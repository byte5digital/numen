<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PerformanceInsightResource;
use App\Http\Resources\PerformanceModelResource;
use App\Models\Performance\SpacePerformanceModel;
use App\Models\Space;
use App\Services\Performance\PerformanceInsightBuilder;
use App\Services\Performance\SpacePerformanceModelService;
use Illuminate\Http\JsonResponse;

class PerformanceInsightsController extends Controller
{
    public function __construct(
        private readonly PerformanceInsightBuilder $insightBuilder,
        private readonly SpacePerformanceModelService $modelService,
    ) {}

    /**
     * GET /api/v1/spaces/{space}/performance/insights
     */
    public function index(Space $space): PerformanceInsightResource
    {
        $insights = $this->insightBuilder->buildInsights($space->id);

        return new PerformanceInsightResource($insights);
    }

    /**
     * GET /api/v1/spaces/{space}/performance/insights/{contentId}
     */
    public function show(Space $space, string $contentId): PerformanceInsightResource
    {
        $insights = $this->insightBuilder->buildInsights($space->id, $contentId);

        return new PerformanceInsightResource($insights);
    }

    /**
     * GET /api/v1/spaces/{space}/performance/model
     */
    public function model(Space $space): JsonResponse
    {
        $model = SpacePerformanceModel::where('space_id', $space->id)->first();

        if (! $model) {
            return response()->json([
                'data' => null,
                'message' => 'No performance model exists for this space yet.',
            ]);
        }

        $recommendations = $this->modelService->getRecommendations($space->id);

        return response()->json([
            'data' => (new PerformanceModelResource($model))->resolve(),
            'recommendations' => $recommendations,
        ]);
    }

    /**
     * POST /api/v1/spaces/{space}/performance/model/rebuild
     */
    public function rebuildModel(Space $space): JsonResponse
    {
        $model = $this->modelService->buildModel($space->id);

        return response()->json([
            'data' => (new PerformanceModelResource($model))->resolve(),
            'message' => 'Performance model rebuilt successfully.',
        ], 201);
    }
}
