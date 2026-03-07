<?php

namespace App\Console\Commands;

use App\Models\Content;
use App\Models\ContentEmbedding;
use Illuminate\Console\Command;

/**
 * Audits search indexes for stale/orphaned entries.
 * Removes embeddings whose content is no longer published.
 * Run daily via scheduler.
 */
class SearchAudit extends Command
{
    protected $signature = 'numen:search:audit {--dry-run : Preview without deleting}';

    protected $description = 'Audit and clean up stale search index entries';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $this->info('Auditing search indexes...');

        // Find embeddings for unpublished/deleted content
        $staleIds = ContentEmbedding::whereNotIn('content_id', function ($query): void {
            $query->select('id')
                ->from('contents')
                ->where('status', 'published')
                ->whereNotNull('published_at');
        })->pluck('content_id')->unique();

        if ($staleIds->isEmpty()) {
            $this->info('No stale embeddings found. All clean!');

            return self::SUCCESS;
        }

        $this->warn("Found {$staleIds->count()} content items with stale embeddings.");

        if ($dryRun) {
            $this->info('[dry-run] Would delete embeddings for: '.$staleIds->implode(', '));
        } else {
            $deleted = ContentEmbedding::whereIn('content_id', $staleIds)->delete();
            $this->info("Deleted {$deleted} stale embeddings.");
        }

        return self::SUCCESS;
    }
}
