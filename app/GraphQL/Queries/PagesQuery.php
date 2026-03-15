<?php

namespace App\GraphQL\Queries;

use App\Models\Page;

final class PagesQuery
{
    /**
     * Cursor-paginated pages (public endpoint — returns published only).
     *
     * @param  array{spaceId: string, first?: int, after?: string|null}  $args
     * @return array{edges: array<int, array{node: Page, cursor: string}>, pageInfo: array<string, mixed>, totalCount: int}
     */
    public function __invoke(mixed $root, array $args): array
    {
        $first = $args['first'] ?? 20;
        $after = $args['after'] ?? null;

        $query = Page::query()
            ->where('space_id', $args['spaceId'])
            ->where('status', 'published')
            ->orderByDesc('created_at');

        $totalCount = (clone $query)->count();

        if ($after !== null) {
            $afterId = base64_decode($after);
            $query->where('id', '<', $afterId);
        }

        $items = $query->limit($first + 1)->get();
        $hasNextPage = $items->count() > $first;
        $items = $items->take($first);

        $edges = $items->map(fn (Page $page) => [
            'node' => $page,
            'cursor' => base64_encode((string) $page->id),
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
