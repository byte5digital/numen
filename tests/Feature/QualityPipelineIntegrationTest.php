<?php

namespace Tests\Feature;

use App\Events\Content\ContentPublished;
use App\Jobs\QualityGateStageJob;
use App\Jobs\ScoreContentQualityJob;
use App\Listeners\AutoScoreOnPublishListener;
use App\Models\Content;
use App\Models\ContentQualityConfig;
use App\Models\ContentQualityScore;
use App\Models\ContentType;
use App\Models\PipelineRun;
use App\Models\Space;
use App\Pipelines\PipelineExecutor;
use App\Services\Quality\ContentQualityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

class QualityPipelineIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private Space $space;

    protected function setUp(): void
    {
        parent::setUp();
        $this->space = Space::factory()->create();
        ContentType::create([
            'space_id' => $this->space->id,
            'name' => 'Blog Post',
            'slug' => 'blog_post',
            'schema' => ['fields' => []],
        ]);
    }

    // ── Auto-score on publish ──────────────────────────────────────────────

    public function test_auto_score_listener_dispatches_job_when_config_enabled(): void
    {
        Queue::fake();

        $content = Content::factory()->create(['space_id' => $this->space->id]);
        ContentQualityConfig::factory()->create([
            'space_id' => $this->space->id,
            'auto_score_on_publish' => true,
        ]);

        $listener = app(AutoScoreOnPublishListener::class);
        $listener->handle(new ContentPublished($content));

        Queue::assertPushed(ScoreContentQualityJob::class, fn ($job) => $job->content->id === $content->id);
    }

    public function test_auto_score_listener_does_not_dispatch_when_config_disabled(): void
    {
        Queue::fake();

        $content = Content::factory()->create(['space_id' => $this->space->id]);
        ContentQualityConfig::factory()->create([
            'space_id' => $this->space->id,
            'auto_score_on_publish' => false,
        ]);

        $listener = app(AutoScoreOnPublishListener::class);
        $listener->handle(new ContentPublished($content));

        Queue::assertNotPushed(ScoreContentQualityJob::class);
    }

    public function test_auto_score_listener_dispatches_when_no_config(): void
    {
        Queue::fake();

        $content = Content::factory()->create(['space_id' => $this->space->id]);

        $listener = app(AutoScoreOnPublishListener::class);
        $listener->handle(new ContentPublished($content));

        Queue::assertPushed(ScoreContentQualityJob::class);
    }

    // ── QualityGateStageJob ────────────────────────────────────────────────

    public function test_quality_gate_advances_pipeline_when_score_passes(): void
    {
        $content = Content::factory()->create(['space_id' => $this->space->id]);

        $run = PipelineRun::factory()->create([
            'content_id' => $content->id,
            'status' => 'running',
        ]);

        $passScore = ContentQualityScore::factory()->make([
            'space_id' => $this->space->id,
            'content_id' => $content->id,
            'overall_score' => 85.0,
        ]);

        $qualityService = Mockery::mock(ContentQualityService::class);
        $qualityService->shouldReceive('score')->once()->andReturn($passScore);

        $executor = Mockery::mock(PipelineExecutor::class);
        $executor->shouldReceive('advance')->once()->with(
            Mockery::on(fn ($r) => $r->id === $run->id),
            Mockery::on(fn ($result) => $result['quality_gate_passed'] === true)
        );

        $job = new QualityGateStageJob($run, ['name' => 'quality_gate', 'type' => 'quality_gate', 'min_score' => 70]);
        $job->handle($qualityService, $executor);
    }

    public function test_quality_gate_pauses_pipeline_when_score_fails(): void
    {
        $content = Content::factory()->create(['space_id' => $this->space->id]);

        $run = PipelineRun::factory()->create([
            'content_id' => $content->id,
            'status' => 'running',
        ]);

        $failScore = ContentQualityScore::factory()->make([
            'space_id' => $this->space->id,
            'content_id' => $content->id,
            'overall_score' => 45.0,
        ]);

        $qualityService = Mockery::mock(ContentQualityService::class);
        $qualityService->shouldReceive('score')->once()->andReturn($failScore);

        $executor = Mockery::mock(PipelineExecutor::class);
        $executor->shouldNotReceive('advance');

        $job = new QualityGateStageJob($run, ['name' => 'quality_gate', 'type' => 'quality_gate', 'min_score' => 70]);
        $job->handle($qualityService, $executor);

        $run->refresh();
        $this->assertEquals('paused_for_review', $run->status);
    }

    public function test_quality_gate_uses_config_min_score_when_no_stage_override(): void
    {
        $content = Content::factory()->create(['space_id' => $this->space->id]);
        ContentQualityConfig::factory()->withGate()->create([
            'space_id' => $this->space->id,
        ]);

        $run = PipelineRun::factory()->create([
            'content_id' => $content->id,
            'status' => 'running',
        ]);

        $score = ContentQualityScore::factory()->make([
            'space_id' => $this->space->id,
            'content_id' => $content->id,
            'overall_score' => 80.0,
        ]);

        $qualityService = Mockery::mock(ContentQualityService::class);
        $qualityService->shouldReceive('score')->once()->andReturn($score);

        $executor = Mockery::mock(PipelineExecutor::class);
        $executor->shouldReceive('advance')->once()->with(
            Mockery::any(),
            Mockery::on(fn ($result) => $result['quality_gate_passed'] === true)
        );

        // No min_score in stage config — uses config's pipeline_gate_min_score (75.0)
        $job = new QualityGateStageJob($run, ['name' => 'quality_gate', 'type' => 'quality_gate']);
        $job->handle($qualityService, $executor);
    }

    // ── PipelineExecutor dispatches quality_gate stage ────────────────────

    public function test_pipeline_executor_dispatches_quality_gate_job(): void
    {
        Queue::fake();

        $run = PipelineRun::factory()->create([
            'status' => 'running',
            'current_stage' => 'quality_gate',
        ]);

        $stage = ['name' => 'quality_gate', 'type' => 'quality_gate'];

        $executor = app(PipelineExecutor::class);

        // Use reflection to call private dispatchCoreStage
        $ref = new \ReflectionClass($executor);
        $method = $ref->getMethod('dispatchCoreStage');
        $method->setAccessible(true);
        $method->invoke($executor, $run, $stage);

        Queue::assertPushed(QualityGateStageJob::class);
    }
}
