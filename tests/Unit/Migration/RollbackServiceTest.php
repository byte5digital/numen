<?php

declare(strict_types=1);

namespace Tests\Unit\Migration;

use App\Models\Content;
use App\Models\MediaAsset;
use App\Models\Migration\MigrationItem;
use App\Models\Migration\MigrationSession;
use App\Models\Space;
use App\Models\User;
use App\Services\Migration\RollbackService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RollbackServiceTest extends TestCase
{
    use RefreshDatabase;

    private RollbackService $service;

    private MigrationSession $session;

    private Space $space;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new RollbackService;
        $this->space = Space::factory()->create();
        $user = User::factory()->create();
        $this->session = MigrationSession::factory()->create([
            'space_id' => $this->space->id,
            'created_by' => $user->id,
            'status' => 'completed',
        ]);
    }

    public function test_rollback_deletes_imported_content(): void
    {
        $content = Content::factory()->create(['space_id' => $this->space->id]);

        MigrationItem::factory()->create([
            'migration_session_id' => $this->session->id,
            'space_id' => $this->space->id,
            'numen_content_id' => $content->id,
            'status' => 'completed',
        ]);

        $result = $this->service->rollback($this->session);

        $this->assertEquals(1, $result['contentDeleted']);
        $this->assertDatabaseMissing('contents', ['id' => $content->id]);
        $this->assertEquals('rolled_back', $this->session->fresh()->status);
    }

    public function test_rollback_deletes_imported_media(): void
    {
        $media = MediaAsset::factory()->create(['space_id' => $this->space->id]);

        MigrationItem::factory()->create([
            'migration_session_id' => $this->session->id,
            'space_id' => $this->space->id,
            'numen_media_ids' => [$media->id],
            'status' => 'completed',
        ]);

        $result = $this->service->rollback($this->session);

        $this->assertEquals(1, $result['mediaDeleted']);
        $this->assertDatabaseMissing('media_assets', ['id' => $media->id]);
    }

    public function test_rollback_throws_for_non_completed_session(): void
    {
        $this->session->update(['status' => 'running']);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Cannot rollback migration in 'running' status");

        $this->service->rollback($this->session);
    }

    public function test_rollback_returns_zero_counts_when_nothing_imported(): void
    {
        $result = $this->service->rollback($this->session);

        $this->assertEquals(0, $result['contentDeleted']);
        $this->assertEquals(0, $result['mediaDeleted']);
        $this->assertEquals(0, $result['taxonomiesDeleted']);
        $this->assertEquals(0, $result['usersDeleted']);
        $this->assertEquals('rolled_back', $this->session->fresh()->status);
    }

    public function test_rollback_marks_items_as_rolled_back(): void
    {
        $content = Content::factory()->create(['space_id' => $this->space->id]);

        MigrationItem::factory()->create([
            'migration_session_id' => $this->session->id,
            'space_id' => $this->space->id,
            'numen_content_id' => $content->id,
            'status' => 'completed',
        ]);

        $this->service->rollback($this->session);

        $this->assertDatabaseHas('migration_items', [
            'migration_session_id' => $this->session->id,
            'status' => 'rolled_back',
        ]);
    }
}
