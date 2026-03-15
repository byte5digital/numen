<?php

namespace App\Services\Graph;

use App\Models\ContentGraphNode;
use Illuminate\Support\Str;

/**
 * Computes content clusters using hierarchical agglomerative clustering
 * with Jaccard distance on entity_labels sets (SQLite-compatible, no embeddings).
 */
class ClusteringService
{
    /**
     * Minimum Jaccard similarity to merge two nodes into the same cluster.
     * Nodes with similarity >= threshold are candidates for merging.
     */
    private const MERGE_THRESHOLD = 0.2;

    /**
     * Compute clusters for a single space and persist cluster_id to each node.
     *
     * @return int Number of distinct clusters created
     */
    public function computeClusters(string $spaceId): int
    {
        /** @var \Illuminate\Database\Eloquent\Collection<int, ContentGraphNode> $nodes */
        $nodes = ContentGraphNode::where('space_id', $spaceId)->get();

        if ($nodes->isEmpty()) {
            return 0;
        }

        // Index nodes by their model id
        /** @var array<string, array<int, string>> $labelSets */
        $labelSets = [];
        foreach ($nodes as $node) {
            $labelSets[$node->id] = array_unique(array_map('strtolower', $node->entity_labels ?? []));
        }

        // Initial: each node is its own cluster (cluster => [nodeId, ...])
        /** @var array<string, list<string>> $clusters */
        $clusters = [];
        foreach ($nodes as $node) {
            $clusters[$node->id] = [$node->id];
        }

        // Hierarchical agglomerative clustering: merge until no pair exceeds threshold
        $merged = true;
        while ($merged) {
            $merged = false;
            $clusterIds = array_keys($clusters);
            $best = null;
            $bestSim = -1.0;
            $bestPair = ['', ''];

            for ($i = 0; $i < count($clusterIds); $i++) {
                for ($j = $i + 1; $j < count($clusterIds); $j++) {
                    $aId = $clusterIds[$i];
                    $bId = $clusterIds[$j];

                    $sim = $this->clusterJaccard(
                        $clusters[$aId],
                        $clusters[$bId],
                        $labelSets
                    );

                    if ($sim > $bestSim) {
                        $bestSim = $sim;
                        $bestPair = [$aId, $bId];
                    }
                }
            }

            if ($bestSim >= self::MERGE_THRESHOLD) {
                [$aId, $bId] = $bestPair;
                // Merge bId into aId
                $clusters[$aId] = array_merge($clusters[$aId], $clusters[$bId]);
                unset($clusters[$bId]);
                $merged = true;
            }
        }

        // Assign a ULID to each cluster and batch-update nodes
        $nodeToCluster = [];
        foreach ($clusters as $clusterNodeIds) {
            $clusterId = (string) Str::ulid();
            foreach ($clusterNodeIds as $nodeId) {
                $nodeToCluster[$nodeId] = $clusterId;
            }
        }

        foreach ($nodes as $node) {
            $clusterId = $nodeToCluster[$node->id] ?? null;
            if ($clusterId !== null && $node->cluster_id !== $clusterId) {
                ContentGraphNode::where('id', $node->id)->update(['cluster_id' => $clusterId]);
            }
        }

        return count($clusters);
    }

    /**
     * Compute clusters for all spaces that have at least one graph node.
     * Called by the scheduler every 6 hours.
     */
    public function computeAllClusters(): void
    {
        $spaceIds = ContentGraphNode::query()
            ->select('space_id')
            ->distinct()
            ->pluck('space_id');

        foreach ($spaceIds as $spaceId) {
            $this->computeClusters($spaceId);
        }
    }

    /**
     * Compute average-linkage Jaccard similarity between two clusters.
     *
     * @param  list<string>  $aIds
     * @param  list<string>  $bIds
     * @param  array<string, array<int, string>>  $labelSets
     */
    private function clusterJaccard(array $aIds, array $bIds, array $labelSets): float
    {
        $total = 0.0;
        $count = 0;

        foreach ($aIds as $aId) {
            foreach ($bIds as $bId) {
                $total += $this->jaccard(
                    $labelSets[$aId] ?? [],
                    $labelSets[$bId] ?? []
                );
                $count++;
            }
        }

        return $count > 0 ? $total / $count : 0.0;
    }

    /**
     * Jaccard similarity between two label arrays.
     *
     * @param  array<int, string>  $a
     * @param  array<int, string>  $b
     */
    private function jaccard(array $a, array $b): float
    {
        if ($a === [] && $b === []) {
            return 0.0;
        }

        $intersection = count(array_intersect($a, $b));
        $union = count(array_unique(array_merge($a, $b)));

        return $union > 0 ? $intersection / $union : 0.0;
    }
}
