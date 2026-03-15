<?php

namespace App\GraphQL\Queries;

use App\Models\ContentPipeline;
use App\Models\PipelineRun;

final class PipelineRunsQuery
{
    /**
     * Cursor-paginated pipeline runs for a parent ContentPipeline.
     *
     * @param  array{first?: int, after?: string|null}  $args
     * @return array{edges: array<int, array{node: PipelineRun, cursor: string}>, pageInfo: array<string, mixed>, totalCount: int}
     */
    public function __invoke(ContentPipeline $root, array $args): array
    {
        $first = $args['first'] ?? 20;
        $after = $args['after'] ?? null;

        $query = PipelineRun::query()
            ->where('pipeline_id', $root->id)
            ->orderByDesc('created_at');

        $totalCount = (clone $query)->count();

        if ($after !== null) {
            $afterId = base64_decode($after);
            $query->where('id', '<', $afterId);
        }

        $items = $query->limit($first + 1)->get();
        $hasNextPage = $items->count() > $first;
        $items = $items->take($first);

        $edges = $items->map(fn (PipelineRun $run) => [
            'node' => $run,
            'cursor' => base64_encode((string) $run->id),
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
