<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Content;
use App\Models\MediaAsset;
use App\Models\Migration\MigrationItem;
use App\Models\Migration\MigrationSession;
use App\Models\Migration\MigrationTypeMapping;
use App\Models\Space;
use App\Models\User;
use App\Services\Migration\CmsConnectorFactory;
use App\Services\Migration\Connectors\CmsConnectorInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MigrationRollbackTest extends TestCase
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
            'status' => 'completed',
            'source_cms' => 'wordpress',
            'source_url' => 'https://example.com',
        ]);
    }

    public function test_unauthenticated_user_cannot_rollback(): void
    {
        $this->postJson("/api/v1/spaces/{$this->space->id}/migrations/{$this->session->id}/rollback")
            ->assertUnauthorized();
    }

    public function test_can_rollback_completed_migration(): void
    {
        $content = Content::factory()->create(['space_id' => $this->space->id]);

        MigrationItem::factory()->create([
            'migration_session_id' => $this->session->id,
            'space_id' => $this->space->id,
            'numen_content_id' => $content->id,
            'status' => 'completed',
        ]);

        $this->actingAs($this->user)
            ->postJson("/api/v1/spaces/{$this->space->id}/migrations/{$this->session->id}/rollback")
            ->assertOk()
            ->assertJsonPath('message', 'Migration rolled back successfully.')
            ->assertJsonPath('data.contentDeleted', 1)
            ->assertJsonPath('data.status', 'rolled_back');

        $this->assertDatabaseMissing('contents', ['id' => $content->id]);
    }

    public function test_cannot_rollback_non_completed_migration(): void
    {
        $this->session->update(['status' => 'running']);

        $this->actingAs($this->user)
            ->postJson("/api/v1/spaces/{$this->space->id}/migrations/{$this->session->id}/rollback")
            ->assertUnprocessable();
    }

    public function test_returns_404_for_wrong_space(): void
    {
        $otherSpace = Space::factory()->create();

        $this->actingAs($this->user)
            ->postJson("/api/v1/spaces/{$otherSpace->id}/migrations/{$this->session->id}/rollback")
            ->assertNotFound();
    }

    public function test_unauthenticated_user_cannot_sync(): void
    {
        $this->postJson("/api/v1/spaces/{$this->space->id}/migrations/{$this->session->id}/sync")
            ->assertUnauthorized();
    }

    public function test_can_sync_completed_migration(): void
    {
        MigrationTypeMapping::factory()->create([
            'migration_session_id' => $this->session->id,
            'space_id' => $this->space->id,
            'source_type_key' => 'post',
            'status' => 'confirmed',
        ]);

        $connector = $this->createMock(CmsConnectorInterface::class);
        $connector->method('getContentItems')->willReturn([]);

        $factory = $this->createMock(CmsConnectorFactory::class);
        $factory->method('make')->willReturn($connector);

        $this->app->instance(CmsConnectorFactory::class, $factory);

        $this->actingAs($this->user)
            ->postJson("/api/v1/spaces/{$this->space->id}/migrations/{$this->session->id}/sync")
            ->assertOk()
            ->assertJsonPath('message', 'Delta sync completed.')
            ->assertJsonPath('data.status', 'synced');
    }

    public function test_cannot_sync_pending_migration(): void
    {
        $this->session->update(['status' => 'pending']);

        $this->actingAs($this->user)
            ->postJson("/api/v1/spaces/{$this->space->id}/migrations/{$this->session->id}/sync")
            ->assertUnprocessable();
    }

    /**
     * Integration test: full migration flow.
     * Create session → items → execute → complete → rollback → verify cleanup.
     */
    public function test_full_migration_flow_with_rollback(): void
    {
        // Simulate a completed migration with imported content
        $content1 = Content::factory()->create(['space_id' => $this->space->id]);
        $content2 = Content::factory()->create(['space_id' => $this->space->id]);
        $media = MediaAsset::factory()->create(['space_id' => $this->space->id]);

        MigrationItem::factory()->create([
            'migration_session_id' => $this->session->id,
            'space_id' => $this->space->id,
            'numen_content_id' => $content1->id,
            'numen_media_ids' => [$media->id],
            'status' => 'completed',
        ]);

        MigrationItem::factory()->create([
            'migration_session_id' => $this->session->id,
            'space_id' => $this->space->id,
            'numen_content_id' => $content2->id,
            'status' => 'completed',
        ]);

        // Rollback
        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/spaces/{$this->space->id}/migrations/{$this->session->id}/rollback")
            ->assertOk();

        $data = $response->json('data');
        $this->assertEquals(2, $data['contentDeleted']);
        $this->assertEquals(1, $data['mediaDeleted']);
        $this->assertEquals('rolled_back', $data['status']);

        // Verify content is gone
        $this->assertDatabaseMissing('contents', ['id' => $content1->id]);
        $this->assertDatabaseMissing('contents', ['id' => $content2->id]);
        $this->assertDatabaseMissing('media_assets', ['id' => $media->id]);

        // Session is marked as rolled back
        $this->assertDatabaseHas('migration_sessions', [
            'id' => $this->session->id,
            'status' => 'rolled_back',
        ]);
    }
}
