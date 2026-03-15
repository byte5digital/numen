<?php

namespace App\Console\Commands;

use App\Jobs\UpdateKnowledgeGraphJob;
use App\Models\Content;
use App\Models\Space;
use Illuminate\Console\Command;

/**
 * Bulk re-index published content into the knowledge graph.
 *
 * Usage:
 *   php artisan graph:reindex --all
 *   php artisan graph:reindex --space=default
 */
class GraphReindexCommand extends Command
{
    protected $signature = 'graph:reindex
        {--space= : Reindex a specific space (slug)}
        {--all : Reindex all spaces}';

    protected $description = 'Dispatch UpdateKnowledgeGraphJob for published content (initial population or recovery)';

    public function handle(): int
    {
        $spaceSlug = $this->option('space');
        $all = (bool) $this->option('all');

        if (! $spaceSlug && ! $all) {
            $this->error('Please provide --space=<slug> or --all.');

            return self::FAILURE;
        }

        $query = Content::published()->with('space');

        if ($spaceSlug) {
            $space = Space::where('slug', $spaceSlug)->first();

            if (! $space) {
                $this->error("Space '{$spaceSlug}' not found.");

                return self::FAILURE;
            }

            $query->where('space_id', $space->id);
        }

        $total = $query->count();

        if ($total === 0) {
            $this->info('No published content found to reindex.');

            return self::SUCCESS;
        }

        $this->info("Dispatching knowledge graph jobs for {$total} content items...");

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $dispatched = 0;
        $query->chunk(50, function ($contents) use ($bar, &$dispatched): void {
            foreach ($contents as $content) {
                UpdateKnowledgeGraphJob::dispatch($content)->onQueue('graph');
                $dispatched++;
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine();
        $this->info("✓ Dispatched {$dispatched} graph reindex jobs.");

        return self::SUCCESS;
    }
}
