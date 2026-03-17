<?php

declare(strict_types=1);

namespace Tests\Unit\Migration;

use App\Jobs\MigrateContentChunkJob;
use App\Models\Content;
use App\Models\ContentType;
use App\Models\Migration\MigrationItem;
use App\Models\Migration\MigrationSession;
use App\Models\Migration\MigrationTypeMapping;
use App\Models\Space;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MigrateContentChunkJobTest extends TestCase
{
    use RefreshDatabase;

    private MigrationSession $session;

    private Space $space;

    private ContentType $contentType;

    protected function setUp(): void
    {
        parent::setUp();
        $this->space = Space::factory()->create();
        $user = User::factory()->create();
        $this->contentType = ContentType::factory()->create(['space_id' => $this->space->id]);
        $this->session = MigrationSession::factory()->create([
            'space_id' => $this->space->id,
            'created_by' => $user->id,
            'status' => 'running',
        ]);
    }

    public function test_imports_transformed_items_as_content(): void
    {
        MigrationTypeMapping::factory()->create([
            'migration_session_id' => $this->session->id,
            'source_type_key' => 'post',
            'numen_content_type_id' => $this->contentType->id,
            'status' => 'approved',
        ]);

        MigrationItem::factory()->create([
            'migration_session_id' => $this->session->id,
            'space_id' => $this->space->id,
            'source_type_key' => 'post',
            'source_id' => 'src-1',
            'status' => 'transformed',
            'source_payload' => json_encode([
                'fields' => ['title' => 'Test Post', 'body' => '<p>Hello</p>', 'slug' => 'test-post'],
                'media_refs' => [],
                'taxonomy_ids' => [],
            ]),
        ]);

        $job = new MigrateContentChunkJob($this->session->id, 0, 50);
        $job->handle();

        $this->assertDatabaseHas('migration_items', [
            'migration_session_id' => $this->session->id,
            'source_id' => 'src-1',
            'status' => 'completed',
        ]);

        $this->assertDatabaseHas('contents', [
            'space_id' => $this->space->id,
            'slug' => 'test-post',
            'status' => 'draft',
        ]);

        $this->assertDatabaseHas('content_versions', [
            'title' => 'Test Post',
            'body' => '<p>Hello</p>',
            'version_number' => 1,
        ]);
    }

    public function test_marks_item_as_failed_on_error(): void
    {
        MigrationItem::factory()->create([
            'migration_session_id' => $this->session->id,
            'space_id' => $this->space->id,
            'source_type_key' => 'post',
            'source_id' => 'bad-1',
            'status' => 'transformed',
            'source_payload' => 'not-valid-json{{{',
        ]);

        $job = new MigrateContentChunkJob($this->session->id, 0, 50);
        $job->handle();

        $this->assertDatabaseHas('migration_items', [
            'source_id' => 'bad-1',
            'status' => 'failed',
        ]);
    }

    public function test_skips_paused_session(): void
    {
        $this->session->update(['status' => 'paused']);

        MigrationItem::factory()->create([
            'migration_session_id' => $this->session->id,
            'space_id' => $this->space->id,
            'source_type_key' => 'post',
            'source_id' => 'src-2',
            'status' => 'transformed',
            'source_payload' => json_encode([
                'fields' => ['title' => 'Skipped'],
                'media_refs' => [],
                'taxonomy_ids' => [],
            ]),
        ]);

        $job = new MigrateContentChunkJob($this->session->id, 0, 50);
        $job->handle();

        $this->assertDatabaseHas('migration_items', [
            'source_id' => 'src-2',
            'status' => 'transformed',
        ]);
    }

    public function test_generates_unique_slug_on_collision(): void
    {
        MigrationTypeMapping::factory()->create([
            'migration_session_id' => $this->session->id,
            'source_type_key' => 'post',
            'numen_content_type_id' => $this->contentType->id,
            'status' => 'approved',
        ]);

        Content::factory()->create([
            'space_id' => $this->space->id,
            'slug' => 'duplicate-slug',
        ]);

        MigrationItem::factory()->create([
            'migration_session_id' => $this->session->id,
            'space_id' => $this->space->id,
            'source_type_key' => 'post',
            'source_id' => 'dup-1',
            'status' => 'transformed',
            'source_payload' => json_encode([
                'fields' => ['title' => 'Duplicate', 'slug' => 'duplicate-slug'],
                'media_refs' => [],
                'taxonomy_ids' => [],
            ]),
        ]);

        $job = new MigrateContentChunkJob($this->session->id, 0, 50);
        $job->handle();

        $this->assertDatabaseHas('contents', [
            'space_id' => $this->space->id,
            'slug' => 'duplicate-slug-1',
        ]);
    }

    public function test_factory_smoke(): void
    {
        $item = MigrationItem::factory()->create();
        $this->assertNotNull($item->id);
    }
}
