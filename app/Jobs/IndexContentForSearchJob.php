<?php

namespace App\Jobs;

use App\Models\Content;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Dispatches both Meilisearch indexing (via Scout) and embedding generation
 * when content is published.
 */
class IndexContentForSearchJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        private readonly string $contentId,
    ) {
        $this->onQueue('search');
    }

    public function handle(): void
    {
        $content = Content::with(['currentVersion'])->find($this->contentId);

        if (! $content || $content->status !== 'published') {
            Log::info('IndexContentForSearchJob: content not published, skipping', [
                'content_id' => $this->contentId,
            ]);

            return;
        }

        $version = $content->currentVersion;

        // Tier 1: Scout/Meilisearch (sync via model observer)
        try {
            $content->searchable();
            Log::info('IndexContentForSearchJob: indexed in Meilisearch', ['content_id' => $this->contentId]);
        } catch (\Throwable $e) {
            Log::warning('IndexContentForSearchJob: Meilisearch indexing failed', [
                'content_id' => $this->contentId,
                'error' => $e->getMessage(),
            ]);
        }

        // Tier 2: Generate embeddings
        if ($version) {
            GenerateEmbeddingsJob::dispatch($this->contentId, $version->id);
        }
    }
}
