<?php

namespace App\Console\Commands;

use App\Models\ContentGraphEdge;
use App\Models\ContentGraphNode;
use Illuminate\Console\Command;

/**
 * Remove orphaned graph nodes and dangling edges.
 *
 * Orphaned nodes: content_id no longer exists or content is not published.
 * Dangling edges: source_id or target_id points to a deleted node.
 *
 * Usage:
 *   php artisan graph:prune
 *
 * Registered in the scheduler to run weekly.
 */
class GraphPruneCommand extends Command
{
    protected $signature = 'graph:prune';

    protected $description = 'Remove orphaned graph nodes and edges pointing to deleted nodes';

    public function handle(): int
    {
        $this->info('Pruning knowledge graph...');

        // --- Step 1: orphaned nodes ---
        // A node is orphaned if its content no longer exists OR is not published.
        $orphanedNodeIds = ContentGraphNode::query()
            ->whereDoesntHave('content', function ($q): void {
                $q->where('status', 'published')->whereNotNull('published_at');
            })
            ->pluck('id');

        $deletedNodes = 0;
        if ($orphanedNodeIds->isNotEmpty()) {
            // Delete in chunks to avoid giant IN() clauses
            $orphanedNodeIds->chunk(200)->each(function ($chunk) use (&$deletedNodes): void {
                $deletedNodes += ContentGraphNode::whereIn('id', $chunk)->delete();
            });
        }

        $this->line("  Orphaned nodes removed: {$deletedNodes}");

        // --- Step 2: dangling edges ---
        // Edges whose source or target no longer has a node row.
        $existingNodeIds = ContentGraphNode::query()->pluck('id');

        $danglingEdges = ContentGraphEdge::query()
            ->where(function ($q) use ($existingNodeIds): void {
                $q->whereNotIn('source_id', $existingNodeIds)
                    ->orWhereNotIn('target_id', $existingNodeIds);
            })
            ->count();

        if ($danglingEdges > 0) {
            ContentGraphEdge::query()
                ->where(function ($q) use ($existingNodeIds): void {
                    $q->whereNotIn('source_id', $existingNodeIds)
                        ->orWhereNotIn('target_id', $existingNodeIds);
                })
                ->delete();
        }

        $this->line("  Dangling edges removed: {$danglingEdges}");
        $this->info('✓ Graph prune complete.');

        return self::SUCCESS;
    }
}
