<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\MediaImportJob;
use App\Models\Migration\MigrationSession;
use App\Models\Space;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class MigrationMediaTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Space $space;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->space = Space::factory()->create();
    }

    public function test_start_media_import_dispatches_job(): void
    {
        Queue::fake();

        $session = MigrationSession::factory()->create([
            'space_id' => $this->space->id,
            'status' => 'mapped',
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/spaces/{$this->space->id}/migrations/{$session->id}/media");

        $response->assertOk();
        $response->assertJsonPath('data.status', 'queued');

        Queue::assertPushed(MediaImportJob::class, function ($job) use ($session) {
            return $job->sessionId === $session->id;
        });
    }

    public function test_cannot_start_media_import_for_completed_session(): void
    {
        Queue::fake();

        $session = MigrationSession::factory()->create([
            'space_id' => $this->space->id,
            'status' => 'completed',
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/spaces/{$this->space->id}/migrations/{$session->id}/media");

        $response->assertStatus(422);
        Queue::assertNothingPushed();
    }

    public function test_get_media_import_progress(): void
    {
        $session = MigrationSession::factory()->create([
            'space_id' => $this->space->id,
            'status' => 'running',
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/spaces/{$this->space->id}/migrations/{$session->id}/media/progress");

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => ['session_id', 'total_processed', 'status'],
        ]);
    }

    public function test_returns_404_for_wrong_space(): void
    {
        $otherSpace = Space::factory()->create();

        $session = MigrationSession::factory()->create([
            'space_id' => $otherSpace->id,
            'status' => 'mapped',
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/spaces/{$this->space->id}/migrations/{$session->id}/media");

        $response->assertNotFound();
    }
}
