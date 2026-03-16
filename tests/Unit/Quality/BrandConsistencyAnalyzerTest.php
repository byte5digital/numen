<?php

namespace Tests\Unit\Quality;

use App\Models\Content;
use App\Models\ContentVersion;
use App\Services\AI\LLMManager;
use App\Services\AI\LLMResponse;
use App\Services\Quality\Analyzers\BrandConsistencyAnalyzer;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class BrandConsistencyAnalyzerTest extends TestCase
{
    private function makeLLM(?string $jsonResponse = null): LLMManager
    {
        $mock = $this->createMock(LLMManager::class);

        if ($jsonResponse !== null) {
            $mock->method('complete')->willReturn(new LLMResponse(
                content: $jsonResponse,
                model: 'claude-haiku-4-5-20251001',
                provider: 'anthropic',
                inputTokens: 100,
                outputTokens: 50,
                costUsd: 0.001,
                latencyMs: 200,
            ));
        }

        return $mock;
    }

    private function makeContent(string $body): Content
    {
        $version = new class($body) extends ContentVersion
        {
            /** @phpstan-ignore-next-line */
            public function __construct(public string $body) {}
        };

        return new class($version) extends Content
        {
            public ?ContentVersion $currentVersion;

            /** @phpstan-ignore-next-line */
            public ?ContentVersion $draftVersion = null;

            /** @phpstan-ignore-next-line */
            public function __construct(ContentVersion $v)
            {
                $this->currentVersion = $v;
            }

            public function pipelineRuns(): \Illuminate\Database\Eloquent\Relations\HasMany
            {
                /** @phpstan-ignore-next-line */
                return new class extends \Illuminate\Database\Eloquent\Relations\HasMany
                {
                    /** @phpstan-ignore-next-line */
                    public function __construct() {}

                    /** @phpstan-ignore-next-line */
                    public function with($relations): static
                    {
                        return $this;
                    }

                    /** @phpstan-ignore-next-line */
                    public function latest(?string $column = null): static
                    {
                        return $this;
                    }

                    /** @phpstan-ignore-next-line */
                    public function first($columns = ['*']): ?object
                    {
                        return null;
                    }
                };
            }
        };
    }

    private function makeNoVersion(): Content
    {
        return new class extends Content
        {
            public ?ContentVersion $currentVersion = null;

            public ?ContentVersion $draftVersion = null;

            /** @phpstan-ignore-next-line */
            public function __construct() {}
        };
    }

    public function test_returns_error_when_no_version(): void
    {
        $analyzer = new BrandConsistencyAnalyzer($this->makeLLM());
        $result = $analyzer->analyze($this->makeNoVersion());

        $this->assertSame(0.0, $result->getScore());
        $this->assertCount(1, $result->getItems());
        $this->assertSame('error', $result->getItems()[0]['type']);
    }

    public function test_returns_heuristic_fallback_when_no_persona(): void
    {
        $llm = $this->createMock(LLMManager::class);
        $llm->expects($this->never())->method('complete');

        $analyzer = new BrandConsistencyAnalyzer($llm);
        $content = $this->makeContent('We love helping our customers succeed every single day. Our team is dedicated to excellence.');
        $result = $analyzer->analyze($content);

        $this->assertGreaterThan(0, $result->getScore());
        $this->assertSame('heuristic', $result->getMetadata()['source']);
    }

    public function test_parses_llm_response_correctly(): void
    {
        $json = json_encode([
            'score' => 87,
            'tone_consistency' => 90,
            'vocabulary_alignment' => 85,
            'brand_voice_adherence' => 86,
            'deviations' => [
                [
                    'type' => 'tone',
                    'message' => 'Slightly too casual in paragraph 2.',
                    'suggestion' => 'Replace "gonna" with "going to".',
                ],
            ],
            'summary' => 'Content aligns well with brand voice.',
        ]);

        $analyzer = new BrandConsistencyAnalyzer($this->makeLLM($json));

        $ref = new ReflectionMethod(BrandConsistencyAnalyzer::class, 'buildResultFromLLMData');
        $ref->setAccessible(true);

        /** @var array<string, mixed> $data */
        $data = json_decode((string) $json, true);
        $result = $ref->invoke($analyzer, $data);

        $this->assertSame(87.0, $result->getScore());
        $this->assertCount(1, $result->getItems());
        $this->assertSame('llm', $result->getMetadata()['source']);
        $this->assertSame(90.0, $result->getMetadata()['tone_consistency']);
    }

    public function test_llm_response_without_deviations_is_clean(): void
    {
        $json = json_encode([
            'score' => 95,
            'tone_consistency' => 96,
            'vocabulary_alignment' => 94,
            'brand_voice_adherence' => 95,
            'deviations' => [],
            'summary' => 'Perfect brand alignment.',
        ]);

        $analyzer = new BrandConsistencyAnalyzer($this->makeLLM($json));

        $ref = new ReflectionMethod(BrandConsistencyAnalyzer::class, 'buildResultFromLLMData');
        $ref->setAccessible(true);

        /** @var array<string, mixed> $data */
        $data = json_decode((string) $json, true);
        $result = $ref->invoke($analyzer, $data);

        $this->assertSame(95.0, $result->getScore());
        $this->assertEmpty($result->getItems());
    }

    public function test_heuristic_penalizes_mixed_voice(): void
    {
        $llm = $this->createMock(LLMManager::class);
        $analyzer = new BrandConsistencyAnalyzer($llm);

        $ref = new ReflectionMethod(BrandConsistencyAnalyzer::class, 'heuristicFallback');
        $ref->setAccessible(true);

        $textMixedVoice = 'We are committed to helping our users. He said they should try it. I believe in our mission. Their team is great. Our company leads the way.';
        $result = $ref->invoke($analyzer, $textMixedVoice, 'Test fallback');

        $this->assertLessThan(75, $result->getScore());
    }

    public function test_heuristic_info_item_contains_reason(): void
    {
        $llm = $this->createMock(LLMManager::class);
        $analyzer = new BrandConsistencyAnalyzer($llm);

        $ref = new ReflectionMethod(BrandConsistencyAnalyzer::class, 'heuristicFallback');
        $ref->setAccessible(true);

        $result = $ref->invoke($analyzer, 'Short clean text.', 'No persona assigned — using heuristic fallback.');

        $messages = array_column($result->getItems(), 'message');
        $this->assertContains('No persona assigned — using heuristic fallback.', $messages);
    }

    public function test_dimension_and_weight(): void
    {
        $analyzer = new BrandConsistencyAnalyzer($this->makeLLM());
        $this->assertSame('brand_consistency', $analyzer->getDimension());
        $this->assertSame(0.20, $analyzer->getWeight());
    }
}
