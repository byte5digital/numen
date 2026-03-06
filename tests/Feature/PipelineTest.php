<?php

namespace Tests\Feature;

use App\Models\ContentBrief;
use App\Models\ContentPipeline;
use App\Models\PipelineRun;
use App\Models\Space;
use App\Pipelines\PipelineExecutor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class PipelineTest extends TestCase
{
    use RefreshDatabase;

    private Space $space;
    private PipelineExecutor $executor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->space    = Space::factory()->create();
        $this->executor = app(PipelineExecutor::class);
    }

    // --- Pipeline creation ---

    public function test_start_creates_pipeline_run(): void
    {
        Bus::fake();

        $pipeline = ContentPipeline::factory()->create(['space_id' => $this->space->id]);
        $brief    = ContentBrief::factory()->create(['space_id' => $this->space->id]);

        $run = $this->executor->start($brief, $pipeline);

        $this->assertInstanceOf(PipelineRun::class, $run);
        $this->assertDatabaseHas('pipeline_runs', [
            'id'          => $run->id,
            'pipeline_id' => $pipeline->id,
            'status'      => 'running',
        ]);
    }

    public function test_start_sets_first_stage(): void
    {
        Bus::fake();

        $pipeline = ContentPipeline::factory()->create([
            'space_id' => $this->space->id,
            'stages'   => [
                ['name' => 'generate', 'type' => 'ai_generate'],
                ['name' => 'publish',  'type' => 'auto_publish'],
            ],
        ]);
        $brief = ContentBrief::factory()->create(['space_id' => $this->space->id]);

        $run = $this->executor->start($brief, $pipeline);

        $this->assertEquals('generate', $run->current_stage);
    }

    public function test_start_marks_brief_as_processing(): void
    {
        Bus::fake();

        $pipeline = ContentPipeline::factory()->create(['space_id' => $this->space->id]);
        $brief    = ContentBrief::factory()->create(['space_id' => $this->space->id]);

        $this->executor->start($brief, $pipeline);

        $this->assertEquals('processing', $brief->fresh()->status);
    }

    public function test_start_dispatches_run_agent_stage_job(): void
    {
        Bus::fake();

        $pipeline = ContentPipeline::factory()->create([
            'space_id' => $this->space->id,
            'stages'   => [
                ['name' => 'generate', 'type' => 'ai_generate', 'persona_role' => 'creator'],
            ],
        ]);
        $brief = ContentBrief::factory()->create(['space_id' => $this->space->id]);

        $this->executor->start($brief, $pipeline);

        Bus::assertDispatched(\App\Jobs\RunAgentStage::class);
    }

    public function test_ai_illustrate_stage_dispatches_generate_image_job(): void
    {
        Bus::fake();

        $pipeline = ContentPipeline::factory()->create([
            'space_id' => $this->space->id,
            'stages'   => [
                ['name' => 'illustrate', 'type' => 'ai_illustrate'],
            ],
        ]);
        $brief = ContentBrief::factory()->create(['space_id' => $this->space->id]);

        $this->executor->start($brief, $pipeline);

        Bus::assertDispatched(\App\Jobs\GenerateImage::class);
    }

    // --- Stage advancement ---

    public function test_advance_saves_stage_result(): void
    {
        Bus::fake();

        $pipeline = ContentPipeline::factory()->create([
            'space_id' => $this->space->id,
            'stages'   => [
                ['name' => 'generate', 'type' => 'ai_generate'],
                ['name' => 'publish',  'type' => 'auto_publish'],
            ],
        ]);
        $brief = ContentBrief::factory()->create(['space_id' => $this->space->id]);
        $run   = PipelineRun::create([
            'pipeline_id'      => $pipeline->id,
            'content_brief_id' => $brief->id,
            'status'           => 'running',
            'current_stage'    => 'generate',
            'started_at'       => now(),
        ]);

        $this->executor->advance($run, ['success' => true, 'word_count' => 800]);

        $freshRun = $run->fresh();
        $this->assertEquals(800, $freshRun->stage_results['generate']['word_count']);
        $this->assertEquals('publish', $freshRun->current_stage);
    }

    public function test_advance_completes_pipeline_after_last_stage(): void
    {
        Bus::fake();

        $pipeline = ContentPipeline::factory()->create([
            'space_id' => $this->space->id,
            'stages'   => [
                ['name' => 'generate', 'type' => 'ai_generate'],
            ],
        ]);
        $brief = ContentBrief::factory()->create(['space_id' => $this->space->id]);
        $run   = PipelineRun::create([
            'pipeline_id'      => $pipeline->id,
            'content_brief_id' => $brief->id,
            'status'           => 'running',
            'current_stage'    => 'generate',
            'started_at'       => now(),
        ]);

        $this->executor->advance($run, ['success' => true]);

        $freshRun = $run->fresh();
        $this->assertEquals('completed', $freshRun->status);
        $this->assertNotNull($freshRun->completed_at);
    }

    // --- Review gate (human_gate) ---

    public function test_human_gate_pauses_pipeline_for_review(): void
    {
        Bus::fake();

        $pipeline = ContentPipeline::factory()->withHumanGate()->create(['space_id' => $this->space->id]);
        $brief    = ContentBrief::factory()->create(['space_id' => $this->space->id]);

        $run = PipelineRun::create([
            'pipeline_id'      => $pipeline->id,
            'content_brief_id' => $brief->id,
            'status'           => 'running',
            'current_stage'    => 'generate',
            'started_at'       => now(),
        ]);

        // Advance past generate → should hit human_gate (review)
        $this->executor->advance($run, ['success' => true]);

        $freshRun = $run->fresh();
        $this->assertEquals('paused_for_review', $freshRun->status);
    }

    public function test_human_gate_dispatches_no_job(): void
    {
        Bus::fake();

        $pipeline = ContentPipeline::factory()->withHumanGate()->create(['space_id' => $this->space->id]);
        $brief    = ContentBrief::factory()->create(['space_id' => $this->space->id]);

        $run = PipelineRun::create([
            'pipeline_id'      => $pipeline->id,
            'content_brief_id' => $brief->id,
            'status'           => 'running',
            'current_stage'    => 'generate',
            'started_at'       => now(),
        ]);

        $this->executor->advance($run, ['success' => true]);

        // After hitting human_gate, only the RunAgentStage from the initial advance should exist
        // but no additional RunAgentStage for the human_gate stage
        Bus::assertNotDispatched(\App\Jobs\PublishContent::class);
    }

    // --- Pipeline completion ---

    public function test_pipeline_completion_fires_event(): void
    {
        Event::fake();
        Bus::fake();

        $pipeline = ContentPipeline::factory()->create([
            'space_id' => $this->space->id,
            'stages'   => [['name' => 'generate', 'type' => 'ai_generate']],
        ]);
        $brief = ContentBrief::factory()->create(['space_id' => $this->space->id]);
        $run   = PipelineRun::create([
            'pipeline_id'      => $pipeline->id,
            'content_brief_id' => $brief->id,
            'status'           => 'running',
            'current_stage'    => 'generate',
            'started_at'       => now(),
        ]);

        $this->executor->advance($run, ['success' => true]);

        Event::assertDispatched(\App\Events\Pipeline\PipelineCompleted::class);
    }

    // --- Error handling ---

    public function test_mark_failed_sets_failed_status(): void
    {
        $pipeline = ContentPipeline::factory()->create(['space_id' => $this->space->id]);
        $brief    = ContentBrief::factory()->create(['space_id' => $this->space->id]);
        $run      = PipelineRun::create([
            'pipeline_id'      => $pipeline->id,
            'content_brief_id' => $brief->id,
            'status'           => 'running',
            'current_stage'    => 'generate',
            'started_at'       => now(),
        ]);

        $run->markFailed('LLM API error: all providers exhausted');

        $freshRun = $run->fresh();
        $this->assertEquals('failed', $freshRun->status);
        $this->assertNotNull($freshRun->completed_at);
        $this->assertStringContainsString('all providers exhausted', $freshRun->stage_results['failure_reason']);
    }

    public function test_mark_failed_updates_brief_status(): void
    {
        $pipeline = ContentPipeline::factory()->create(['space_id' => $this->space->id]);
        $brief    = ContentBrief::factory()->create(['space_id' => $this->space->id]);
        $run      = PipelineRun::create([
            'pipeline_id'      => $pipeline->id,
            'content_brief_id' => $brief->id,
            'status'           => 'running',
            'current_stage'    => 'generate',
            'started_at'       => now(),
        ]);

        $run->markFailed('Something went wrong');

        $this->assertEquals('failed', $brief->fresh()->status);
    }
}
