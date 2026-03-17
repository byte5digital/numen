<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Migration\MigrationSession;
use App\Models\Space;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MigrationSessionTest extends TestCase
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

    public function test_unauthenticated_user_cannot_list_sessions(): void
    {
        $this->getJson("/api/v1/spaces/{$this->space->id}/migrations")
            ->assertUnauthorized();
    }

    public function test_can_list_migration_sessions(): void
    {
        MigrationSession::factory()->count(3)->create([
            'space_id' => $this->space->id,
            'created_by' => $this->user->id,
        ]);

        $this->actingAs($this->user)
            ->getJson("/api/v1/spaces/{$this->space->id}/migrations")
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonStructure([
                'data' => [['id', 'name', 'source_cms', 'source_url', 'status']],
            ]);
    }

    public function test_can_create_migration_session(): void
    {
        $payload = [
            'name' => 'WordPress Migration',
            'source_url' => 'https://example.com',
            'source_cms' => 'wordpress',
        ];

        $this->actingAs($this->user)
            ->postJson("/api/v1/spaces/{$this->space->id}/migrations", $payload)
            ->assertCreated()
            ->assertJsonPath('data.name', 'WordPress Migration')
            ->assertJsonPath('data.source_cms', 'wordpress')
            ->assertJsonPath('data.status', 'pending');

        $this->assertDatabaseHas('migration_sessions', [
            'space_id' => $this->space->id,
            'name' => 'WordPress Migration',
        ]);
    }

    public function test_create_session_validates_required_fields(): void
    {
        $this->actingAs($this->user)
            ->postJson("/api/v1/spaces/{$this->space->id}/migrations", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'source_url', 'source_cms']);
    }

    public function test_create_session_validates_source_cms(): void
    {
        $this->actingAs($this->user)
            ->postJson("/api/v1/spaces/{$this->space->id}/migrations", [
                'name' => 'Test',
                'source_url' => 'https://example.com',
                'source_cms' => 'invalid_cms',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['source_cms']);
    }

    public function test_can_show_migration_session(): void
    {
        $session = MigrationSession::factory()->create([
            'space_id' => $this->space->id,
            'created_by' => $this->user->id,
        ]);

        $this->actingAs($this->user)
            ->getJson("/api/v1/spaces/{$this->space->id}/migrations/{$session->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $session->id);
    }

    public function test_show_returns_404_for_wrong_space(): void
    {
        $otherSpace = Space::factory()->create();
        $session = MigrationSession::factory()->create([
            'space_id' => $otherSpace->id,
            'created_by' => $this->user->id,
        ]);

        $this->actingAs($this->user)
            ->getJson("/api/v1/spaces/{$this->space->id}/migrations/{$session->id}")
            ->assertNotFound();
    }

    public function test_can_update_migration_session(): void
    {
        $session = MigrationSession::factory()->create([
            'space_id' => $this->space->id,
            'created_by' => $this->user->id,
            'name' => 'Old Name',
        ]);

        $this->actingAs($this->user)
            ->patchJson("/api/v1/spaces/{$this->space->id}/migrations/{$session->id}", [
                'name' => 'New Name',
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'New Name');
    }

    public function test_can_delete_migration_session(): void
    {
        $session = MigrationSession::factory()->create([
            'space_id' => $this->space->id,
            'created_by' => $this->user->id,
        ]);

        $this->actingAs($this->user)
            ->deleteJson("/api/v1/spaces/{$this->space->id}/migrations/{$session->id}")
            ->assertOk();

        $this->assertDatabaseMissing('migration_sessions', ['id' => $session->id]);
    }

    public function test_factory_smoke(): void
    {
        $session = MigrationSession::factory()->create();
        $this->assertNotNull($session->id);
        $this->assertEquals(26, strlen($session->id));
    }
}
