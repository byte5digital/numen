<?php

declare(strict_types=1);

namespace Tests\Unit\Migration;

use App\Services\AI\LLMManager;
use App\Services\AI\LLMResponse;
use App\Services\Migration\AiFieldMappingService;
use Mockery;
use Tests\TestCase;

class AiFieldMappingServiceTest extends TestCase
{
    private function sourceType(): array
    {
        return [
            'key' => 'post',
            'label' => 'Blog Post',
            'fields' => [
                ['name' => 'title', 'type' => 'string', 'required' => true],
                ['name' => 'content', 'type' => 'richtext', 'required' => false],
                ['name' => 'slug', 'type' => 'string', 'required' => true],
                ['name' => 'featured_image', 'type' => 'media', 'required' => false],
                ['name' => 'status', 'type' => 'enum', 'required' => true],
            ],
        ];
    }

    private function numenType(): array
    {
        return [
            'key' => 'article',
            'label' => 'Article',
            'fields' => [
                ['name' => 'title', 'type' => 'string', 'required' => true],
                ['name' => 'body', 'type' => 'richtext', 'required' => false],
                ['name' => 'slug', 'type' => 'string', 'required' => true],
                ['name' => 'image', 'type' => 'media', 'required' => false],
                ['name' => 'status', 'type' => 'enum', 'required' => true],
            ],
        ];
    }

    public function test_suggest_uses_ai_when_available(): void
    {
        $aiJson = json_encode([
            ['source_field' => 'title', 'target_field' => 'title', 'confidence' => 0.99, 'requires_transform' => false],
            ['source_field' => 'content', 'target_field' => 'body', 'confidence' => 0.9, 'requires_transform' => false],
            ['source_field' => 'slug', 'target_field' => 'slug', 'confidence' => 0.99, 'requires_transform' => false],
            ['source_field' => 'featured_image', 'target_field' => 'image', 'confidence' => 0.85, 'requires_transform' => false],
            ['source_field' => 'status', 'target_field' => 'status', 'confidence' => 0.95, 'requires_transform' => false],
        ]);

        $llmResponse = new LLMResponse(
            content: $aiJson,
            model: 'claude-haiku-4-5-20251001',
            provider: 'anthropic',
            inputTokens: 100,
            outputTokens: 50,
            costUsd: 0.001,
            latencyMs: 500,
        );

        $llm = Mockery::mock(LLMManager::class);
        $llm->shouldReceive('complete')->once()->andReturn($llmResponse);

        $service = new AiFieldMappingService($llm);
        $result = $service->suggest($this->sourceType(), $this->numenType());

        $this->assertCount(5, $result);
        $this->assertSame('title', $result[0]['source_field']);
        $this->assertSame('title', $result[0]['target_field']);
        $this->assertSame(0.99, $result[0]['confidence']);
        $this->assertFalse($result[0]['requires_transform']);
    }

    public function test_suggest_falls_back_to_rule_based_on_ai_failure(): void
    {
        $llm = Mockery::mock(LLMManager::class);
        $llm->shouldReceive('complete')->once()->andThrow(new \RuntimeException('API unavailable'));

        $service = new AiFieldMappingService($llm);
        $result = $service->suggest($this->sourceType(), $this->numenType());

        $this->assertNotEmpty($result);
        // Title should match exactly
        $titleMapping = collect($result)->firstWhere('source_field', 'title');
        $this->assertNotNull($titleMapping);
        $this->assertSame('title', $titleMapping['target_field']);
        $this->assertGreaterThanOrEqual(0.5, $titleMapping['confidence']);
    }

    public function test_rule_based_matches_exact_names(): void
    {
        $llm = Mockery::mock(LLMManager::class);
        $service = new AiFieldMappingService($llm);

        $result = $service->suggestRuleBased($this->sourceType(), $this->numenType());

        // Title => title (exact match)
        $title = collect($result)->firstWhere('source_field', 'title');
        $this->assertSame('title', $title['target_field']);
        $this->assertSame(1.0, $title['confidence']);
        $this->assertFalse($title['requires_transform']);

        // Slug => slug (exact match)
        $slug = collect($result)->firstWhere('source_field', 'slug');
        $this->assertSame('slug', $slug['target_field']);
        $this->assertSame(1.0, $slug['confidence']);

        // Status => status (exact match)
        $status = collect($result)->firstWhere('source_field', 'status');
        $this->assertSame('status', $status['target_field']);
    }

    public function test_rule_based_matches_synonyms(): void
    {
        $llm = Mockery::mock(LLMManager::class);
        $service = new AiFieldMappingService($llm);

        $result = $service->suggestRuleBased($this->sourceType(), $this->numenType());

        // content => body (synonym match)
        $content = collect($result)->firstWhere('source_field', 'content');
        $this->assertSame('body', $content['target_field']);
        $this->assertGreaterThanOrEqual(0.3, $content['confidence']);

        // featured_image => image (synonym match)
        $image = collect($result)->firstWhere('source_field', 'featured_image');
        $this->assertSame('image', $image['target_field']);
        $this->assertGreaterThanOrEqual(0.3, $image['confidence']);
    }

    public function test_rule_based_returns_null_for_unmatched(): void
    {
        $llm = Mockery::mock(LLMManager::class);
        $service = new AiFieldMappingService($llm);

        $source = [
            'key' => 'post',
            'label' => 'Post',
            'fields' => [
                ['name' => 'custom_xyz_widget', 'type' => 'json', 'required' => false],
            ],
        ];

        $numen = [
            'key' => 'article',
            'label' => 'Article',
            'fields' => [
                ['name' => 'title', 'type' => 'string', 'required' => true],
            ],
        ];

        $result = $service->suggestRuleBased($source, $numen);
        $this->assertCount(1, $result);
        $this->assertNull($result[0]['target_field']);
        $this->assertSame(0.0, $result[0]['confidence']);
    }

    public function test_confidence_scoring_ranges(): void
    {
        $llm = Mockery::mock(LLMManager::class);
        $service = new AiFieldMappingService($llm);

        $result = $service->suggestRuleBased($this->sourceType(), $this->numenType());

        foreach ($result as $mapping) {
            $this->assertGreaterThanOrEqual(0.0, $mapping['confidence']);
            $this->assertLessThanOrEqual(1.0, $mapping['confidence']);
            $this->assertIsBool($mapping['requires_transform']);
        }
    }

    public function test_ai_response_with_markdown_fences_parsed(): void
    {
        $aiJson = "```json\n".json_encode([
            ['source_field' => 'title', 'target_field' => 'title', 'confidence' => 0.95, 'requires_transform' => false],
        ])."\n```";

        $llmResponse = new LLMResponse(
            content: $aiJson,
            model: 'claude-haiku-4-5-20251001',
            provider: 'anthropic',
            inputTokens: 50,
            outputTokens: 30,
            costUsd: 0.0005,
            latencyMs: 300,
        );

        $llm = Mockery::mock(LLMManager::class);
        $llm->shouldReceive('complete')->once()->andReturn($llmResponse);

        $service = new AiFieldMappingService($llm);
        $result = $service->suggest($this->sourceType(), $this->numenType());

        $title = collect($result)->firstWhere('source_field', 'title');
        $this->assertSame('title', $title['target_field']);
    }

    public function test_suggest_all_maps_multiple_types(): void
    {
        $llm = Mockery::mock(LLMManager::class);
        $llm->shouldReceive('complete')->andThrow(new \RuntimeException('No AI'));

        $service = new AiFieldMappingService($llm);

        $sourceTypes = [
            $this->sourceType(),
            [
                'key' => 'page',
                'label' => 'Page',
                'fields' => [
                    ['name' => 'title', 'type' => 'string', 'required' => true],
                    ['name' => 'body', 'type' => 'richtext', 'required' => false],
                ],
            ],
        ];

        $numenTypes = [
            $this->numenType(),
            [
                'key' => 'page',
                'label' => 'Page',
                'fields' => [
                    ['name' => 'title', 'type' => 'string', 'required' => true],
                    ['name' => 'body', 'type' => 'richtext', 'required' => false],
                ],
            ],
        ];

        $result = $service->suggestAll($sourceTypes, $numenTypes);
        $this->assertCount(2, $result);

        // Page should match page exactly
        $pageMapping = collect($result)->firstWhere('source_type', 'page');
        $this->assertSame('page', $pageMapping['numen_type']);
    }
}
