<?php

declare(strict_types=1);

namespace Tests\Unit\Migration;

use App\Services\AI\LLMManager;
use App\Services\Migration\AiFieldMappingService;
use App\Services\Migration\MappingPreviewService;
use App\Services\Migration\SchemaInspectorService;
use Mockery;
use Tests\TestCase;

class MappingPreviewServiceTest extends TestCase
{
    private function sourceSchema(): array
    {
        return [
            [
                'key' => 'post',
                'label' => 'Blog Post',
                'fields' => [
                    ['name' => 'title', 'type' => 'string', 'required' => true],
                    ['name' => 'content', 'type' => 'richtext', 'required' => false],
                    ['name' => 'slug', 'type' => 'string', 'required' => true],
                ],
            ],
        ];
    }

    private function numenSchema(): array
    {
        return [
            [
                'key' => 'article',
                'label' => 'Article',
                'fields' => [
                    ['name' => 'title', 'type' => 'string', 'required' => true],
                    ['name' => 'body', 'type' => 'richtext', 'required' => false],
                    ['name' => 'slug', 'type' => 'string', 'required' => true],
                ],
            ],
        ];
    }

    public function test_generate_preview_with_schema_returns_complete_structure(): void
    {
        $llm = Mockery::mock(LLMManager::class);
        $llm->shouldReceive('complete')->andThrow(new \RuntimeException('No AI'));

        $inspector = new SchemaInspectorService;
        $aiMapper = new AiFieldMappingService($llm);
        $service = new MappingPreviewService($inspector, $aiMapper);

        $result = $service->generatePreviewWithSchema(
            $this->sourceSchema(),
            $this->numenSchema(),
        );

        // Structure checks
        $this->assertArrayHasKey('comparison', $result);
        $this->assertArrayHasKey('type_mappings', $result);
        $this->assertArrayHasKey('summary', $result);

        // Comparison structure
        $this->assertArrayHasKey('matched_types', $result['comparison']);
        $this->assertArrayHasKey('unmatched_source', $result['comparison']);
        $this->assertArrayHasKey('unmatched_numen', $result['comparison']);

        // Summary structure
        $this->assertArrayHasKey('total_source_types', $result['summary']);
        $this->assertArrayHasKey('mapped_types', $result['summary']);
        $this->assertArrayHasKey('total_fields', $result['summary']);
        $this->assertArrayHasKey('mapped_fields', $result['summary']);
        $this->assertArrayHasKey('avg_confidence', $result['summary']);
    }

    public function test_summary_counts_are_accurate(): void
    {
        $llm = Mockery::mock(LLMManager::class);
        $llm->shouldReceive('complete')->andThrow(new \RuntimeException('No AI'));

        $inspector = new SchemaInspectorService;
        $aiMapper = new AiFieldMappingService($llm);
        $service = new MappingPreviewService($inspector, $aiMapper);

        $result = $service->generatePreviewWithSchema(
            $this->sourceSchema(),
            $this->numenSchema(),
        );

        $summary = $result['summary'];
        $this->assertSame(1, $summary['total_source_types']);
        $this->assertSame(3, $summary['total_fields']);
        $this->assertGreaterThanOrEqual(1, $summary['mapped_fields']);
        $this->assertGreaterThanOrEqual(0.0, $summary['avg_confidence']);
        $this->assertLessThanOrEqual(1.0, $summary['avg_confidence']);
    }

    public function test_type_mappings_contain_field_details(): void
    {
        $llm = Mockery::mock(LLMManager::class);
        $llm->shouldReceive('complete')->andThrow(new \RuntimeException('No AI'));

        $inspector = new SchemaInspectorService;
        $aiMapper = new AiFieldMappingService($llm);
        $service = new MappingPreviewService($inspector, $aiMapper);

        $result = $service->generatePreviewWithSchema(
            $this->sourceSchema(),
            $this->numenSchema(),
        );

        $this->assertCount(1, $result['type_mappings']);
        $tm = $result['type_mappings'][0];
        $this->assertSame('post', $tm['source_type']);
        $this->assertNotNull($tm['numen_type']);

        foreach ($tm['mappings'] as $fm) {
            $this->assertArrayHasKey('source_field', $fm);
            $this->assertArrayHasKey('target_field', $fm);
            $this->assertArrayHasKey('source_type', $fm);
            $this->assertArrayHasKey('target_type', $fm);
            $this->assertArrayHasKey('confidence', $fm);
            $this->assertArrayHasKey('requires_transform', $fm);
        }
    }

    public function test_empty_numen_schema_returns_unmatched(): void
    {
        $llm = Mockery::mock(LLMManager::class);
        $llm->shouldReceive('complete')->andThrow(new \RuntimeException('No AI'));

        $inspector = new SchemaInspectorService;
        $aiMapper = new AiFieldMappingService($llm);
        $service = new MappingPreviewService($inspector, $aiMapper);

        $result = $service->generatePreviewWithSchema(
            $this->sourceSchema(),
            [],
        );

        $this->assertSame(0, $result['summary']['mapped_types']);
        $this->assertSame(0, $result['summary']['mapped_fields']);
    }
}
