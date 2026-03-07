<?php

namespace App\Listeners;

use App\Events\Content\ContentUnpublished;
use App\Models\ContentEmbedding;
use Illuminate\Support\Facades\Log;

class RemoveFromSearchIndex
{
    public function handle(ContentUnpublished $event): void
    {
        $content = $event->content;

        // Remove from Meilisearch (via Scout)
        try {
            $content->unsearchable();
        } catch (\Throwable $e) {
            Log::warning('RemoveFromSearchIndex: failed to remove from Meilisearch', [
                'content_id' => $content->id,
                'error' => $e->getMessage(),
            ]);
        }

        // Remove embeddings
        ContentEmbedding::where('content_id', $content->id)->delete();

        Log::info('RemoveFromSearchIndex: removed from all search indexes', [
            'content_id' => $content->id,
        ]);
    }
}
