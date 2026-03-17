<?php

declare(strict_types=1);

namespace Tests\Unit\Migration;

use App\Models\Migration\MigrationItem;
use App\Models\Migration\MigrationSession;
use App\Models\Migration\MigrationTypeMapping;
use App\Models\Space;
use App\Models\User;
use App\Services\Migration\CmsConnectorFactory;
use App\Services\Migration\Connectors\CmsConnectorInterface;
use App\Services\Migration\DeltaSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeltaSyncServiceTest extends TestCase
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
            'status' => 'completed',
            'source_cms' => 'wordpress',
            'source_url' => 'https://example.com',
        ]);
    }

    public function test_sync_creates_new_items(): void
    {
        MigrationTypeMapping::factory()->create([
            'migration_session_id' => $this->session->id,
            'space_id' => $this->space->id,
            'source_type_key' => 'post',
            'status' => 'confirmed',
        ]);

        $connector = $this->createMock(CmsConnectorInterface::class);
        $connector->method('getContentItems')
            ->willReturn([
                ['id' => 'new-1', 'title' => 'New Post'],
            ]);

        $factory = $this->createMock(CmsConnectorFactory::class);
        $factory->method('make')->willReturn($connector);

        $service = new DeltaSyncService($factory);
        $result = $service->sync($this->session);

        $this->assertEquals(1, $result['created']);
        $this->assertEquals(0, $result['updated']);
        $this->assertEquals(0, $result['unchanged']);
        $this->assertEquals('synced', $this->session->fresh()->status);
    }

    public function test_sync_detects_unchanged_items(): void
    {
        MigrationTypeMapping::factory()->create([
            'migration_session_id' => $this->session->id,
            'space_id' => $this->space->id,
            'source_type_key' => 'post',
            'status' => 'confirmed',
        ]);

        $itemData = ['id' => 'existing-1', 'title' => 'Existing Post'];

        MigrationItem::factory()->create([
            'migration_session_id' => $this->session->id,
            'space_id' => $this->space->id,
            'source_type_key' => 'post',
            'source_id' => 'existing-1',
            'source_hash' => md5(json_encode($itemData)),
            'status' => 'completed',
        ]);

        $connector = $this->createMock(CmsConnectorInterface::class);
        $connector->method('getContentItems')
            ->willReturn([$itemData]);

        $factory = $this->createMock(CmsConnectorFactory::class);
        $factory->method('make')->willReturn($connector);

        $service = new DeltaSyncService($factory);
        $result = $service->sync($this->session);

        $this->assertEquals(0, $result['created']);
        $this->assertEquals(0, $result['updated']);
        $this->assertEquals(1, $result['unchanged']);
    }

    public function test_sync_detects_updated_items(): void
    {
        MigrationTypeMapping::factory()->create([
            'migration_session_id' => $this->session->id,
            'space_id' => $this->space->id,
            'source_type_key' => 'post',
            'status' => 'confirmed',
        ]);

        MigrationItem::factory()->create([
            'migration_session_id' => $this->session->id,
            'space_id' => $this->space->id,
            'source_type_key' => 'post',
            'source_id' => 'existing-1',
            'source_hash' => 'old-hash',
            'status' => 'completed',
        ]);

        $connector = $this->createMock(CmsConnectorInterface::class);
        $connector->method('getContentItems')
            ->willReturn([
                ['id' => 'existing-1', 'title' => 'Updated Post'],
            ]);

        $factory = $this->createMock(CmsConnectorFactory::class);
        $factory->method('make')->willReturn($connector);

        $service = new DeltaSyncService($factory);
        $result = $service->sync($this->session);

        $this->assertEquals(0, $result['created']);
        $this->assertEquals(1, $result['updated']);
    }

    public function test_sync_throws_for_invalid_status(): void
    {
        $this->session->update(['status' => 'pending']);

        $factory = $this->createMock(CmsConnectorFactory::class);

        $service = new DeltaSyncService($factory);

        $this->expectException(\InvalidArgumentException::class);
        $service->sync($this->session);
    }

    public function test_sync_updates_checkpoint(): void
    {
        MigrationTypeMapping::factory()->create([
            'migration_session_id' => $this->session->id,
            'space_id' => $this->space->id,
            'source_type_key' => 'post',
            'status' => 'confirmed',
        ]);

        $connector = $this->createMock(CmsConnectorInterface::class);
        $connector->method('getContentItems')
            ->willReturn([
                ['id' => 'item-99', 'title' => 'Latest'],
            ]);

        $factory = $this->createMock(CmsConnectorFactory::class);
        $factory->method('make')->willReturn($connector);

        $service = new DeltaSyncService($factory);
        $service->sync($this->session);

        $this->assertDatabaseHas('migration_checkpoints', [
            'migration_session_id' => $this->session->id,
            'source_type_key' => 'post',
            'last_cursor' => 'item-99',
        ]);
    }
}
