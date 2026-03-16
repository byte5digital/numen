<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCompetitorAlertRequest;
use App\Http\Resources\CompetitorAlertResource;
use App\Http\Resources\CompetitorContentItemResource;
use App\Jobs\CrawlCompetitorSourceJob;
use App\Models\CompetitorAlert;
use App\Models\CompetitorContentItem;
use App\Models\CompetitorSource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CompetitorController extends Controller
{
    /**
     * GET /api/v1/competitor/content
     * List competitor content items for a space.
     */
    public function content(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'space_id' => ['required', 'string'],
            'source_id' => ['nullable', 'string'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $currentSpace = app()->bound('current_space') ? app('current_space') : null;
        abort_if($currentSpace && $validated['space_id'] !== $currentSpace->id, 403);

        $query = CompetitorContentItem::query()
            ->whereHas('source', fn ($q) => $q->where('space_id', $validated['space_id']))
            ->with('source')
            ->orderByDesc('crawled_at');

        if (! empty($validated['source_id'])) {
            $query->where('source_id', $validated['source_id']);
        }

        return CompetitorContentItemResource::collection(
            $query->paginate((int) ($validated['per_page'] ?? 20))
        );
    }

    /**
     * POST /api/v1/competitor/sources/{id}/crawl
     * Trigger an immediate crawl for a source.
     */
    public function crawl(string $id): JsonResponse
    {
        $source = CompetitorSource::findOrFail($id);

        $currentSpace = app()->bound('current_space') ? app('current_space') : null;
        abort_if($currentSpace && $source->space_id !== $currentSpace->id, 403);

        CrawlCompetitorSourceJob::dispatch($source);

        return response()->json(['message' => 'Crawl job dispatched', 'source_id' => $source->id]);
    }

    /**
     * GET /api/v1/competitor/alerts
     */
    public function alerts(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'space_id' => ['required', 'string'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $currentSpace = app()->bound('current_space') ? app('current_space') : null;
        abort_if($currentSpace && $validated['space_id'] !== $currentSpace->id, 403);

        $alerts = CompetitorAlert::where('space_id', $validated['space_id'])
            ->orderByDesc('created_at')
            ->paginate((int) ($validated['per_page'] ?? 20));

        return CompetitorAlertResource::collection($alerts);
    }

    /**
     * POST /api/v1/competitor/alerts
     */
    public function storeAlert(StoreCompetitorAlertRequest $request): JsonResponse
    {
        $alert = CompetitorAlert::create($request->validated());

        return response()->json(['data' => new CompetitorAlertResource($alert)], 201);
    }

    /**
     * DELETE /api/v1/competitor/alerts/{id}
     */
    public function destroyAlert(string $id): JsonResponse
    {
        $alert = CompetitorAlert::findOrFail($id);

        $currentSpace = app()->bound('current_space') ? app('current_space') : null;
        abort_if($currentSpace && $alert->space_id !== $currentSpace->id, 403);

        $alert->delete();

        return response()->json(null, 204);
    }
}
