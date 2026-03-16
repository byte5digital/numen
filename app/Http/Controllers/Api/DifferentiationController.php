<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\DifferentiationAnalysisResource;
use App\Models\DifferentiationAnalysis;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class DifferentiationController extends Controller
{
    /**
     * GET /api/v1/competitor/differentiation
     * List differentiation analyses for a space.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'space_id' => ['required', 'string'],
            'content_id' => ['nullable', 'string'],
            'brief_id' => ['nullable', 'string'],
            'min_score' => ['nullable', 'numeric', 'min:0', 'max:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = DifferentiationAnalysis::where('space_id', $validated['space_id'])
            ->with('competitorContent')
            ->orderByDesc('analyzed_at');

        if (! empty($validated['content_id'])) {
            $query->where('content_id', $validated['content_id']);
        }

        if (! empty($validated['brief_id'])) {
            $query->where('brief_id', $validated['brief_id']);
        }

        if (isset($validated['min_score'])) {
            $query->where('differentiation_score', '>=', (float) $validated['min_score']);
        }

        return DifferentiationAnalysisResource::collection(
            $query->paginate((int) ($validated['per_page'] ?? 20))
        );
    }

    /**
     * GET /api/v1/competitor/differentiation/{id}
     * Show a single differentiation analysis.
     */
    public function show(string $id): JsonResponse
    {
        $analysis = DifferentiationAnalysis::with('competitorContent')->findOrFail($id);

        return response()->json(['data' => new DifferentiationAnalysisResource($analysis)]);
    }

    /**
     * GET /api/v1/competitor/differentiation/summary
     * Aggregate differentiation score summary for a space.
     */
    public function summary(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'space_id' => ['required', 'string'],
        ]);

        /** @var object{total_analyses: int|string, avg_differentiation_score: float|string|null, avg_similarity_score: float|string|null, max_differentiation_score: float|string|null, min_differentiation_score: float|string|null, last_analyzed_at: string|null}|null $summary */
        $summary = DifferentiationAnalysis::where('space_id', $validated['space_id'])
            ->selectRaw('
                COUNT(*) as total_analyses,
                AVG(differentiation_score) as avg_differentiation_score,
                AVG(similarity_score) as avg_similarity_score,
                MAX(differentiation_score) as max_differentiation_score,
                MIN(differentiation_score) as min_differentiation_score,
                MAX(analyzed_at) as last_analyzed_at
            ')
            ->first();

        return response()->json([
            'data' => [
                'total_analyses' => (int) ($summary->total_analyses ?? 0),
                'avg_differentiation_score' => round((float) ($summary->avg_differentiation_score ?? 0), 4),
                'avg_similarity_score' => round((float) ($summary->avg_similarity_score ?? 0), 4),
                'max_differentiation_score' => round((float) ($summary->max_differentiation_score ?? 0), 4),
                'min_differentiation_score' => round((float) ($summary->min_differentiation_score ?? 0), 4),
                'last_analyzed_at' => $summary->last_analyzed_at ?? null,
            ],
        ]);
    }
}
