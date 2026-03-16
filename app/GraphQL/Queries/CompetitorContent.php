<?php

namespace App\GraphQL\Queries;

use App\Models\CompetitorContentItem;
use Illuminate\Pagination\LengthAwarePaginator;

class CompetitorContent
{
    /** @param array{space_id: string, source_id?: string|null, first?: int, page?: int} $args */
    public function __invoke(mixed $root, array $args): LengthAwarePaginator
    {
        $query = CompetitorContentItem::query()
            ->whereHas('source', fn ($q) => $q->where('space_id', $args['space_id']))
            ->with('source')
            ->orderByDesc('crawled_at');

        if (! empty($args['source_id'])) {
            $query->where('source_id', $args['source_id']);
        }

        $perPage = (int) ($args['first'] ?? 20);
        $page = (int) ($args['page'] ?? 1);

        return $query->paginate($perPage, ['*'], 'page', $page);
    }
}
