<?php

namespace App\Jobs;

use App\Models\Content;
use App\Models\ContentFingerprint;
use App\Services\Competitor\DifferentiationAnalysisService;
use App\Services\Competitor\SimilarContentFinder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class AnalyzeContentDifferentiationJob implements ShouldQueue
{
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $backoff = 120;

    public function __construct(
        public readonly string $contentId,
    ) {
        $this->onQueue('competitor');
    }

    public function handle(
        DifferentiationAnalysisService $analysisService,
        SimilarContentFinder $finder,
    ): void {
        $content = Content::find($this->contentId);

        if ($content === null) {
            Log::warning('AnalyzeContentDifferentiationJob: content not found', ['content_id' => $this->contentId]);

            return;
        }

        $fingerprint = ContentFingerprint::query()
            ->where('fingerprintable_type', Content::class)
            ->where('fingerprintable_id', $content->id)
            ->first();

        if ($fingerprint === null) {
            Log::warning('AnalyzeContentDifferentiationJob: no fingerprint for content', ['content_id' => $this->contentId]);

            return;
        }

        $similar = $finder->findSimilar($fingerprint, threshold: 0.3, limit: 10);

        if ($similar->isEmpty()) {
            Log::info('AnalyzeContentDifferentiationJob: no similar competitor content found', ['content_id' => $this->contentId]);

            return;
        }

        $analyses = $analysisService->analyze($content, $similar);

        Log::info('AnalyzeContentDifferentiationJob: complete', [
            'content_id' => $this->contentId,
            'competitor_count' => $similar->count(),
            'analyses_stored' => $analyses->count(),
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('AnalyzeContentDifferentiationJob: job failed permanently', [
            'content_id' => $this->contentId,
            'error' => $exception->getMessage(),
        ]);
    }
}
