<?php

declare(strict_types=1);

namespace Tests\Unit\Migration;

use App\Models\Migration\MigrationSession;
use App\Models\Migration\MigrationTypeMapping;
use App\Services\Migration\ContentTransformerService;
use App\Services\Migration\MigrationExecutorService;
use App\Services\Migration\RollbackService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EdgeCasesTest extends TestCase
{
    use RefreshDatabase;

    private ContentTransformerService $transformer;

    private RollbackService $rollbackService;

    private MigrationExecutorService $executorService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->transformer = app(ContentTransformerService::class);
        $this->rollbackService = app(RollbackService::class);
        $this->executorService = app(MigrationExecutorService::class);
    }

    public function test_transform_empty_source(): void
    {
        $mapping = MigrationTypeMapping::factory()->create();
        $result = $this->transformer->transform([], $mapping);
        $this->assertEmpty($result['fields']);
    }

    public function test_transform_skips_unmapped(): void
    {
        $mapping = MigrationTypeMapping::factory()->create([
            'field_map' => [['source_field' => 'title', 'target_field' => null]],
        ]);
        $result = $this->transformer->transform(['title' => 'Test'], $mapping);
        $this->assertEmpty($result['fields']);
    }

    public function test_malformed_html(): void
    {
        $mapping = MigrationTypeMapping::factory()->create([
            'field_map' => [[
                'source_field' => 'body',
                'target_field' => 'body',
                'source_type' => 'richtext',
            ]],
        ]);
        $html = '<div><p>Unclosed<span>Nested';
        $result = $this->transformer->transform(['body' => $html], $mapping);
        $this->assertArrayHasKey('body', $result['fields']);
    }

    public function test_deeply_nested_html(): void
    {
        $mapping = MigrationTypeMapping::factory()->create([
            'field_map' => [[
                'source_field' => 'content',
                'target_field' => 'content',
                'source_type' => 'richtext',
            ]],
        ]);
        $nested = '<div>'.str_repeat('<div>', 50).'text'.str_repeat('</div>', 50);
        $result = $this->transformer->transform(['content' => $nested], $mapping);
        $this->assertArrayHasKey('content', $result['fields']);
    }

    public function test_null_in_media_arrays(): void
    {
        $mapping = MigrationTypeMapping::factory()->create([
            'field_map' => [[
                'source_field' => 'gallery',
                'target_field' => 'gallery',
                'source_type' => 'media',
            ]],
        ]);
        $result = $this->transformer->transform(
            ['gallery' => ['file-1.jpg', null, 'file-2.jpg']],
            $mapping
        );
        $this->assertIsArray($result);
        $this->assertIsArray($result['fields']);
    }

    public function test_invalid_numbers(): void
    {
        $mapping = MigrationTypeMapping::factory()->create([
            'field_map' => [[
                'source_field' => 'qty',
                'target_field' => 'qty',
                'source_type' => 'number',
            ]],
        ]);
        $result = $this->transformer->transform(['qty' => 'text'], $mapping);
        $this->assertIsArray($result);
    }

    public function test_rollback_zero_items(): void
    {
        $session = MigrationSession::factory()->completed()->create([
            'total_items' => 0,
            'processed_items' => 0,
        ]);
        $counts = $this->rollbackService->rollback($session);
        $this->assertIsArray($counts);
        $this->assertEquals(0, $counts['content_deleted'] ?? 0);
    }

    public function test_rollback_incomplete_throws(): void
    {
        $session = MigrationSession::factory()->running()->create();
        $this->expectException(\Exception::class);
        $this->rollbackService->rollback($session);
    }

    public function test_pause_running(): void
    {
        $session = MigrationSession::factory()->running()->create();
        $this->executorService->pause($session);
        $session->refresh();
        $this->assertEquals('paused', $session->status);
    }

    public function test_missing_source_field(): void
    {
        $mapping = MigrationTypeMapping::factory()->create([
            'field_map' => [['source_field' => null, 'target_field' => 'title']],
        ]);
        $result = $this->transformer->transform(['title' => 'Test'], $mapping);
        $this->assertEmpty($result['fields']);
    }

    public function test_special_chars(): void
    {
        $mapping = MigrationTypeMapping::factory()->create([
            'field_map' => [[
                'source_field' => 'title',
                'target_field' => 'title',
                'source_type' => 'string',
            ]],
        ]);
        $special = '!@#$%^&*()_+-=[]{}|;:,.<>?/~`';
        $result = $this->transformer->transform(['title' => $special], $mapping);
        $this->assertArrayHasKey('title', $result['fields']);
    }

    public function test_unicode_chars(): void
    {
        $mapping = MigrationTypeMapping::factory()->create([
            'field_map' => [[
                'source_field' => 'title',
                'target_field' => 'title',
                'source_type' => 'string',
            ]],
        ]);
        $unicode = 'Hello 你好 مرحبا';
        $result = $this->transformer->transform(['title' => $unicode], $mapping);
        $this->assertArrayHasKey('title', $result['fields']);
    }

    public function test_executor_zero_progress(): void
    {
        $session = MigrationSession::factory()->completed()->create([
            'total_items' => 0,
            'processed_items' => 0,
        ]);
        $progress = $this->executorService->getProgress($session);
        $this->assertEquals(0, $progress['total']);
    }

    public function test_transform_with_empty_field_map(): void
    {
        $mapping = MigrationTypeMapping::factory()->create(['field_map' => []]);
        $result = $this->transformer->transform(['any' => 'value'], $mapping);
        $this->assertEmpty($result['fields']);
    }

    public function test_transform_missing_target_field(): void
    {
        $mapping = MigrationTypeMapping::factory()->create([
            'field_map' => [['source_field' => 'src', 'target_field' => null]],
        ]);
        $result = $this->transformer->transform(['src' => 'data'], $mapping);
        $this->assertEmpty($result['fields']);
    }

    public function test_transform_source_value_missing(): void
    {
        $mapping = MigrationTypeMapping::factory()->create([
            'field_map' => [['source_field' => 'missing_field', 'target_field' => 'target']],
        ]);
        $result = $this->transformer->transform(['other_field' => 'data'], $mapping);
        $this->assertEmpty($result['fields']);
    }
}
