<?php

namespace App\Services\Graph;

use App\Models\ContentGraphEdge;
use App\Models\ContentGraphNode;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Read-side service for querying the content knowledge graph.
 */
class GraphQueryService
{
    /**
     * Return content related to the given contentId, ordered by edge weight DESC.
     *
     * @return Collection<int, array{content_id: string, edge_type: string, weight: float, node_metadata: array<string, mixed>}>
     */
    public function relatedContent(
        string $contentId,
        string $spaceId,
        ?string $edgeType = null,
        int $limit = 10,
    ): Collection {
        $sourceNode = ContentGraphNode::where('content_id', $contentId)
            ->where('space_id', $spaceId)
            ->first();

        if ($sourceNode === null) {
            return collect();
        }

        $query = ContentGraphEdge::with('targetNode')
            ->where('source_id', $sourceNode->id)
            ->where('space_id', $spaceId)
            ->orderByDesc('weight')
            ->limit($limit);

        if ($edgeType !== null) {
            $query->where('edge_type', $edgeType);
        }

        return $query->get()->map(function (ContentGraphEdge $edge): array {
            /** @var array<string, mixed> $meta */
            $meta = $edge->targetNode !== null ? ($edge->targetNode->node_metadata ?? []) : [];

            return [
                'content_id' => $edge->targetNode !== null ? $edge->targetNode->content_id : '',
                'edge_type' => $edge->edge_type,
                'weight' => $edge->weight,
                'node_metadata' => $meta,
            ];
        });
    }

    /**
     * Return cluster summaries for a space, grouped by cluster_id.
     *
     * @return Collection<int, array{cluster_id: string, node_count: int, top_entities: array<int, string>, sample_titles: array<int, string>}>
     */
    public function topicClusters(string $spaceId, int $limit = 20): Collection
    {
        $nodes = ContentGraphNode::where('space_id', $spaceId)
            ->whereNotNull('cluster_id')
            ->get();

        return $nodes
            ->groupBy('cluster_id')
            ->map(function (Collection $clusterNodes, string $clusterId): array {
                /** @var array<string, int> $entityCounts */
                $entityCounts = [];
                foreach ($clusterNodes as $node) {
                    /** @var array<int, string> $labels */
                    $labels = $node->entity_labels ?? [];
                    foreach ($labels as $label) {
                        $entityCounts[$label] = ($entityCounts[$label] ?? 0) + 1;
                    }
                }
                arsort($entityCounts);
                /** @var array<int, string> $topEntities */
                $topEntities = array_values(array_slice(array_keys($entityCounts), 0, 5));

                /** @var array<int, string> $sampleTitles */
                $sampleTitles = array_values(
                    $clusterNodes
                        ->take(3)
                        ->map(fn (ContentGraphNode $n): string => (string) ($n->node_metadata['title'] ?? ''))
                        ->filter()
                        ->values()
                        ->all()
                );

                return [
                    'cluster_id' => $clusterId,
                    'node_count' => $clusterNodes->count(),
                    'top_entities' => $topEntities,
                    'sample_titles' => $sampleTitles,
                ];
            })
            ->values()
            ->sortByDesc('node_count')
            ->take($limit)
            ->values();
    }

    /**
     * Return all nodes in a given cluster with their metadata.
     *
     * @return Collection<int, array{node_id: string, content_id: string, node_metadata: array<string, mixed>, entity_labels: array<int, string>}>
     */
    public function clusterContents(string $clusterId): Collection
    {
        return ContentGraphNode::where('cluster_id', $clusterId)
            ->get()
            ->map(fn (ContentGraphNode $node): array => [
                'node_id' => $node->id,
                'content_id' => $node->content_id,
                'node_metadata' => $node->node_metadata ?? [],
                'entity_labels' => $node->entity_labels ?? [],
            ]);
    }

    /**
     * Find content gaps: clusters with <3 nodes, low avg edge weight, or no content in last 30 days.
     *
     * @return Collection<int, mixed>
     */
    public function contentGaps(string $spaceId): Collection
    {
        $nodes = ContentGraphNode::where('space_id', $spaceId)
            ->whereNotNull('cluster_id')
            ->get();

        $cutoff = now()->subDays(30);

        return $nodes
            ->groupBy('cluster_id')
            ->map(function (Collection $clusterNodes, string $clusterId) use ($spaceId, $cutoff): ?array {
                $nodeCount = $clusterNodes->count();
                $nodeIds = $clusterNodes->pluck('id')->all();

                $avgWeight = 0.0;
                if (count($nodeIds) > 0) {
                    $rawAvg = ContentGraphEdge::where('space_id', $spaceId)
                        ->whereIn('source_id', $nodeIds)
                        ->whereIn('target_id', $nodeIds)
                        ->avg('weight');
                    $avgWeight = $rawAvg !== null ? (float) $rawAvg : 0.0;
                }

                $lastIndexed = $clusterNodes->max('indexed_at');
                $noRecentContent = $lastIndexed === null || $lastIndexed < $cutoff;

                $isGap = $nodeCount < 3 || $avgWeight < 0.3 || $noRecentContent;
                if (! $isGap) {
                    return null;
                }

                /** @var array<string, int> $entityCounts */
                $entityCounts = [];
                foreach ($clusterNodes as $node) {
                    /** @var array<int, string> $labels */
                    $labels = $node->entity_labels ?? [];
                    foreach ($labels as $label) {
                        $entityCounts[$label] = ($entityCounts[$label] ?? 0) + 1;
                    }
                }
                arsort($entityCounts);
                /** @var array<int, string> $suggestedEntities */
                $suggestedEntities = array_values(array_slice(array_keys($entityCounts), 0, 5));

                $lastIndexedStr = $lastIndexed instanceof Carbon ? $lastIndexed->toIso8601String() : null;

                return [
                    'cluster_id' => $clusterId,
                    'node_count' => $nodeCount,
                    'avg_edge_weight' => round($avgWeight, 4),
                    'last_indexed_at' => $lastIndexedStr,
                    'suggested_entities' => $suggestedEntities,
                ];
            })
            ->filter()
            ->values();
    }

    /**
     * BFS shortest path between two content IDs in a space.
     *
     * @return array<int, string>|null
     */
    public function shortestPath(
        string $fromContentId,
        string $toContentId,
        string $spaceId,
        int $maxDepth = 5,
    ): ?array {
        $fromNode = ContentGraphNode::where('content_id', $fromContentId)
            ->where('space_id', $spaceId)
            ->first();

        $toNode = ContentGraphNode::where('content_id', $toContentId)
            ->where('space_id', $spaceId)
            ->first();

        if ($fromNode === null || $toNode === null) {
            return null;
        }

        $fromId = $fromNode->id;
        $toId = $toNode->id;

        if ($fromId === $toId) {
            return [$fromId];
        }

        /** @var array<int, array{0: string, 1: array<int, string>}> $queue */
        $queue = [[$fromId, [$fromId]]];
        /** @var array<string, true> $visited */
        $visited = [$fromId => true];

        while (count($queue) > 0) {
            [$currentId, $path] = array_shift($queue);

            if (count($path) > $maxDepth) {
                continue;
            }

            /** @var array<int, string> $neighbourIds */
            $neighbourIds = ContentGraphEdge::where('source_id', $currentId)
                ->where('space_id', $spaceId)
                ->pluck('target_id')
                ->all();

            foreach ($neighbourIds as $neighbourId) {
                if (isset($visited[$neighbourId])) {
                    continue;
                }

                /** @var array<int, string> $newPath */
                $newPath = [...$path, $neighbourId];

                if ($neighbourId === $toId) {
                    return $newPath;
                }

                $visited[$neighbourId] = true;
                $queue[] = [$neighbourId, $newPath];
            }
        }

        return null;
    }
}
