<?php

namespace App\Listeners;

use App\Events\Content\ContentPublished;
use App\Jobs\ScoreContentQualityJob;
use App\Models\ContentQualityConfig;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Automatically triggers quality scoring when content is published,
 * if auto_score_on_publish is enabled in the space's quality config.
 */
class AutoScoreOnPublishListener implements ShouldQueue
{
    public function handle(ContentPublished $event): void
    {
        $content = $event->content;

        $config = ContentQualityConfig::where('space_id', $content->space_id)->first();

        // Score if: no config (default on) OR config explicitly enables it
        if ($config === null || $config->auto_score_on_publish) {
            ScoreContentQualityJob::dispatch($content);
        }
    }
}
