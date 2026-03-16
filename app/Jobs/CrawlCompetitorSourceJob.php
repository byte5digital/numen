<?php

namespace App\Jobs;

use App\Models\CompetitorContentItem;
use App\Models\CompetitorSource;
use App\Services\Competitor\CrawlerService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CrawlCompetitorSourceJob implements ShouldQueue
{
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(
        public readonly CompetitorSource $source
    ) {
        $this->onQueue('competitor');
    }

    public function handle(CrawlerService $crawlerService): void
    {
        Log::info('CrawlCompetitorSourceJob: starting', ['source_id' => $this->source->id]);

        $items = $crawlerService->crawlSource($this->source);

        if ($items->isEmpty()) {
            Log::info('CrawlCompetitorSourceJob: no new items', ['source_id' => $this->source->id]);

            return;
        }

        $saved = 0;
        foreach ($items as $item) {
            try {
                /** @var CompetitorContentItem $item */
                $item->save();
                $saved++;

                // Dispatch fingerprinting after each new item is persisted
                FingerprintContentJob::dispatch($item);
            } catch (\Throwable $e) {
                Log::warning('CrawlCompetitorSourceJob: failed to save item', [
                    'source_id' => $this->source->id,
                    'url' => $item->external_url,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('CrawlCompetitorSourceJob: complete', [
            'source_id' => $this->source->id,
            'saved' => $saved,
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('CrawlCompetitorSourceJob: job failed permanently', [
            'source_id' => $this->source->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
