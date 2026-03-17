<?php

declare(strict_types=1);

namespace Tests\Unit\Migration;

use App\Models\Migration\MigrationTypeMapping;
use App\Services\Migration\ContentTransformerService;
use Tests\TestCase;

class ContentTransformerServiceTest extends TestCase
{
    private ContentTransformerService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ContentTransformerService;
    }

    private function makeMapping(array $fieldMap): MigrationTypeMapping
    {
        $mapping = new MigrationTypeMapping;
        $mapping->forceFill(['field_map' => $fieldMap]);

        return $mapping;
    }

    public function test_transforms_string_fields(): void
    {
        $mapping = $this->makeMapping([
            ['source_field' => 'title', 'target_field' => 'title', 'source_type' => 'string'],
        ]);

        $result = $this->service->transform(['title' => 'Hello World'], $mapping);

        $this->assertSame('Hello World', $result['fields']['title']);
        $this->assertEmpty($result['media_refs']);
        $this->assertEmpty($result['taxonomy_refs']);
    }

    public function test_transforms_richtext_and_extracts_media(): void
    {
        $mapping = $this->makeMapping([
            ['source_field' => 'body', 'target_field' => 'content', 'source_type' => 'richtext'],
        ]);

        $html = '<p>Hello</p><img src="https://example.com/photo.jpg" alt="Photo" />';
        $result = $this->service->transform(['body' => $html], $mapping);

        $this->assertStringContainsString('<p>Hello</p>', $result['fields']['content']);
        $this->assertContains('https://example.com/photo.jpg', $result['media_refs']);
    }

    public function test_transforms_markdown_to_html(): void
    {
        $mapping = $this->makeMapping([
            ['source_field' => 'body', 'target_field' => 'content', 'source_type' => 'markdown'],
        ]);

        $md = "# Hello\n\n![alt](https://example.com/img.png)";
        $result = $this->service->transform(['body' => $md], $mapping);

        $this->assertIsString($result['fields']['content']);
        $this->assertContains('https://example.com/img.png', $result['media_refs']);
    }

    public function test_transforms_media_string_ref(): void
    {
        $mapping = $this->makeMapping([
            ['source_field' => 'image', 'target_field' => 'hero_image', 'source_type' => 'media'],
        ]);

        $result = $this->service->transform(['image' => 'https://cdn.example.com/img.jpg'], $mapping);

        $this->assertSame('https://cdn.example.com/img.jpg', $result['fields']['hero_image']);
        $this->assertContains('https://cdn.example.com/img.jpg', $result['media_refs']);
    }

    public function test_transforms_media_array_ref(): void
    {
        $mapping = $this->makeMapping([
            ['source_field' => 'image', 'target_field' => 'hero_image', 'source_type' => 'media'],
        ]);

        $result = $this->service->transform(['image' => ['url' => 'https://cdn.example.com/img.jpg']], $mapping);

        $this->assertSame('https://cdn.example.com/img.jpg', $result['fields']['hero_image']);
        $this->assertContains('https://cdn.example.com/img.jpg', $result['media_refs']);
    }

    public function test_transforms_number_fields(): void
    {
        $mapping = $this->makeMapping([
            ['source_field' => 'count', 'target_field' => 'count', 'source_type' => 'number'],
            ['source_field' => 'price', 'target_field' => 'price', 'source_type' => 'number'],
        ]);

        $result = $this->service->transform(['count' => '42', 'price' => '19.99'], $mapping);

        $this->assertSame(42, $result['fields']['count']);
        $this->assertSame(19.99, $result['fields']['price']);
    }

    public function test_transforms_boolean_fields(): void
    {
        $mapping = $this->makeMapping([
            ['source_field' => 'active', 'target_field' => 'is_active', 'source_type' => 'boolean'],
        ]);

        $result = $this->service->transform(['active' => 'true'], $mapping);

        $this->assertTrue($result['fields']['is_active']);
    }

    public function test_transforms_date_fields(): void
    {
        $mapping = $this->makeMapping([
            ['source_field' => 'published', 'target_field' => 'published_at', 'source_type' => 'date'],
        ]);

        $result = $this->service->transform(['published' => '2025-01-15 10:00:00'], $mapping);

        $this->assertStringContainsString('2025-01-15', $result['fields']['published_at']);
    }

    public function test_transforms_taxonomy_relations(): void
    {
        $mapping = $this->makeMapping([
            ['source_field' => 'categories', 'target_field' => 'categories', 'source_type' => 'taxonomy'],
        ]);

        $result = $this->service->transform([
            'categories' => [['id' => 'cat-1'], ['id' => 'cat-2']],
        ], $mapping);

        $this->assertSame(['cat-1', 'cat-2'], $result['fields']['categories']);
        $this->assertSame(['cat-1', 'cat-2'], $result['taxonomy_refs']);
    }

    public function test_skips_null_source_values(): void
    {
        $mapping = $this->makeMapping([
            ['source_field' => 'missing', 'target_field' => 'out', 'source_type' => 'string'],
        ]);

        $result = $this->service->transform(['other' => 'value'], $mapping);

        $this->assertArrayNotHasKey('out', $result['fields']);
    }

    public function test_skips_entries_without_target_field(): void
    {
        $mapping = $this->makeMapping([
            ['source_field' => 'title', 'target_field' => null, 'source_type' => 'string'],
        ]);

        $result = $this->service->transform(['title' => 'Hello'], $mapping);

        $this->assertEmpty($result['fields']);
    }

    public function test_custom_transformer_overrides_default(): void
    {
        $this->service->registerTransformer('string', fn ($val) => strtoupper((string) $val));

        $mapping = $this->makeMapping([
            ['source_field' => 'title', 'target_field' => 'title', 'source_type' => 'string'],
        ]);

        $result = $this->service->transform(['title' => 'hello'], $mapping);

        $this->assertSame('HELLO', $result['fields']['title']);
    }

    public function test_transforms_block_based_richtext(): void
    {
        $mapping = $this->makeMapping([
            ['source_field' => 'body', 'target_field' => 'content', 'source_type' => 'richtext'],
        ]);

        $blocks = [
            ['type' => 'h1', 'content' => 'Title'],
            ['type' => 'paragraph', 'content' => 'Text here'],
            ['type' => 'image', 'url' => 'https://example.com/pic.jpg', 'alt' => 'Pic'],
        ];

        $result = $this->service->transform(['body' => $blocks], $mapping);

        $this->assertStringContainsString('<h1>Title</h1>', $result['fields']['content']);
        $this->assertStringContainsString('<p>Text here</p>', $result['fields']['content']);
        $this->assertContains('https://example.com/pic.jpg', $result['media_refs']);
    }

    public function test_transforms_json_string(): void
    {
        $mapping = $this->makeMapping([
            ['source_field' => 'meta', 'target_field' => 'metadata', 'source_type' => 'json'],
        ]);

        $result = $this->service->transform(['meta' => '{"key":"value"}'], $mapping);

        $this->assertSame(['key' => 'value'], $result['fields']['metadata']);
    }

    public function test_transforms_json_array(): void
    {
        $mapping = $this->makeMapping([
            ['source_field' => 'meta', 'target_field' => 'metadata', 'source_type' => 'json'],
        ]);

        $result = $this->service->transform(['meta' => ['key' => 'value']], $mapping);

        $this->assertSame(['key' => 'value'], $result['fields']['metadata']);
    }
}
