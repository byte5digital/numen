<?php

namespace App\Pipelines;

use App\Events\Pipeline\PipelineCompleted;
use App\Events\Pipeline\PipelineStageCompleted;
use App\Events\Pipeline\PipelineStarted;
use App\Jobs\GenerateImage;
use App\Jobs\RunAgentStage;
use App\Jobs\PublishContent;
use App\Models\Content;
use App\Models\ContentBrief;
use App\Models\ContentPipeline;
use App\Models\PipelineRun;
use Illuminate\Support\Facades\Log;

class PipelineExecutor
{
    /**
     * Start a new pipeline run from a brief.
     */
    public function start(ContentBrief $brief, ContentPipeline $pipeline, ?Content $existingContent = null): PipelineRun
    {
        $context = [
            'brief' => $brief->toArray(),
            'space_id' => $brief->space_id,
        ];

        // If updating existing content, include it in context for the AI agents
        if ($existingContent) {
            $version = $existingContent->currentVersion;
            $context['update_mode'] = true;
            $context['existing_content'] = [
                'title'   => $version?->title,
                'excerpt' => $version?->excerpt,
                'body'    => $version?->body,
                'seo_data' => $version?->seo_data,
            ];
        }

        $run = PipelineRun::create([
            'pipeline_id'      => $pipeline->id,
            'content_brief_id' => $brief->id,
            'content_id'       => $existingContent?->id,
            'status'           => 'running',
            'current_stage'    => $pipeline->stages[0]['name'] ?? null,
            'stage_results'    => [],
            'context'          => $context,
            'started_at' => now(),
        ]);

        $brief->update(['status' => 'processing']);

        event(new PipelineStarted($run));

        $this->dispatchStage($run, $pipeline->stages[0]);

        return $run;
    }

    /**
     * Advance to the next stage after the current one completes.
     */
    public function advance(PipelineRun $run, array $stageResult): void
    {
        $pipeline = $run->pipeline;
        $currentStageName = $run->current_stage;

        // Save stage result
        $run->addStageResult($currentStageName, $stageResult);

        event(new PipelineStageCompleted($run, $currentStageName, $stageResult));

        // Get next stage
        $nextStage = $pipeline->getStageAfter($currentStageName);

        if (!$nextStage) {
            // Pipeline complete
            $run->markCompleted();
            $run->brief?->update(['status' => 'completed']);
            event(new PipelineCompleted($run));
            Log::info("Pipeline run completed", ['run_id' => $run->id]);
            return;
        }

        $run->update(['current_stage' => $nextStage['name']]);

        $this->dispatchStage($run, $nextStage);
    }

    /**
     * Dispatch the appropriate job for a pipeline stage.
     */
    private function dispatchStage(PipelineRun $run, array $stage): void
    {
        $queue = match ($stage['type']) {
            'ai_generate'   => config('numen.queues.generation'),
            'ai_transform'  => config('numen.queues.transform'),
            'ai_review'     => config('numen.queues.review'),
            'ai_illustrate' => config('numen.queues.generation'),
            'auto_publish'  => config('numen.queues.publishing'),
            'human_gate'    => null, // No job — pauses for human
            default         => 'default',
        };

        if ($stage['type'] === 'human_gate') {
            $run->update(['status' => 'paused_for_review']);
            // TODO: Notify humans (email, webhook, OpenClaw message)
            Log::info("Pipeline paused for human review", ['run_id' => $run->id, 'stage' => $stage['name']]);
            return;
        }

        if ($stage['type'] === 'auto_publish') {
            PublishContent::dispatch($run)->onQueue($queue);
            return;
        }

        if ($stage['type'] === 'ai_illustrate') {
            GenerateImage::dispatch($run, $stage)->onQueue($queue);
            return;
        }

        RunAgentStage::dispatch($run, $stage)->onQueue($queue);
    }
}
