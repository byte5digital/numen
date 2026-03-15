<?php

namespace App\GraphQL\Queries;

use App\Models\ContentBrief;

final class BriefsQuery
{
    /**
     * Cursor-paginated content briefs.
     *
     * @param  array{spaceId: string, status?: string|null, first?: int, after?: string|null}  $args
     * @return array{edges: array<int, array{node: ContentBrief, cursor: string}>, pageInfo: array<string, mixed>, totalCount: int}
     */
    public function __invoke(mixed $root, array $args): array
    {
        $first = $args['first'] ?? 20;
        $after = $args['after'] ?? null;

        $query = ContentBrief::query()
            ->where('space_id', $args['spaceId'])
            ->orderByDesc('created_at');

        if (! empty($args['status'])) {
            $query->where('status', strtolower((string) $args['status']));
        }

        $totalCount = (clone $query)->count();

        if ($after !== null) {
            $afterId = base64_decode($after);
            $query->where('id', '<', $afterId);
        }

        $items = $query->limit($first + 1)->get();
        $hasNextPage = $items->count() > $first;
        $items = $items->take($first);

        $edges = $items->map(fn (ContentBrief $brief) => [
            'node' => $brief,
            'cursor' => base64_encode((string) $brief->id),
        ])->values()->all();

        return [
            'edges' => $edges,
            'pageInfo' => [
                'hasNextPage' => $hasNextPage,
                'hasPreviousPage' => $after !== null,
                'startCursor' => isset($edges[0]) ? $edges[0]['cursor'] : null,
                'endCursor' => isset($edges[count($edges) - 1]) ? $edges[count($edges) - 1]['cursor'] : null,
            ],
            'totalCount' => $totalCount,
        ];
    }
}
