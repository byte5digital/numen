<?php

namespace App\Console\Commands\Pipeline;

use App\Models\ContentBrief;
use App\Models\ContentPipeline;
use App\Pipelines\PipelineExecutor;
use Illuminate\Console\Command;

class PipelineRunCommand extends Command
{
    protected $signature = 'numen:pipeline:run
        {--brief-id= : Brief ID to run the pipeline for (required)}
        {--pipeline-id= : Pipeline ID to use (defaults to active pipeline for the brief space)}';

    protected $description = 'Trigger a pipeline run for a given brief';

    public function handle(PipelineExecutor $executor): int
    {
        $briefId = $this->option('brief-id');

        if (! $briefId && $this->input->isInteractive()) {
            $briefId = $this->ask('Brief ID');
        }

        if (! $briefId) {
            $this->error('A brief ID is required (--brief-id).');

            return self::FAILURE;
        }

        $brief = ContentBrief::find($briefId);

        if (! $brief) {
            $this->error("Brief not found: {$briefId}");

            return self::FAILURE;
        }

        $pipelineId = $this->option('pipeline-id');
        $pipeline = $pipelineId
            ? ContentPipeline::find($pipelineId)
            : ContentPipeline::where('space_id', $brief->space_id)->where('is_active', true)->first();

        if (! $pipeline) {
            $this->error('No active pipeline found for this brief. Provide --pipeline-id.');

            return self::FAILURE;
        }

        if (in_array($brief->status, ['processing', 'completed'])) {
            if (! $this->confirm("Brief is already '{$brief->status}'. Run again?", false)) {
                $this->info('Aborted.');

                return self::SUCCESS;
            }
        }

        $existingContent = $brief->targetContent;

        $run = $executor->start($brief, $pipeline, $existingContent);

        $this->info('Pipeline run started.');
        $this->table(
            ['Field', 'Value'],
            [
                ['Brief ID', $brief->id],
                ['Brief Title', $brief->title],
                ['Run ID', $run->id],
                ['Pipeline', $pipeline->name],
                ['Status', $run->status],
                ['Stage', $run->current_stage ?? '—'],
            ]
        );

        return self::SUCCESS;
    }
}
