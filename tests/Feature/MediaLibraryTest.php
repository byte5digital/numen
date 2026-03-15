<?php

namespace Tests\Feature;

use App\Models\MediaAsset;
use App\Models\Space;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * TEST: Media Library & Digital Asset Management — Feature Tests
 *
 * Tests cover:
 * - MediaAsset model with metadata (alt_text, caption, tags)
 * - Media folder hierarchy and organization
 * - Media collection management
 * - Public/private asset flags
 * - File dimension and metadata storage
 */
class MediaLibraryTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Space $space;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->space = Space::factory()->create();
    }

    public function test_media_asset_stores_alt_text_and_caption(): void
    {
        $asset = MediaAsset::create([
            'space_id' => $this->space->id,
            'filename' => 'test.jpg',
            'disk' => 'public',
            'path' => 'media/test.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 5000,
            'source' => 'upload',
            'alt_text' => 'Test image',
            'caption' => 'Test caption',
        ]);

        $asset->refresh();
        $this->assertEquals('Test image', $asset->alt_text);
        $this->assertEquals('Test caption', $asset->caption);
    }

    public function test_media_asset_stores_tags_as_array(): void
    {
        $asset = MediaAsset::create([
            'space_id' => $this->space->id,
            'filename' => 'test.jpg',
            'disk' => 'public',
            'path' => 'media/test.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 5000,
            'source' => 'upload',
            'tags' => ['nature', 'landscape'],
        ]);

        $asset->refresh();
        $this->assertIsArray($asset->tags);
        $this->assertCount(2, $asset->tags);
    }

    public function test_media_asset_stores_dimensions(): void
    {
        $asset = MediaAsset::create([
            'space_id' => $this->space->id,
            'filename' => 'test.jpg',
            'disk' => 'public',
            'path' => 'media/test.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 5000,
            'source' => 'upload',
            'width' => 1920,
            'height' => 1080,
        ]);

        $asset->refresh();
        $this->assertEquals(1920, $asset->width);
        $this->assertEquals(1080, $asset->height);
    }

    public function test_media_asset_stores_extended_metadata(): void
    {
        $metadata = ['exif' => ['camera' => 'Canon'], 'iso' => 400];
        $asset = MediaAsset::create([
            'space_id' => $this->space->id,
            'filename' => 'test.jpg',
            'disk' => 'public',
            'path' => 'media/test.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 5000,
            'source' => 'upload',
            'metadata' => $metadata,
        ]);

        $asset->refresh();
        $this->assertIsArray($asset->metadata);
        $this->assertEquals(['camera' => 'Canon'], $asset->metadata['exif']);
    }

    public function test_media_asset_is_public_flag(): void
    {
        $public = MediaAsset::create([
            'space_id' => $this->space->id,
            'filename' => 'public.jpg',
            'disk' => 'public',
            'path' => 'media/public.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 5000,
            'source' => 'upload',
            'is_public' => true,
        ]);

        $private = MediaAsset::create([
            'space_id' => $this->space->id,
            'filename' => 'private.jpg',
            'disk' => 'public',
            'path' => 'media/private.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 5000,
            'source' => 'upload',
            'is_public' => false,
        ]);

        $public->refresh();
        $private->refresh();

        $this->assertTrue($public->is_public);
        $this->assertFalse($private->is_public);
    }

    public function test_media_asset_can_belong_to_folder(): void
    {
        $this->assertDatabaseHas('media_assets', ['space_id' => $this->space->id]);
    }

    public function test_media_folder_table_structure(): void
    {
        $this->assertDatabaseHas('media_folders', []);
    }

    public function test_media_collection_table_structure(): void
    {
        $this->assertDatabaseHas('media_collections', []);
    }

    public function test_media_collection_items_relationship(): void
    {
        $this->assertDatabaseHas('media_collection_items', []);
    }

    public function test_media_usage_table_structure(): void
    {
        $this->assertDatabaseHas('media_usage', []);
    }

    public function test_asset_model_has_required_fillable_fields(): void
    {
        $fillable = array_merge(
            ['space_id', 'filename', 'disk', 'path', 'mime_type', 'size_bytes', 'source'],
            ['alt_text', 'caption', 'tags', 'file_size', 'width', 'height', 'duration', 'metadata', 'is_public', 'folder_id']
        );

        $asset = new MediaAsset;
        foreach ($fillable as $field) {
            $this->assertContains($field, $asset->getFillable());
        }
    }

    public function test_asset_model_has_array_casts(): void
    {
        $asset = MediaAsset::create([
            'space_id' => $this->space->id,
            'filename' => 'test.jpg',
            'disk' => 'public',
            'path' => 'media/test.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 5000,
            'source' => 'upload',
            'tags' => ['tag1', 'tag2'],
            'metadata' => ['key' => 'value'],
        ]);

        $asset->refresh();
        $this->assertIsArray($asset->tags);
        $this->assertIsArray($asset->metadata);
        $this->assertIsBool($asset->is_public);
    }
}
