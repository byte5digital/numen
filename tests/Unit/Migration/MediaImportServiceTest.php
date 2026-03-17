<?php

declare(strict_types=1);

namespace Tests\Unit\Migration;

use App\Models\MediaAsset;
use App\Models\Migration\MigrationCheckpoint;
use App\Models\Migration\MigrationSession;
use App\Services\Migration\CmsConnectorFactory;
use App\Services\Migration\Connectors\CmsConnectorInterface;
use App\Services\Migration\MediaImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class MediaImportServiceTest extends TestCase
{
    use RefreshDatabase;

    private MediaImportService $service;

    private CmsConnectorFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->factory = Mockery::mock(CmsConnectorFactory::class);
        $this->service = new MediaImportService($this->factory);
    }

    private function createSession(): MigrationSession
    {
        return MigrationSession::factory()->create([
            'source_cms' => 'wordpress',
            'source_url' => 'https://example.com',
            'status' => 'running',
        ]);
    }

    public function test_imports_media_and_creates_assets(): void
    {
        Storage::fake('public');
        Http::fake([
            'https://example.com/image.jpg' => Http::response('fake-image-data', 200, [
                'Content-Type' => 'image/jpeg',
            ]),
        ]);

        $session = $this->createSession();

        $connector = Mockery::mock(CmsConnectorInterface::class);
        $connector->shouldReceive('getMediaItems')
            ->with(1, 50)
            ->andReturn([
                ['id' => 'media-1', 'url' => 'https://example.com/image.jpg', 'filename' => 'image.jpg'],
            ]);
        $connector->shouldReceive('getMediaItems')
            ->with(2, 50)
            ->andReturn([]);

        $this->factory->shouldReceive('make')->andReturn($connector);

        $mapping = $this->service->importMedia($session);

        $this->assertCount(2, $mapping); // both ID and URL mapped
        $this->assertTrue($mapping->has('media-1'));
        $this->assertTrue($mapping->has('https://example.com/image.jpg'));

        $asset = MediaAsset::where('space_id', $session->space_id)->first();
        $this->assertNotNull($asset);
        $this->assertSame('image.jpg', $asset->filename);
        $this->assertSame('image/jpeg', $asset->mime_type);
        $this->assertSame('migration', $asset->source);
    }

    public function test_skips_already_imported_media(): void
    {
        Storage::fake('public');
        Http::fake([
            'https://example.com/image.jpg' => Http::response('fake-data', 200, [
                'Content-Type' => 'image/jpeg',
            ]),
        ]);

        $session = $this->createSession();

        // Pre-create asset with matching url hash
        $urlHash = md5('https://example.com/image.jpg');
        $existing = MediaAsset::create([
            'space_id' => $session->space_id,
            'filename' => 'existing.jpg',
            'disk' => 'public',
            'path' => 'media/existing.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 100,
            'source' => 'migration',
            'metadata' => [
                'migration_url_hash' => $urlHash,
            ],
        ]);

        $connector = Mockery::mock(CmsConnectorInterface::class);
        $connector->shouldReceive('getMediaItems')
            ->with(1, 50)
            ->andReturn([
                ['id' => 'media-1', 'url' => 'https://example.com/image.jpg'],
            ]);
        $connector->shouldReceive('getMediaItems')
            ->with(2, 50)
            ->andReturn([]);

        $this->factory->shouldReceive('make')->andReturn($connector);

        $mapping = $this->service->importMedia($session);

        // Should map to existing asset, not create new one
        $this->assertSame($existing->id, $mapping->get('media-1'));
        $this->assertSame(1, MediaAsset::where('space_id', $session->space_id)->count());
    }

    public function test_tracks_progress_via_checkpoint(): void
    {
        Storage::fake('public');
        Http::fake([
            '*' => Http::response('data', 200, ['Content-Type' => 'image/png']),
        ]);

        $session = $this->createSession();

        $connector = Mockery::mock(CmsConnectorInterface::class);
        $connector->shouldReceive('getMediaItems')
            ->with(1, 50)
            ->andReturn([
                ['id' => '1', 'url' => 'https://example.com/a.png', 'filename' => 'a.png'],
                ['id' => '2', 'url' => 'https://example.com/b.png', 'filename' => 'b.png'],
            ]);
        $connector->shouldReceive('getMediaItems')
            ->with(2, 50)
            ->andReturn([]);

        $this->factory->shouldReceive('make')->andReturn($connector);

        $this->service->importMedia($session);

        $checkpoint = MigrationCheckpoint::where('migration_session_id', $session->id)
            ->where('source_type_key', '__media_import')
            ->first();

        $this->assertNotNull($checkpoint);
        $this->assertSame(2, $checkpoint->item_count);
    }

    public function test_get_progress_returns_status(): void
    {
        $session = $this->createSession();

        $progress = $this->service->getProgress($session);
        $this->assertSame('pending', $progress['status']);
        $this->assertSame(0, $progress['total_processed']);
    }

    public function test_handles_failed_downloads_gracefully(): void
    {
        Storage::fake('public');
        Http::fake([
            'https://example.com/missing.jpg' => Http::response('Not Found', 404),
        ]);

        $session = $this->createSession();

        $connector = Mockery::mock(CmsConnectorInterface::class);
        $connector->shouldReceive('getMediaItems')
            ->with(1, 50)
            ->andReturn([
                ['id' => 'media-1', 'url' => 'https://example.com/missing.jpg'],
            ]);
        $connector->shouldReceive('getMediaItems')
            ->with(2, 50)
            ->andReturn([]);

        $this->factory->shouldReceive('make')->andReturn($connector);

        $mapping = $this->service->importMedia($session);

        // Should not have mapping for failed download
        $this->assertFalse($mapping->has('media-1'));
        $this->assertSame(0, MediaAsset::where('space_id', $session->space_id)->count());
    }
}
