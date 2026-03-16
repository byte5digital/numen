<?php

namespace Tests\Unit\Quality;

use App\Models\Content;
use App\Models\ContentVersion;
use App\Services\AI\LLMManager;
use App\Services\AI\LLMResponse;
use App\Services\Quality\Analyzers\EngagementPredictionAnalyzer;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class EngagementPredictionAnalyzerTest extends TestCase
{
    private function makeLLM(string $jsonResponse): LLMManager
    {
        $mock = $this->createMock(LLMManager::class);
        $mock->method('complete')->willReturn(new LLMResponse(
            content: $jsonResponse,
            model: 'claude-haiku-4-5-20251001',
            provider: 'anthropic',
            inputTokens: 120,
            outputTokens: 180,
            costUsd: 0.002,
            latencyMs: 250,
        ));

        return $mock;
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
        $analyzer = new EngagementPredictionAnalyzer($this->createMock(LLMManager::class));
        $result = $analyzer->analyze($this->makeNoVersion());

        $this->assertSame(0.0, $result->getScore());
        $this->assertSame('error', $result->getItems()[0]['type']);
    }

    public function test_parses_llm_response_correctly(): void
    {
        $json = json_encode([
            'score' => 82,
            'headline_strength' => 85,
            'hook_quality' => 80,
            'emotional_resonance' => 75,
            'cta_effectiveness' => 88,
            'shareability' => 82,
            'factors' => [
                [
                    'factor' => 'headline_strength',
                    'score' => 85,
                    'observation' => 'Strong headline with power words.',
                    'suggestion' => null,
                ],
                [
                    'factor' => 'emotional_resonance',
                    'score' => 45,
                    'observation' => 'Limited emotional language.',
                    'suggestion' => 'Add more emotional hooks.',
                ],
            ],
            'summary' => 'High engagement potential with strong headline.',
        ]);

        $ref = new ReflectionMethod(EngagementPredictionAnalyzer::class, 'buildResultFromLLMData');
        $ref->setAccessible(true);

        $analyzer = new EngagementPredictionAnalyzer($this->createMock(LLMManager::class));

        /** @var array<string, mixed> $data */
        $data = json_decode((string) $json, true);
        $result = $ref->invoke($analyzer, $data);

        $this->assertSame(82.0, $result->getScore());
        $this->assertSame(85.0, $result->getMetadata()['headline_strength']);
        $this->assertSame('llm', $result->getMetadata()['source']);
        // Only the low-scoring factor (45) should generate an item
        $this->assertCount(1, $result->getItems());
        $this->assertStringContainsString('emotional_resonance', $result->getItems()[0]['message']);
    }

    public function test_heuristic_detects_cta(): void
    {
        $analyzer = new EngagementPredictionAnalyzer($this->createMock(LLMManager::class));

        $ref = new ReflectionMethod(EngagementPredictionAnalyzer::class, 'heuristicFallback');
        $ref->setAccessible(true);

        $textWithCta = 'Discover the top 10 secrets to success. Amazing results await you. Sign up today to get started!';
        $result = $ref->invoke($analyzer, $textWithCta, 'Test');

        $this->assertSame('heuristic', $result->getMetadata()['source']);
        $this->assertGreaterThan(50, $result->getMetadata()['cta_effectiveness']);
        // Should not have a CTA warning
        $messages = array_column($result->getItems(), 'message');
        $ctaWarning = array_filter($messages, fn ($m) => str_contains($m, 'call-to-action'));
        $this->assertEmpty($ctaWarning);
    }

    public function test_heuristic_warns_missing_cta(): void
    {
        $analyzer = new EngagementPredictionAnalyzer($this->createMock(LLMManager::class));

        $ref = new ReflectionMethod(EngagementPredictionAnalyzer::class, 'heuristicFallback');
        $ref->setAccessible(true);

        $textNoCta = 'This is a very long article about nothing in particular. It contains many sentences but lacks any direction or purpose.';
        $result = $ref->invoke($analyzer, $textNoCta, 'Test');

        $messages = array_column($result->getItems(), 'message');
        $ctaWarning = array_filter($messages, fn ($m) => str_contains($m, 'call-to-action'));
        $this->assertNotEmpty($ctaWarning);
    }

    public function test_falls_back_on_llm_exception(): void
    {
        $analyzer = new EngagementPredictionAnalyzer($this->createMock(LLMManager::class));

        $ref = new ReflectionMethod(EngagementPredictionAnalyzer::class, 'heuristicFallback');
        $ref->setAccessible(true);

        $result = $ref->invoke($analyzer, 'How to succeed in 2024. Amazing tips inside! Sign up now.', 'LLM unavailable — using heuristic fallback.');
        $this->assertSame('heuristic', $result->getMetadata()['source']);
        $this->assertGreaterThan(0, $result->getScore());
    }

    public function test_heuristic_penalizes_short_headline(): void
    {
        $analyzer = new EngagementPredictionAnalyzer($this->createMock(LLMManager::class));

        $ref = new ReflectionMethod(EngagementPredictionAnalyzer::class, 'heuristicFallback');
        $ref->setAccessible(true);

        $textShortHeadline = "Hi\nThis is the body with lots of content and words to make it seem like a real article.";
        $result = $ref->invoke($analyzer, $textShortHeadline, 'Test');

        $this->assertLessThan(50, $result->getMetadata()['headline_strength']);
    }

    public function test_heuristic_rewards_optimal_headline(): void
    {
        $analyzer = new EngagementPredictionAnalyzer($this->createMock(LLMManager::class));

        $ref = new ReflectionMethod(EngagementPredictionAnalyzer::class, 'heuristicFallback');
        $ref->setAccessible(true);

        $textGoodHeadline = "How to Build the Best Content Strategy\nThis is the body with details about content strategy.";
        $result = $ref->invoke($analyzer, $textGoodHeadline, 'Test');

        $this->assertGreaterThanOrEqual(70, $result->getMetadata()['headline_strength']);
    }

    public function test_dimension_and_weight(): void
    {
        $analyzer = new EngagementPredictionAnalyzer($this->createMock(LLMManager::class));
        $this->assertSame('engagement_prediction', $analyzer->getDimension());
        $this->assertSame(0.20, $analyzer->getWeight());
    }
}
