<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PerformanceSnapshotResource;
use App\Models\Performance\ContentPerformanceSnapshot;
use App\Models\Space;
use App\Services\Performance\PerformanceAggregatorService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PerformanceSnapshotController extends Controller
{
    public function __construct(
        private readonly PerformanceAggregatorService $aggregatorService,
    ) {}

    /**
     * GET /api/v1/spaces/{space}/performance/snapshots
     */
    public function index(Request $request, Space $space): AnonymousResourceCollection
    {
        $query = ContentPerformanceSnapshot::where('space_id', $space->id);

        if ($request->filled('content_id')) {
            $query->where('content_id', $request->input('content_id'));
        }

        if ($request->filled('period_type')) {
            $query->where('period_type', $request->input('period_type'));
        }

        if ($request->filled('from')) {
            $query->where('period_start', '>=', $request->input('from'));
        }

        if ($request->filled('to')) {
            $query->where('period_start', '<=', $request->input('to'));
        }

        $snapshots = $query->orderByDesc('period_start')
            ->paginate($request->integer('per_page', 25));

        return PerformanceSnapshotResource::collection($snapshots);
    }

    /**
     * GET /api/v1/spaces/{space}/performance/snapshots/{snapshot}
     */
    public function show(Space $space, ContentPerformanceSnapshot $snapshot): PerformanceSnapshotResource
    {
        abort_unless($snapshot->space_id === $space->id, 404);

        return new PerformanceSnapshotResource($snapshot);
    }

    /**
     * POST /api/v1/spaces/{space}/performance/aggregate
     */
    public function aggregate(Request $request, Space $space): JsonResponse
    {
        $validated = $request->validate([
            'content_id' => 'required|string|max:26',
            'period' => 'required|string|in:daily,weekly,monthly',
            'date' => 'nullable|date',
        ]);

        $date = Carbon::parse($validated['date'] ?? now());

        $period = (string) $validated['period'];

        $snapshot = match ($period) {
            'daily' => $this->aggregatorService->aggregateDaily($validated['content_id'], $date),
            'weekly' => $this->aggregatorService->aggregateWeekly($validated['content_id'], $date),
            'monthly' => $this->aggregatorService->aggregateMonthly($validated['content_id'], $date),
            default => abort(422, 'Invalid period type.'),
        };

        return (new PerformanceSnapshotResource($snapshot))
            ->response()
            ->setStatusCode(201);
    }
}
