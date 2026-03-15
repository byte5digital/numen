<?php

namespace App\Jobs;

use App\Models\Content;
use App\Services\Graph\GraphIndexingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Queued job that indexes (or re-indexes) a Content record into the knowledge graph.
 *
 * Dispatched on the 'graph' queue. Failures are caught and logged without
 * re-throwing so a single bad record never blocks the queue.
 */
class UpdateKnowledgeGraphJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 60;

    public function __construct(
        public readonly Content $content,
    ) {
        $this->onQueue(config('numen.graph.queue', 'graph'));
    }

    public function handle(GraphIndexingService $graphIndexingService): void
    {
        if (! config('numen.graph.enabled', true)) {
            Log::debug('UpdateKnowledgeGraphJob: graph indexing disabled, skipping', [
                'content_id' => $this->content->id,
            ]);

            return;
        }

        try {
            $node = $graphIndexingService->indexContent($this->content);

            Log::info('UpdateKnowledgeGraphJob: completed', [
                'content_id' => $this->content->id,
                'node_id' => $node->id,
            ]);
        } catch (\Throwable $e) {
            // Log but do NOT re-throw — graph indexing failures must never break content publishing
            Log::error('UpdateKnowledgeGraphJob: indexing failed', [
                'content_id' => $this->content->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}
