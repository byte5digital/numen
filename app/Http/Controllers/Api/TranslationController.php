<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\TranslateContentJob;
use App\Models\Content;
use App\Models\ContentTranslationJob;
use App\Models\Persona;
use App\Models\Space;
use App\Services\AITranslationService;
use App\Services\AuthorizationService;
use App\Services\LocaleService;
use App\Services\TranslationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TranslationController extends Controller
{
    // Maximum number of pending/processing translation jobs per space to
    // prevent abuse and runaway AI costs.
    private const MAX_PENDING_JOBS_PER_SPACE = 50;

    public function __construct(
        private readonly TranslationService $translationService,
        private readonly AITranslationService $aiTranslationService,
        private readonly AuthorizationService $authz,
        private readonly LocaleService $localeService,
    ) {}

    /**
     * GET /v1/translations/matrix?space_id=...
     *
     * Returns a matrix of translation statuses for all content in the space:
     * { content_id => { locale => status } }
     */
    public function matrix(Request $request): JsonResponse
    {
        $request->validate([
            'space_id' => ['required', 'integer', 'exists:spaces,id'],
        ]);

        $spaceId = (string) $request->integer('space_id');
        $this->authz->authorize($request->user(), 'content.read', $spaceId);

        $space = Space::findOrFail($spaceId);

        $matrix = $this->translationService->getTranslationMatrix($space);

        // Build per-locale completion stats
        $totalContent = count($matrix);
        $localeSummary = [];

        foreach ($matrix as $localeStatuses) {
            foreach ($localeStatuses as $locale => $status) {
                if (! isset($localeSummary[$locale])) {
                    $localeSummary[$locale] = ['completed' => 0, 'total' => 0];
                }
                $localeSummary[$locale]['total']++;
                if ($status === 'completed') {
                    $localeSummary[$locale]['completed']++;
                }
            }
        }

        $localeCompletion = [];
        foreach ($localeSummary as $locale => $counts) {
            $localeCompletion[$locale] = [
                'completed' => $counts['completed'],
                'total' => $counts['total'],
                'completion_percentage' => $counts['total'] > 0
                    ? round(($counts['completed'] / $counts['total']) * 100, 1)
                    : 0.0,
            ];
        }

        return response()->json([
            'data' => [
                'matrix' => $matrix,
                'summary' => [
                    'total_content' => $totalContent,
                    'total_locales' => count($localeCompletion),
                    'locales' => $localeCompletion,
                ],
            ],
        ]);
    }

    /**
     * POST /v1/content/{content}/translate
     *
     * Body: { target_locale, persona_id? }
     * Creates a ContentTranslationJob and dispatches TranslateContentJob.
     */
    public function translate(Request $request, Content $content): JsonResponse
    {
        // Verify the authenticated user has access to this content's space
        $this->authz->authorize($request->user(), 'content.update', (string) $content->space_id);

        $validated = $request->validate([
            'target_locale' => ['required', 'string', 'max:10', 'regex:/^[a-zA-Z]{2,3}(-[a-zA-Z0-9]{2,8})*$/'],
            'persona_id'    => ['sometimes', 'nullable', 'integer', 'exists:personas,id'],
        ]);

        // Validate persona belongs to the same space as the content (IDOR guard)
        $persona = null;
        if (! empty($validated['persona_id'])) {
            $persona = Persona::where('id', $validated['persona_id'])
                ->where('space_id', $content->space_id)
                ->first();

            if (! $persona) {
                return response()->json([
                    'error'   => 'Validation Error',
                    'message' => 'The selected persona does not belong to this space.',
                ], 422);
            }
        }

        // AI cost guard: cap the number of queued jobs per space
        $pendingCount = ContentTranslationJob::where('space_id', $content->space_id)
            ->whereIn('status', ['pending', 'processing'])
            ->count();

        if ($pendingCount >= self::MAX_PENDING_JOBS_PER_SPACE) {
            return response()->json([
                'error'   => 'Too Many Requests',
                'message' => 'Maximum concurrent translation jobs per space reached. Please wait for existing jobs to complete.',
            ], 429);
        }

        $job = $this->translationService->createTranslationJob(
            $content,
            $validated['target_locale'],
            $persona,
        );

        // Only dispatch if newly created (pending and not already running)
        if ($job->status === 'pending' && $job->wasRecentlyCreated) {
            TranslateContentJob::dispatch($job);
        }

        return response()->json([
            'data' => $this->formatJob($job),
        ], 201);
    }

    /**
     * GET /v1/content/{content}/translations
     *
     * Returns all translation jobs for this content with their status.
     */
    public function status(Request $request, Content $content): JsonResponse
    {
        $this->authz->authorize($request->user(), 'content.read', (string) $content->space_id);

        $jobs = ContentTranslationJob::where('source_content_id', $content->id)
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'data' => $jobs->map(fn ($job) => $this->formatJob($job)),
        ]);
    }

    /**
     * DELETE /v1/translations/{job}
     *
     * Cancel a pending or processing translation job.
     */
    public function cancel(Request $request, ContentTranslationJob $job): JsonResponse
    {
        $this->authz->authorize($request->user(), 'content.update', (string) $job->space_id);

        $this->translationService->cancelJob($job);

        return response()->json([
            'data' => $this->formatJob($job->fresh()),
        ]);
    }

    /**
     * POST /v1/translations/{job}/retry
     *
     * Retry a failed translation job.
     */
    public function retry(Request $request, ContentTranslationJob $job): JsonResponse
    {
        $this->authz->authorize($request->user(), 'content.update', (string) $job->space_id);

        // AI cost guard: cap the number of queued jobs per space before retrying
        $pendingCount = ContentTranslationJob::where('space_id', $job->space_id)
            ->whereIn('status', ['pending', 'processing'])
            ->count();

        if ($pendingCount >= self::MAX_PENDING_JOBS_PER_SPACE) {
            return response()->json([
                'error'   => 'Too Many Requests',
                'message' => 'Maximum concurrent translation jobs per space reached. Please wait for existing jobs to complete.',
            ], 429);
        }

        $this->translationService->retryJob($job);

        $job->refresh();

        TranslateContentJob::dispatch($job);

        return response()->json([
            'data' => $this->formatJob($job),
        ]);
    }

    /**
     * GET /v1/content/{content}/translate/estimate?target_locale=...
     *
     * Returns estimated token usage for translating this content.
     */
    public function estimateCost(Request $request, Content $content): JsonResponse
    {
        $this->authz->authorize($request->user(), 'content.read', (string) $content->space_id);

        $request->validate([
            'target_locale' => ['required', 'string', 'max:10', 'regex:/^[a-zA-Z]{2,3}(-[a-zA-Z0-9]{2,8})*$/'],
        ]);

        $estimate = $this->aiTranslationService->estimateCost($content);

        return response()->json([
            'data' => array_merge($estimate, [
                'target_locale' => $request->input('target_locale'),
                'source_locale' => $content->locale,
            ]),
        ]);
    }

    /**
     * Format a ContentTranslationJob for API responses.
     *
     * @return array<string, mixed>
     */
    private function formatJob(ContentTranslationJob $job): array
    {
        return [
            'id'                => $job->id,
            'space_id'          => $job->space_id,
            'source_content_id' => $job->source_content_id,
            'target_content_id' => $job->target_content_id,
            'source_locale'     => $job->source_locale,
            'target_locale'     => $job->target_locale,
            'status'            => $job->status,
            'persona_id'        => $job->persona_id,
            'error_message'     => $job->error_message,
            'started_at'        => $job->started_at?->toIso8601String(),
            'completed_at'      => $job->completed_at?->toIso8601String(),
            'created_at'        => $job->created_at->toIso8601String(),
            'updated_at'        => $job->updated_at->toIso8601String(),
        ];
    }
}
