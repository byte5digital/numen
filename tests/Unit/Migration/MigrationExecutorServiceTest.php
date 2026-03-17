<?php

declare(strict_types=1);

namespace Tests\Unit\Migration;

use App\Jobs\MigrateContentChunkJob;
use App\Models\Migration\MigrationItem;
use App\Models\Migration\MigrationSession;
use App\Models\Space;
use App\Models\User;
use App\Services\Migration\ContentTransformPipeline;
use App\Services\Migration\MigrationExecutorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class MigrationExecutorServiceTest extends TestCase
{
    use RefreshDatabase;

    private MigrationSession $session;

    private Space $space;

    protected function setUp(): void
    {
        parent::setUp();
        $this->space = Space::factory()->create();
        $user = User::factory()->create();
        $this->session = MigrationSession::factory()->create([
            'space_id' => $this->space->id,
            'created_by' => $user->id,
            'status' => 'mapped',
        ]);
    }

    public function test_execute_sets_session_to_running(): void
    {
        Queue::fake();

        $pipeline = $this->mock(ContentTransformPipeline::class);
        $pipeline->shouldReceive('run')
            ->once()
            ->andReturn(['total' => 0, 'processed' => 0, 'failed' => 0, 'skipped' => 0]);

        $executor = new MigrationExecutorService($pipeline);
        $executor->execute($this->session);

        $this->session->refresh();
        // With no items, it completes immediately
        $this->assertContains($this->session->status, ['running', 'completed']);
    }

    public function test_execute_dispatches_chunk_jobs(): void
    {
        Queue::fake();

        // Create transformed items
        for ($i = 0; $i < 75; $i++) {
            MigrationItem::factory()->create([
                'migration_session_id' => $this->session->id,
                'space_id' => $this->space->id,
                'status' => 'transformed',
                'source_type_key' => 'post',
                'source_id' => "item-{$i}",
            ]);
        }

        $pipeline = $this->mock(ContentTransformPipeline::class);
        $pipeline->shouldReceive('run')
            ->once()
            ->andReturn(['total' => 75, 'processed' => 75, 'failed' => 0, 'skipped' => 0]);

        $executor = new MigrationExecutorService($pipeline);
        $executor->execute($this->session);

        Queue::assertPushed(MigrateContentChunkJob::class, 2); // 75 items / 50 chunk = 2 jobs
    }

    public function test_get_progress_returns_correct_counts(): void
    {
        MigrationItem::factory()->count(3)->create([
            'migration_session_id' => $this->session->id,
            'space_id' => $this->space->id,
            'status' => 'completed',
        ]);
        MigrationItem::factory()->count(2)->create([
            'migration_session_id' => $this->session->id,
            'space_id' => $this->space->id,
            'status' => 'failed',
        ]);
        MigrationItem::factory()->create([
            'migration_session_id' => $this->session->id,
            'space_id' => $this->space->id,
            'status' => 'transformed',
        ]);

        $pipeline = $this->mock(ContentTransformPipeline::class);
        $executor = new MigrationExecutorService($pipeline);

        $progress = $executor->getProgress($this->session);

        $this->assertEquals(6, $progress['total']);
        $this->assertEquals(3, $progress['completed']);
        $this->assertEquals(2, $progress['failed']);
        $this->assertEquals(1, $progress['pending']);
        $this->assertEquals(83.33, $progress['percentage']);
    }

    public function test_pause_sets_status(): void
    {
        $this->session->update(['status' => 'running']);

        $pipeline = $this->mock(ContentTransformPipeline::class);
        $executor = new MigrationExecutorService($pipeline);

        $result = $executor->pause($this->session);

        $this->assertTrue($result);
        $this->assertEquals('paused', $this->session->fresh()->status);
    }

    public function test_pause_fails_if_not_running(): void
    {
        $pipeline = $this->mock(ContentTransformPipeline::class);
        $executor = new MigrationExecutorService($pipeline);

        $result = $executor->pause($this->session);

        $this->assertFalse($result);
    }

    public function test_resume_dispatches_chunk_jobs(): void
    {
        Queue::fake();
        $this->session->update(['status' => 'paused']);

        MigrationItem::factory()->count(3)->create([
            'migration_session_id' => $this->session->id,
            'space_id' => $this->space->id,
            'status' => 'transformed',
        ]);

        $pipeline = $this->mock(ContentTransformPipeline::class);
        $executor = new MigrationExecutorService($pipeline);

        $result = $executor->resume($this->session);

        $this->assertTrue($result);
        $this->assertEquals('running', $this->session->fresh()->status);
        Queue::assertPushed(MigrateContentChunkJob::class, 1);
    }

    public function test_does_not_execute_completed_session(): void
    {
        Queue::fake();
        $this->session->update(['status' => 'completed']);

        $pipeline = $this->mock(ContentTransformPipeline::class);
        $pipeline->shouldNotReceive('run');

        $executor = new MigrationExecutorService($pipeline);
        $executor->execute($this->session);

        $this->assertEquals('completed', $this->session->fresh()->status);
    }
}
