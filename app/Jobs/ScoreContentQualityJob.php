<?php

namespace App\Jobs;

use App\Models\Content;
use App\Models\ContentQualityConfig;
use App\Services\Quality\ContentQualityService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ScoreContentQualityJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /** Maximum job attempts before failure. */
    public int $tries = 3;

    /** Timeout in seconds. */
    public int $timeout = 120;

    public function __construct(
        public readonly string $contentId,
        public readonly ?string $configId = null,
    ) {
        $this->onQueue('quality');
    }

    public function handle(ContentQualityService $service): void
    {
        $content = Content::findOrFail($this->contentId);

        $config = $this->configId
            ? ContentQualityConfig::find($this->configId)
            : null;

        $service->score($content, $config);
    }
}
