<?php

namespace App\GraphQL\Queries;

use App\Models\MediaAsset;

final class MediaAssetsQuery
{
    /**
     * Cursor-paginated media assets.
     *
     * @param  array{spaceId: string, first?: int, after?: string|null}  $args
     * @return array{edges: array<int, array{node: MediaAsset, cursor: string}>, pageInfo: array<string, mixed>, totalCount: int}
     */
    public function __invoke(mixed $root, array $args): array
    {
        $first = $args['first'] ?? 20;
        $after = $args['after'] ?? null;

        $query = MediaAsset::query()
            ->where('space_id', $args['spaceId'])
            ->orderByDesc('created_at');

        $totalCount = (clone $query)->count();

        if ($after !== null) {
            $afterId = base64_decode($after);
            $query->where('id', '<', $afterId);
        }

        $items = $query->limit($first + 1)->get();
        $hasNextPage = $items->count() > $first;
        $items = $items->take($first);

        $edges = $items->map(fn (MediaAsset $asset) => [
            'node' => $asset,
            'cursor' => base64_encode((string) $asset->id),
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
