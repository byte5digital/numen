<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PerformanceCorrelationResource;
use App\Models\Performance\PerformanceCorrelation;
use App\Models\Space;
use App\Services\Performance\PerformanceCorrelatorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PerformanceCorrelationController extends Controller
{
    public function __construct(
        private readonly PerformanceCorrelatorService $correlatorService,
    ) {}

    /**
     * GET /api/v1/spaces/{space}/performance/correlations
     */
    public function index(Request $request, Space $space): AnonymousResourceCollection
    {
        $query = PerformanceCorrelation::where('space_id', $space->id);

        if ($request->filled('attribute_name')) {
            $query->where('attribute_name', $request->input('attribute_name'));
        }

        if ($request->filled('metric_name')) {
            $query->where('metric_name', $request->input('metric_name'));
        }

        $correlations = $query->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 25));

        return PerformanceCorrelationResource::collection($correlations);
    }

    /**
     * GET /api/v1/spaces/{space}/performance/correlations/{contentId}
     */
    public function show(Space $space, string $contentId): AnonymousResourceCollection
    {
        $correlations = PerformanceCorrelation::where('space_id', $space->id)
            ->where('content_id', $contentId)
            ->orderByDesc('created_at')
            ->get();

        return PerformanceCorrelationResource::collection($correlations);
    }

    /**
     * POST /api/v1/spaces/{space}/performance/correlations/analyze
     */
    public function analyze(Request $request, Space $space): JsonResponse
    {
        $validated = $request->validate([
            'content_id' => 'required|string|max:26',
        ]);

        $result = $this->correlatorService->correlate($validated['content_id']);

        return response()->json([
            'data' => $result,
            'message' => 'Correlation analysis completed.',
        ], 201);
    }
}
