<?php

namespace App\GraphQL\Queries;

use App\Models\DifferentiationAnalysis;

class DifferentiationSummary
{
    /** @param array{space_id: string} $args */
    /** @return array<string, mixed> */
    public function __invoke(mixed $root, array $args): array
    {
        /** @var object{total_analyses: int|string, avg_differentiation_score: float|string|null, avg_similarity_score: float|string|null, max_differentiation_score: float|string|null, min_differentiation_score: float|string|null, last_analyzed_at: string|null}|null $summary */
        $summary = DifferentiationAnalysis::where('space_id', $args['space_id'])
            ->selectRaw('
                COUNT(*) as total_analyses,
                AVG(differentiation_score) as avg_differentiation_score,
                AVG(similarity_score) as avg_similarity_score,
                MAX(differentiation_score) as max_differentiation_score,
                MIN(differentiation_score) as min_differentiation_score,
                MAX(analyzed_at) as last_analyzed_at
            ')
            ->first();

        return [
            'total_analyses' => (int) ($summary->total_analyses ?? 0),
            'avg_differentiation_score' => round((float) ($summary->avg_differentiation_score ?? 0.0), 4),
            'avg_similarity_score' => round((float) ($summary->avg_similarity_score ?? 0.0), 4),
            'max_differentiation_score' => round((float) ($summary->max_differentiation_score ?? 0.0), 4),
            'min_differentiation_score' => round((float) ($summary->min_differentiation_score ?? 0.0), 4),
            'last_analyzed_at' => $summary->last_analyzed_at ?? null,
        ];
    }
}
