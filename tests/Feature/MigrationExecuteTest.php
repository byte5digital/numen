<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Migration\MigrationItem;
use App\Models\Migration\MigrationSession;
use App\Models\Space;
use App\Models\User;
use App\Services\Migration\ContentTransformPipeline;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class MigrationExecuteTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Space $space;

    private MigrationSession $session;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->space = Space::factory()->create();
        $this->session = MigrationSession::factory()->create([
            'space_id' => $this->space->id,
            'created_by' => $this->user->id,
            'status' => 'mapped',
        ]);
    }

    public function test_unauthenticated_user_cannot_execute(): void
    {
        $this->postJson("/api/v1/spaces/{$this->space->id}/migrations/{$this->session->id}/execute")
            ->assertUnauthorized();
    }

    public function test_can_start_migration_execution(): void
    {
        Queue::fake();

        $this->mock(ContentTransformPipeline::class)
            ->shouldReceive('run')
            ->once()
            ->andReturn(['total' => 0, 'processed' => 0, 'failed' => 0, 'skipped' => 0]);

        $this->actingAs($this->user)
            ->postJson("/api/v1/spaces/{$this->space->id}/migrations/{$this->session->id}/execute")
            ->assertOk()
            ->assertJsonPath('message', 'Migration started.');
    }

    public function test_cannot_execute_completed_migration(): void
    {
        $this->session->update(['status' => 'completed']);

        $this->actingAs($this->user)
            ->postJson("/api/v1/spaces/{$this->space->id}/migrations/{$this->session->id}/execute")
            ->assertUnprocessable()
            ->assertJsonPath('message', "Cannot execute migration in 'completed' status.");
    }

    public function test_can_get_progress(): void
    {
        MigrationItem::factory()->count(2)->create([
            'migration_session_id' => $this->session->id,
            'space_id' => $this->space->id,
            'status' => 'completed',
        ]);
        MigrationItem::factory()->create([
            'migration_session_id' => $this->session->id,
            'space_id' => $this->space->id,
            'status' => 'failed',
        ]);

        $this->actingAs($this->user)
            ->getJson("/api/v1/spaces/{$this->space->id}/migrations/{$this->session->id}/progress")
            ->assertOk()
            ->assertJsonPath('data.total', 3)
            ->assertJsonPath('data.completed', 2)
            ->assertJsonPath('data.failed', 1)
            ->assertJsonPath('data.pending', 0);
    }

    public function test_can_pause_running_migration(): void
    {
        $this->session->update(['status' => 'running']);

        $this->actingAs($this->user)
            ->postJson("/api/v1/spaces/{$this->space->id}/migrations/{$this->session->id}/pause")
            ->assertOk()
            ->assertJsonPath('data.status', 'paused');

        $this->assertDatabaseHas('migration_sessions', [
            'id' => $this->session->id,
            'status' => 'paused',
        ]);
    }

    public function test_cannot_pause_non_running_migration(): void
    {
        $this->actingAs($this->user)
            ->postJson("/api/v1/spaces/{$this->space->id}/migrations/{$this->session->id}/pause")
            ->assertUnprocessable();
    }

    public function test_can_resume_paused_migration(): void
    {
        Queue::fake();
        $this->session->update(['status' => 'paused']);

        $this->actingAs($this->user)
            ->postJson("/api/v1/spaces/{$this->space->id}/migrations/{$this->session->id}/resume")
            ->assertOk()
            ->assertJsonPath('data.status', 'running');
    }

    public function test_cannot_resume_non_paused_migration(): void
    {
        $this->session->update(['status' => 'running']);

        $this->actingAs($this->user)
            ->postJson("/api/v1/spaces/{$this->space->id}/migrations/{$this->session->id}/resume")
            ->assertUnprocessable();
    }

    public function test_returns_404_for_wrong_space(): void
    {
        $otherSpace = Space::factory()->create();

        $this->actingAs($this->user)
            ->getJson("/api/v1/spaces/{$otherSpace->id}/migrations/{$this->session->id}/progress")
            ->assertNotFound();
    }
}
