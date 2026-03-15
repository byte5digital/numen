<?php

namespace App\Console\Commands\Brief;

use App\Models\ContentBrief;
use App\Models\ContentPipeline;
use App\Models\Persona;
use App\Models\Space;
use App\Pipelines\PipelineExecutor;
use Illuminate\Console\Command;

class BriefCreateCommand extends Command
{
    protected $signature = 'numen:brief:create
        {--title= : Brief title (required)}
        {--type=blog_post : Content type slug}
        {--persona= : Persona ID or slug to use for generation}
        {--space-id= : Space ID (defaults to first space)}
        {--pipeline-id= : Pipeline to run (defaults to active pipeline for space)}
        {--description= : Optional brief description}
        {--keywords=* : Target keywords}
        {--priority=normal : Priority (low, normal, high, urgent)}
        {--no-run : Create the brief but do not trigger a pipeline run}';

    protected $description = 'Create a content brief and optionally trigger the pipeline';

    public function handle(PipelineExecutor $executor): int
    {
        $title = $this->option('title');

        if (! $title && $this->input->isInteractive()) {
            $title = $this->ask('Brief title');
        }

        if (! $title) {
            $this->error('A title is required.');

            return self::FAILURE;
        }

        $spaceId = $this->option('space-id');

        if (! $spaceId) {
            $space = Space::first();

            if (! $space) {
                $this->error('No space found. Please provide --space-id.');

                return self::FAILURE;
            }

            $spaceId = $space->id;
        }

        $personaId = null;

        if ($personaOption = $this->option('persona')) {
            $persona = Persona::where('id', $personaOption)
                ->orWhere('slug', $personaOption)
                ->first();

            if (! $persona) {
                $this->warn("Persona '{$personaOption}' not found; continuing without persona.");
            } else {
                $personaId = $persona->id;
            }
        }

        $brief = ContentBrief::create([
            'space_id' => $spaceId,
            'title' => $title,
            'description' => $this->option('description'),
            'content_type_slug' => $this->option('type'),
            'target_keywords' => $this->option('keywords') ?: [],
            'target_locale' => 'en',
            'persona_id' => $personaId,
            'source' => 'cli',
            'priority' => $this->option('priority') ?? 'normal',
            'status' => 'pending',
        ]);

        $this->info("Brief created: {$brief->id}");

        if ($this->option('no-run')) {
            $this->line('Pipeline run skipped (--no-run).');

            return self::SUCCESS;
        }

        $pipelineId = $this->option('pipeline-id');
        $pipeline = $pipelineId
            ? ContentPipeline::find($pipelineId)
            : ContentPipeline::where('space_id', $spaceId)->where('is_active', true)->first();

        if (! $pipeline) {
            $this->warn('No active pipeline found. Brief saved without triggering a run.');
            $this->line("Brief ID: {$brief->id}");

            return self::SUCCESS;
        }

        $run = $executor->start($brief, $pipeline);

        $this->info("Pipeline started: {$run->id}");
        $this->table(
            ['Field', 'Value'],
            [
                ['Brief ID', $brief->id],
                ['Run ID', $run->id],
                ['Pipeline', $pipeline->name],
                ['Status', $run->status],
            ]
        );

        return self::SUCCESS;
    }
}
