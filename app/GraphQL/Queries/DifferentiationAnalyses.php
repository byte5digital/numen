<?php

namespace App\GraphQL\Queries;

use App\Models\DifferentiationAnalysis;
use Illuminate\Pagination\LengthAwarePaginator;

class DifferentiationAnalyses
{
    /** @param array{space_id: string, content_id?: string|null, brief_id?: string|null, first?: int, page?: int} $args */
    public function __invoke(mixed $root, array $args): LengthAwarePaginator
    {
        $query = DifferentiationAnalysis::where('space_id', $args['space_id'])
            ->with('competitorContent')
            ->orderByDesc('analyzed_at');

        if (! empty($args['content_id'])) {
            $query->where('content_id', $args['content_id']);
        }

        if (! empty($args['brief_id'])) {
            $query->where('brief_id', $args['brief_id']);
        }

        $perPage = (int) ($args['first'] ?? 20);
        $page = (int) ($args['page'] ?? 1);

        return $query->paginate($perPage, ['*'], 'page', $page);
    }
}
