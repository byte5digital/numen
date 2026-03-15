<?php

namespace App\Console\Commands;

use App\Models\ContentGraphEdge;
use App\Models\ContentGraphNode;
use App\Models\Space;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Display knowledge graph statistics.
 *
 * Usage:
 *   php artisan graph:stats
 *   php artisan graph:stats --space=default
 */
class GraphStatsCommand extends Command
{
    protected $signature = 'graph:stats
        {--space= : Stats for a specific space (slug)}';

    protected $description = 'Show knowledge graph statistics (nodes, edges, clusters)';

    public function handle(): int
    {
        $spaceSlug = $this->option('space');
        $spaceId = null;

        if ($spaceSlug) {
            $space = Space::where('slug', $spaceSlug)->first();

            if (! $space) {
                $this->error("Space '{$spaceSlug}' not found.");

                return self::FAILURE;
            }

            $spaceId = $space->id;
            $this->info("Knowledge Graph Stats — space: {$spaceSlug}");
        } else {
            $this->info('Knowledge Graph Stats — all spaces');
        }

        $this->newLine();

        // Node count
        $nodeQuery = ContentGraphNode::query();
        if ($spaceId) {
            $nodeQuery->where('space_id', $spaceId);
        }
        $totalNodes = $nodeQuery->count();

        // Edge count
        $edgeQuery = ContentGraphEdge::query();
        if ($spaceId) {
            $edgeQuery->where('space_id', $spaceId);
        }
        $totalEdges = $edgeQuery->count();

        // Edges by type
        $edgesByTypeQuery = ContentGraphEdge::query()
            ->select('edge_type', DB::raw('count(*) as count'));
        if ($spaceId) {
            $edgesByTypeQuery->where('space_id', $spaceId);
        }
        $edgesByType = $edgesByTypeQuery
            ->groupBy('edge_type')
            ->orderByDesc('count')
            ->get();

        // Cluster count
        $clusterQuery = ContentGraphNode::query()
            ->whereNotNull('cluster_id')
            ->distinct();
        if ($spaceId) {
            $clusterQuery->where('space_id', $spaceId);
        }
        $clusterCount = $clusterQuery->count('cluster_id');

        // Avg edges per node
        $avgEdges = $totalNodes > 0 ? round($totalEdges / $totalNodes, 2) : 0.0;

        // Summary table
        $this->table(
            ['Metric', 'Value'],
            [
                ['Total Nodes', number_format($totalNodes)],
                ['Total Edges', number_format($totalEdges)],
                ['Distinct Clusters', number_format($clusterCount)],
                ['Avg Edges per Node', $avgEdges],
            ]
        );

        if ($edgesByType->isNotEmpty()) {
            $this->newLine();
            $this->line('Edges by Type:');
            $this->table(
                ['Edge Type', 'Count'],
                $edgesByType->map(fn ($row) => [
                    $row->edge_type,
                    number_format((int) data_get($row, 'count', 0)),
                ])->toArray()
            );
        }

        return self::SUCCESS;
    }
}
