<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\IndexContentForSearchJob;
use App\Models\ContentEmbedding;
use App\Models\PromotedResult;
use App\Models\SearchSynonym;
use App\Services\AuthorizationService;
use App\Services\Search\SearchAnalyticsService;
use App\Services\Search\SearchCapabilityDetector;
use App\Services\Search\SynonymSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Admin search management endpoints.
 * Requires Sanctum authentication + admin role.
 */
class SearchAdminController extends Controller
{
    public function __construct(
        private readonly SearchAnalyticsService $analyticsService,
        private readonly SynonymSyncService $synonymSync,
        private readonly SearchCapabilityDetector $capabilityDetector,
        private readonly AuthorizationService $authz,
    ) {}

    // ── Analytics ────────────────────────────────────────────────────────────

    public function analytics(Request $request): JsonResponse
    {
        $this->authz->authorize($request->user(), 'search.admin');

        $spaceId = (string) ($request->input('space_id') ?? 'default');
        $period = (string) ($request->input('period', '7d'));

        $data = $this->analyticsService->getDashboard($spaceId, $period);

        return response()->json(['data' => $data]);
    }

    public function contentGaps(Request $request): JsonResponse
    {
        $spaceId = (string) ($request->input('space_id') ?? 'default');

        return response()->json([
            'data' => ['gaps' => $this->analyticsService->getContentGaps($spaceId)],
        ]);
    }

    // ── Synonyms ──────────────────────────────────────────────────────────────

    public function synonyms(Request $request): JsonResponse
    {
        $spaceId = (string) ($request->input('space_id') ?? 'default');

        $synonyms = SearchSynonym::where('space_id', $spaceId)->orderBy('term')->get();

        return response()->json(['data' => $synonyms]);
    }

    public function storeSynonym(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'space_id' => ['required', 'string'],
            'term' => ['required', 'string', 'max:255'],
            'synonyms' => ['required', 'array'],
            'synonyms.*' => ['string', 'max:255'],
            'is_one_way' => ['sometimes', 'boolean'],
        ]);

        $synonym = SearchSynonym::create([
            'space_id' => $validated['space_id'],
            'term' => $validated['term'],
            'synonyms' => $validated['synonyms'],
            'is_one_way' => (bool) ($validated['is_one_way'] ?? false),
            'source' => 'manual',
            'approved' => true,
        ]);

        $this->synonymSync->syncToMeilisearch($validated['space_id']);

        return response()->json(['data' => $synonym], 201);
    }

    public function updateSynonym(Request $request, string $id): JsonResponse
    {
        $synonym = SearchSynonym::findOrFail($id);

        $validated = $request->validate([
            'term' => ['sometimes', 'string', 'max:255'],
            'synonyms' => ['sometimes', 'array'],
            'synonyms.*' => ['string', 'max:255'],
            'is_one_way' => ['sometimes', 'boolean'],
            'approved' => ['sometimes', 'boolean'],
        ]);

        $synonym->update($validated);
        $this->synonymSync->syncToMeilisearch($synonym->space_id);

        return response()->json(['data' => $synonym]);
    }

    public function destroySynonym(string $id): JsonResponse
    {
        $synonym = SearchSynonym::findOrFail($id);
        $spaceId = $synonym->space_id;
        $synonym->delete();

        $this->synonymSync->syncToMeilisearch($spaceId);

        return response()->json(null, 204);
    }

    // ── Promoted Results ──────────────────────────────────────────────────────

    public function promoted(Request $request): JsonResponse
    {
        $spaceId = (string) ($request->input('space_id') ?? 'default');

        $promoted = PromotedResult::where('space_id', $spaceId)
            ->with(['content.currentVersion'])
            ->orderBy('position')
            ->get();

        return response()->json(['data' => $promoted]);
    }

    public function storePromoted(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'space_id' => ['required', 'string'],
            'query' => ['required', 'string', 'max:255'],
            'content_id' => ['required', 'string'],
            'position' => ['sometimes', 'integer', 'min:1'],
            'starts_at' => ['sometimes', 'nullable', 'date'],
            'expires_at' => ['sometimes', 'nullable', 'date'],
        ]);

        $promoted = PromotedResult::create($validated);

        return response()->json(['data' => $promoted], 201);
    }

    public function updatePromoted(Request $request, string $id): JsonResponse
    {
        $promoted = PromotedResult::findOrFail($id);

        $validated = $request->validate([
            'query' => ['sometimes', 'string', 'max:255'],
            'content_id' => ['sometimes', 'string'],
            'position' => ['sometimes', 'integer', 'min:1'],
            'starts_at' => ['sometimes', 'nullable', 'date'],
            'expires_at' => ['sometimes', 'nullable', 'date'],
        ]);

        $promoted->update($validated);

        return response()->json(['data' => $promoted]);
    }

    public function destroyPromoted(string $id): JsonResponse
    {
        PromotedResult::findOrFail($id)->delete();

        return response()->json(null, 204);
    }

    // ── Index Health & Re-index ───────────────────────────────────────────────

    public function health(): JsonResponse
    {
        $caps = $this->capabilityDetector->detect();
        $totalEmbeddings = ContentEmbedding::count();

        return response()->json([
            'data' => [
                'capabilities' => $caps->toArray(),
                'embeddings_count' => $totalEmbeddings,
                'embedding_model' => config('numen.search.embedding_model', 'text-embedding-3-small'),
                'last_reindex' => Cache::get('search:last_reindex'),
            ],
        ]);
    }

    public function reindex(Request $request): JsonResponse
    {
        // Prevent reindex spam — 5 minute cooldown
        $lockKey = 'search:reindex_lock';

        if (Cache::has($lockKey)) {
            $retryAfter = (int) Cache::get($lockKey) - time();

            return response()->json([
                'error' => 'Reindex already in progress or recently completed.',
                'retry_after_seconds' => max(0, $retryAfter),
            ], 429);
        }

        Cache::put($lockKey, time() + 300, 300);

        $spaceId = $request->input('space_id');

        // Dispatch indexing for all published content
        $query = \App\Models\Content::published();

        if ($spaceId) {
            $query->where('space_id', $spaceId);
        }

        $count = 0;
        $maxItems = 10000;
        $query->chunk(100, function ($contents) use (&$count, $maxItems): bool {
            foreach ($contents as $content) {
                if ($count >= $maxItems) {
                    return false;
                }
                IndexContentForSearchJob::dispatch($content->id)->onQueue('search');
                $count++;
            }

            return $count < $maxItems;
        });

        Cache::put('search:last_reindex', now()->toISOString());

        return response()->json([
            'data' => [
                'message' => "Re-indexing {$count} content items dispatched to queue.",
                'count' => $count,
            ],
        ]);
    }
}
