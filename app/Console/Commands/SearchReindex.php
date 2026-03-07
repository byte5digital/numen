<?php

namespace App\Console\Commands;

use App\Jobs\GenerateEmbeddingsJob;
use App\Jobs\IndexContentForSearchJob;
use App\Models\Content;
use App\Models\ContentEmbedding;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Bulk re-index all published content into search indexes.
 *
 * Usage:
 *   php artisan numen:search:reindex
 *   php artisan numen:search:reindex --space=default
 *   php artisan numen:search:reindex --fresh
 *   php artisan numen:search:reindex --tier=instant
 *   php artisan numen:search:reindex --tier=semantic
 */
class SearchReindex extends Command
{
    protected $signature = 'numen:search:reindex
        {--space= : Only reindex a specific space slug}
        {--fresh : Drop all embeddings first}
        {--tier= : Only reindex a specific tier (instant|semantic|both)}
        {--sync : Run synchronously instead of queuing}';

    protected $description = 'Re-index all published content into Meilisearch and/or pgvector embeddings';

    public function handle(): int
    {
        $spaceName = $this->option('space');
        $fresh = (bool) $this->option('fresh');
        $tier = $this->option('tier') ?? 'both';
        $sync = (bool) $this->option('sync');

        if ($fresh) {
            $this->warn('Dropping all existing embeddings...');
            ContentEmbedding::query()->delete();
            $this->line('Done.');
        }

        $query = Content::published()->with(['currentVersion']);

        if ($spaceName) {
            $query->whereHas('space', fn ($q) => $q->where('slug', $spaceName));
        }

        $total = $query->count();
        $this->info("Re-indexing {$total} published content items (tier: {$tier})...");

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $count = 0;
        $query->chunk(50, function ($contents) use ($tier, $sync, $bar, &$count): void {
            foreach ($contents as $content) {
                if ($tier === 'both' || $tier === 'instant') {
                    if ($sync) {
                        try {
                            $content->searchable();
                        } catch (\Throwable $e) {
                            $this->warn("Meilisearch failed for {$content->id}: ".$e->getMessage());
                        }
                    } else {
                        IndexContentForSearchJob::dispatch($content->id)->onQueue('search');
                    }
                }

                if (($tier === 'both' || $tier === 'semantic') && $content->currentVersion) {
                    if ($sync) {
                        app(GenerateEmbeddingsJob::class)->handle(
                            app(\App\Services\Search\ContentChunker::class),
                            app(\App\Services\Search\EmbeddingService::class),
                        );
                    } else {
                        GenerateEmbeddingsJob::dispatch($content->id, $content->currentVersion->id)
                            ->onQueue('search');
                    }
                }

                $count++;
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine();

        Cache::put('search:last_reindex', now()->toISOString());

        $this->info("Re-index complete. {$count} items dispatched.");

        return self::SUCCESS;
    }
}
