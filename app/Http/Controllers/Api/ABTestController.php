<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Performance\ContentAbTest;
use App\Models\Space;
use App\Services\Performance\ABTestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ABTestController extends Controller
{
    public function __construct(
        private readonly ABTestService $abTestService,
    ) {}

    /**
     * POST /api/v1/spaces/{space}/ab-tests — create test
     */
    public function store(Request $request, Space $space): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'hypothesis' => 'nullable|string|max:2000',
            'metric' => 'nullable|string|max:100',
            'traffic_split' => 'nullable|numeric|min:0|max:1',
            'min_sample_size' => 'nullable|integer|min:10',
            'significance_threshold' => 'nullable|numeric|min:0.5|max:0.99',
            'variants' => 'required|array|min:2',
            'variants.*.content_id' => 'required|string|max:26',
            'variants.*.label' => 'required|string|max:255',
            'variants.*.is_control' => 'nullable|boolean',
            'variants.*.generation_params' => 'nullable|array',
        ]);

        $test = $this->abTestService->createTest($space->id, $validated);

        return response()->json(['data' => $test], 201);
    }

    /**
     * GET /api/v1/spaces/{space}/ab-tests — list tests
     */
    public function index(Request $request, Space $space): JsonResponse
    {
        $tests = ContentAbTest::where('space_id', $space->id)
            ->with('variants')
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 15));

        return response()->json($tests);
    }

    /**
     * GET /api/v1/spaces/{space}/ab-tests/{test} — show test + results
     */
    public function show(Space $space, ContentAbTest $test): JsonResponse
    {
        if ($test->space_id !== $space->id) {
            return response()->json(['error' => 'Test not found in this space.'], 404);
        }

        $results = $this->abTestService->getResults($test);

        return response()->json([
            'data' => array_merge($test->toArray(), ['results' => $results]),
        ]);
    }

    /**
     * POST /api/v1/spaces/{space}/ab-tests/{test}/assign — assign variant
     */
    public function assign(Request $request, Space $space, ContentAbTest $test): JsonResponse
    {
        if ($test->space_id !== $space->id) {
            return response()->json(['error' => 'Test not found in this space.'], 404);
        }

        $validated = $request->validate([
            'visitor_id' => 'required|string|max:255',
        ]);

        $variant = $this->abTestService->assignVariant($test, $validated['visitor_id']);

        return response()->json(['data' => $variant]);
    }

    /**
     * POST /api/v1/spaces/{space}/ab-tests/{test}/convert — record conversion
     */
    public function convert(Request $request, Space $space, ContentAbTest $test): JsonResponse
    {
        if ($test->space_id !== $space->id) {
            return response()->json(['error' => 'Test not found in this space.'], 404);
        }

        $validated = $request->validate([
            'variant_id' => 'required|string|max:26',
            'visitor_id' => 'required|string|max:255',
        ]);

        $this->abTestService->recordConversion($test, $validated['variant_id'], $validated['visitor_id']);

        return response()->json(['message' => 'Conversion recorded.']);
    }

    /**
     * POST /api/v1/spaces/{space}/ab-tests/{test}/end — end test
     */
    public function end(Space $space, ContentAbTest $test): JsonResponse
    {
        if ($test->space_id !== $space->id) {
            return response()->json(['error' => 'Test not found in this space.'], 404);
        }

        $results = $this->abTestService->endTest($test);

        return response()->json(['data' => $results]);
    }
}
