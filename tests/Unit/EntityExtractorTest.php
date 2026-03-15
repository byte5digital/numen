<?php

namespace Tests\Unit;

use App\Models\Content;
use App\Models\ContentType;
use App\Models\ContentVersion;
use App\Models\Space;
use App\Services\AI\CostTracker;
use App\Services\AI\LLMManager;
use App\Services\AI\LLMResponse;
use App\Services\Graph\EntityExtractor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EntityExtractorTest extends TestCase
{
    use RefreshDatabase;

    private LLMManager $llmManager;

    private CostTracker $costTracker;

    private EntityExtractor $extractor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->llmManager = $this->createMock(LLMManager::class);
        $this->costTracker = $this->createMock(CostTracker::class);
        $this->extractor = new EntityExtractor($this->llmManager, $this->costTracker);
    }

    public function test_extracts_entities_from_content(): void
    {
        $content = $this->makeContentWithVersion(
            'AI and Machine Learning in Content Strategy',
            '<p>Artificial intelligence is transforming digital marketing.</p>'
        );

        $jsonResponse = json_encode([
            ['entity' => 'Artificial Intelligence', 'type' => 'concept', 'weight' => 0.9],
            ['entity' => 'Machine Learning', 'type' => 'concept', 'weight' => 0.8],
            ['entity' => 'Content Strategy', 'type' => 'topic', 'weight' => 0.7],
        ]);

        $llmResponse = new LLMResponse(
            content: $jsonResponse,
            model: 'claude-haiku-4-5-20251001',
            provider: 'anthropic',
            inputTokens: 100,
            outputTokens: 50,
            costUsd: 0.001,
            latencyMs: 200,
        );

        $this->llmManager
            ->expects($this->once())
            ->method('complete')
            ->willReturn($llmResponse);

        $this->costTracker->method('calculateCost')->willReturn(0.001);
        $this->costTracker->method('recordUsage')->willReturn(true);

        $result = $this->extractor->extract($content);

        $this->assertCount(3, $result);
        $this->assertEquals('Artificial Intelligence', $result[0]['entity']);
        $this->assertEquals('concept', $result[0]['type']);
        $this->assertEquals(0.9, $result[0]['weight']);
    }

    public function test_handles_empty_content(): void
    {
        // Content with no current version → returns []
        $content = Content::factory()->create();

        $this->llmManager->expects($this->never())->method('complete');

        $result = $this->extractor->extract($content);

        $this->assertSame([], $result);
    }

    public function test_handles_llm_failure_gracefully(): void
    {
        $content = $this->makeContentWithVersion('Some Title', 'Some body content');

        $this->llmManager
            ->method('complete')
            ->willThrowException(new \RuntimeException('LLM provider unavailable'));

        $result = $this->extractor->extract($content);

        $this->assertSame([], $result);
    }

    // -----------------------------------------------------------------------
    // Helper
    // -----------------------------------------------------------------------

    private function makeContentWithVersion(string $title, string $body): Content
    {
        $space = Space::factory()->create();
        $contentType = ContentType::factory()->create(['space_id' => $space->id]);

        $content = Content::create([
            'space_id' => $space->id,
            'content_type_id' => $contentType->id,
            'slug' => \Illuminate\Support\Str::slug($title),
            'status' => 'published',
            'locale' => 'en',
        ]);

        $version = ContentVersion::create([
            'content_id' => $content->id,
            'version_number' => 1,
            'title' => $title,
            'excerpt' => '',
            'body' => $body,
            'body_format' => 'html',
            'author_type' => 'ai_agent',
            'author_id' => 'test',
        ]);

        $content->update(['current_version_id' => $version->id]);

        return $content->fresh(['currentVersion']);
    }
}
