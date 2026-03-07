<?php

namespace App\Jobs;

use App\Models\Content;
use App\Models\ContentEmbedding;
use App\Services\Search\ContentChunker;
use App\Services\Search\EmbeddingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Generates and stores vector embeddings for a published content item.
 *
 * Safety:
 * - Verifies content is still published before embedding (race condition guard)
 * - NEVER embeds unpublished content
 * - Deletes stale embeddings before inserting new ones
 */
class GenerateEmbeddingsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(
        private readonly string $contentId,
        public readonly string $targetVersionId,
    ) {
        $this->onQueue('search');
    }

    public function handle(ContentChunker $chunker, EmbeddingService $embeddings): void
    {
        // 1. Load content (published only)
        $content = Content::with(['currentVersion'])->find($this->contentId);

        if (! $content) {
            Log::warning('GenerateEmbeddingsJob: content not found', ['content_id' => $this->contentId]);

            return;
        }

        // 2. Race condition guard — must still be published
        if ($content->status !== 'published' || $content->published_at === null) {
            Log::info('GenerateEmbeddingsJob: content no longer published, skipping', [
                'content_id' => $this->contentId,
            ]);

            return;
        }

        // 3. Get the specific version (prefer targetVersionId, fall back to currentVersion)
        $version = $content->versions()->where('id', $this->targetVersionId)->first()
            ?? $content->currentVersion;

        if (! $version) {
            Log::warning('GenerateEmbeddingsJob: no current version', ['content_id' => $this->contentId]);

            return;
        }

        // 4. Chunk the content
        $chunks = $chunker->chunk($content, $version);

        if (empty($chunks)) {
            Log::info('GenerateEmbeddingsJob: no chunks produced', ['content_id' => $this->contentId]);

            return;
        }

        // 5. Generate embeddings in batch
        $texts = array_map(fn ($chunk) => $chunk->text, $chunks);
        $vectors = $embeddings->embedBatch($texts);

        // 6. Delete old embeddings for this content_id
        ContentEmbedding::where('content_id', $this->contentId)->delete();

        // 7. Insert new embeddings
        $model = $embeddings->getModel();
        $locale = $content->locale;
        $spaceId = $content->space_id;

        foreach ($chunks as $i => $chunk) {
            $vector = $vectors[$i] ?? null;

            if (! $vector) {
                continue;
            }

            // Encode vector for storage (pgvector uses [f1,f2,...] format; fallback stores as JSON)
            $embeddingStr = '['.implode(',', $vector).']';

            ContentEmbedding::create([
                'id' => Str::ulid()->toBase32(),
                'content_id' => $this->contentId,
                'content_version_id' => $version->id,
                'chunk_index' => $chunk->index,
                'chunk_type' => $chunk->type,
                'chunk_text' => $chunk->text,
                'embedding' => $embeddingStr,
                'embedding_model' => $model,
                'token_count' => $chunk->tokenCount,
                'metadata' => $chunk->metadata,
                'space_id' => $spaceId,
                'locale' => $locale,
            ]);
        }

        Log::info('GenerateEmbeddingsJob: completed', [
            'content_id' => $this->contentId,
            'chunks' => count($chunks),
        ]);
    }
}
