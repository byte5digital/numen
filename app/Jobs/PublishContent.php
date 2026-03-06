<?php

namespace App\Jobs;

use App\Events\Content\ContentPublished;
use App\Models\PipelineRun;
use App\Pipelines\PipelineExecutor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class PublishContent implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public PipelineRun $run,
    ) {
        $this->onQueue('ai-pipeline');
    }

    public function handle(PipelineExecutor $executor): void
    {
        $content = $this->run->content;

        if (!$content) {
            Log::error("PublishContent: No content found for pipeline run", ['run_id' => $this->run->id]);
            $this->run->markFailed('No content to publish');
            return;
        }

        // Check quality threshold
        $qualityScore = $this->run->context['last_stage_score'] ?? 0;
        $threshold = config('numen.pipeline.auto_publish_threshold', 80);

        if ($qualityScore < $threshold) {
            Log::info("Content quality below threshold, pausing for review", [
                'content_id' => $content->id,
                'score'      => $qualityScore,
                'threshold'  => $threshold,
            ]);
            $this->run->update(['status' => 'paused_for_review']);
            $this->run->brief?->update(['status' => 'in_review']);
            return;
        }

        $content->publish();

        event(new ContentPublished($content));

        $executor->advance($this->run, [
            'stage'   => 'auto_publish',
            'success' => true,
            'summary' => "Published content: {$content->currentVersion?->title}",
        ]);

        Log::info("Content published", [
            'content_id' => $content->id,
            'slug'       => $content->slug,
        ]);
    }
}
