<?php

namespace Tests\Feature;

use App\Models\Migration\MigrationItem;
use App\Models\Migration\MigrationSession;
use App\Models\Migration\MigrationTypeMapping;
use App\Models\Space;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MigrationWizardTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_migration_session(): void
    {
        $space = Space::factory()->create();
        $user = User::factory()->create();

        $session = MigrationSession::factory()->create([
            'space_id' => $space->id,
            'created_by' => $user->id,
            'name' => 'WordPress Import',
            'source_cms' => 'wordpress',
            'source_url' => 'https://example.com',
            'status' => 'pending',
        ]);

        $this->assertDatabaseHas('migration_sessions', [
            'id' => $session->id,
            'name' => 'WordPress Import',
            'source_cms' => 'wordpress',
            'status' => 'pending',
        ]);

        $this->assertEquals($space->id, $session->space_id);
        $this->assertNotNull($session->id);
        $this->assertEquals(26, strlen($session->id));
    }

    public function test_can_create_type_mapping(): void
    {
        $session = MigrationSession::factory()->create();

        $mapping = MigrationTypeMapping::create([
            'migration_session_id' => $session->id,
            'space_id' => $session->space_id,
            'source_type_key' => 'post',
            'source_type_label' => 'Post',
            'field_map' => ['title' => 'title', 'content' => 'body'],
            'status' => 'pending',
        ]);

        $this->assertDatabaseHas('migration_type_mappings', [
            'id' => $mapping->id,
            'source_type_key' => 'post',
            'status' => 'pending',
        ]);

        $this->assertIsArray($mapping->field_map);
        $this->assertEquals('title', $mapping->field_map['title']);
        $this->assertNotNull($mapping->id);
        $this->assertEquals(26, strlen($mapping->id));
    }

    public function test_can_create_migration_item(): void
    {
        $session = MigrationSession::factory()->create();

        $item = MigrationItem::create([
            'migration_session_id' => $session->id,
            'space_id' => $session->space_id,
            'source_type_key' => 'post',
            'source_id' => '12345',
            'status' => 'pending',
            'attempt' => 0,
        ]);

        $this->assertDatabaseHas('migration_items', [
            'id' => $item->id,
            'source_type_key' => 'post',
            'source_id' => '12345',
            'status' => 'pending',
        ]);

        $this->assertNotNull($item->id);
        $this->assertEquals(26, strlen($item->id));
        $this->assertEquals(0, $item->attempt);
    }

    public function test_migration_item_unique_constraint(): void
    {
        $session = MigrationSession::factory()->create();

        MigrationItem::create([
            'migration_session_id' => $session->id,
            'space_id' => $session->space_id,
            'source_type_key' => 'post',
            'source_id' => '99999',
            'status' => 'pending',
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        MigrationItem::create([
            'migration_session_id' => $session->id,
            'space_id' => $session->space_id,
            'source_type_key' => 'post',
            'source_id' => '99999',
            'status' => 'pending',
        ]);
    }
}
