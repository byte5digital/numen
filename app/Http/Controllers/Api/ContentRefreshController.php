<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Performance\ContentRefreshSuggestion;
use App\Models\Space;
use App\Services\Performance\AutoBriefGeneratorService;
use App\Services\Performance\ContentRefreshAdvisorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContentRefreshController extends Controller
{
    public function __construct(
        private readonly ContentRefreshAdvisorService $advisorService,
        private readonly AutoBriefGeneratorService $briefGenerator,
    ) {}

    /**
     * GET /api/v1/spaces/{space}/refresh-suggestions
     */
    public function index(Request $request, Space $space): JsonResponse
    {
        $query = ContentRefreshSuggestion::where('space_id', $space->id)
            ->orderByDesc('urgency_score');

        if ($request->has('priority')) {
            $minScore = match ($request->input('priority')) {
                'high' => 50,
                'medium' => 25,
                'low' => 0,
                default => 0,
            };
            $maxScore = match ($request->input('priority')) {
                'high' => 100,
                'medium' => 49.99,
                'low' => 24.99,
                default => 100,
            };
            $query->where('urgency_score', '>=', $minScore)
                ->where('urgency_score', '<=', $maxScore);
        }

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        $suggestions = $query->paginate($request->integer('per_page', 15));

        return response()->json($suggestions);
    }

    /**
     * GET /api/v1/spaces/{space}/refresh-suggestions/{suggestion}
     */
    public function show(Space $space, ContentRefreshSuggestion $suggestion): JsonResponse
    {
        if ($suggestion->space_id !== $space->id) {
            return response()->json(['error' => 'Suggestion not found in this space.'], 404);
        }

        return response()->json(['data' => $suggestion]);
    }

    /**
     * POST /api/v1/spaces/{space}/refresh-suggestions/{suggestion}/accept
     */
    public function accept(Space $space, ContentRefreshSuggestion $suggestion): JsonResponse
    {
        if ($suggestion->space_id !== $space->id) {
            return response()->json(['error' => 'Suggestion not found in this space.'], 404);
        }

        if ($suggestion->status !== 'pending') {
            return response()->json(['error' => 'Only pending suggestions can be accepted.'], 422);
        }

        $brief = $this->briefGenerator->generateRefreshBrief($suggestion);

        return response()->json([
            'data' => [
                'suggestion' => $suggestion->fresh(),
                'brief' => $brief,
            ],
        ]);
    }

    /**
     * POST /api/v1/spaces/{space}/refresh-suggestions/{suggestion}/dismiss
     */
    public function dismiss(Space $space, ContentRefreshSuggestion $suggestion): JsonResponse
    {
        if ($suggestion->space_id !== $space->id) {
            return response()->json(['error' => 'Suggestion not found in this space.'], 404);
        }

        if ($suggestion->status === 'dismissed') {
            return response()->json(['error' => 'Suggestion already dismissed.'], 422);
        }

        $suggestion->update([
            'status' => 'dismissed',
            'acted_on_at' => now(),
        ]);

        return response()->json(['data' => $suggestion->fresh()]);
    }

    /**
     * POST /api/v1/spaces/{space}/refresh-suggestions/generate
     */
    public function generate(Space $space): JsonResponse
    {
        $suggestions = $this->advisorService->generateSuggestions($space->id);

        return response()->json([
            'data' => $suggestions,
            'meta' => ['total' => $suggestions->count()],
        ]);
    }
}
