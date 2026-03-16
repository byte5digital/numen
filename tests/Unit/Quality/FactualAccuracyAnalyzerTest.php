<?php

namespace Tests\Unit\Quality;

use App\Models\Content;
use App\Models\ContentVersion;
use App\Services\AI\LLMManager;
use App\Services\AI\LLMResponse;
use App\Services\Quality\Analyzers\FactualAccuracyAnalyzer;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class FactualAccuracyAnalyzerTest extends TestCase
{
    private function makeLLM(string $jsonResponse): LLMManager
    {
        $mock = $this->createMock(LLMManager::class);
        $mock->method('complete')->willReturn(new LLMResponse(
            content: $jsonResponse,
            model: 'claude-haiku-4-5-20251001',
            provider: 'anthropic',
            inputTokens: 150,
            outputTokens: 200,
            costUsd: 0.002,
            latencyMs: 300,
        ));

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
        $analyzer = new FactualAccuracyAnalyzer($this->createMock(LLMManager::class));
        $result = $analyzer->analyze($this->makeNoVersion());

        $this->assertSame(0.0, $result->getScore());
        $this->assertSame('error', $result->getItems()[0]['type']);
    }

    public function test_parses_llm_response_correctly(): void
    {
        $json = json_encode([
            'score' => 78,
            'verifiable_claims_ratio' => 0.75,
            'has_source_citations' => true,
            'claims' => [
                ['claim' => 'The company was founded in 2010.', 'verifiable' => true, 'confidence' => 0.9, 'issue' => null, 'suggestion' => null],
                ['claim' => 'Revenue grew by 500%.', 'verifiable' => false, 'confidence' => 0.3, 'issue' => 'No source for this statistic.', 'suggestion' => 'Cite the source.'],
            ],
            'summary' => 'Mostly accurate with one unsupported claim.',
        ]);

        $ref = new ReflectionMethod(FactualAccuracyAnalyzer::class, 'buildResultFromLLMData');
        $ref->setAccessible(true);

        $analyzer = new FactualAccuracyAnalyzer($this->createMock(LLMManager::class));

        /** @var array<string, mixed> $data */
        $data = json_decode((string) $json, true);
        $result = $ref->invoke($analyzer, $data);

        $this->assertSame(78.0, $result->getScore());
        $this->assertSame(0.75, $result->getMetadata()['verifiable_claims_ratio']);
        $this->assertTrue($result->getMetadata()['has_source_citations']);
        $this->assertSame('llm', $result->getMetadata()['source']);
        // One unverifiable claim should generate an item
        $this->assertCount(1, $result->getItems());
        $this->assertSame('error', $result->getItems()[0]['type']);
    }

    public function test_adds_citation_warning_when_no_citations(): void
    {
        $json = json_encode([
            'score' => 60,
            'verifiable_claims_ratio' => 0.5,
            'has_source_citations' => false,
            'claims' => [
                ['claim' => 'Example claim.', 'verifiable' => true, 'confidence' => 0.8, 'issue' => null, 'suggestion' => null],
            ],
            'summary' => 'Missing citations.',
        ]);

        $ref = new ReflectionMethod(FactualAccuracyAnalyzer::class, 'buildResultFromLLMData');
        $ref->setAccessible(true);

        $analyzer = new FactualAccuracyAnalyzer($this->createMock(LLMManager::class));

        /** @var array<string, mixed> $data */
        $data = json_decode((string) $json, true);
        $result = $ref->invoke($analyzer, $data);

        $types = array_column($result->getItems(), 'type');
        $this->assertContains('warning', $types);
    }

    public function test_heuristic_fallback_detects_citations(): void
    {
        $analyzer = new FactualAccuracyAnalyzer($this->createMock(LLMManager::class));

        $ref = new ReflectionMethod(FactualAccuracyAnalyzer::class, 'heuristicFallback');
        $ref->setAccessible(true);

        $textWithCitation = 'According to a 2023 study, 75% of users prefer mobile apps. See https://example.com/study';
        $result = $ref->invoke($analyzer, $textWithCitation, 'Test');

        $this->assertTrue($result->getMetadata()['has_source_citations']);
        $this->assertGreaterThanOrEqual(60, $result->getScore());
    }

    public function test_heuristic_fallback_penalizes_missing_citations(): void
    {
        $analyzer = new FactualAccuracyAnalyzer($this->createMock(LLMManager::class));

        $ref = new ReflectionMethod(FactualAccuracyAnalyzer::class, 'heuristicFallback');
        $ref->setAccessible(true);

        $textWithoutCitation = 'Revenue grew by 300%. Profits increased by 500%. The market expanded by 200% in 2022. User growth hit 1000%. Customer satisfaction improved by 150%.';
        $result = $ref->invoke($analyzer, $textWithoutCitation, 'Test');

        $this->assertFalse($result->getMetadata()['has_source_citations']);
        $this->assertLessThan(60, $result->getScore());
    }

    public function test_falls_back_on_llm_exception(): void
    {
        $analyzer = new FactualAccuracyAnalyzer($this->createMock(LLMManager::class));

        $ref = new ReflectionMethod(FactualAccuracyAnalyzer::class, 'heuristicFallback');
        $ref->setAccessible(true);

        $result = $ref->invoke($analyzer, 'Some content with facts.', 'LLM unavailable — using heuristic fallback.');
        $this->assertSame('heuristic', $result->getMetadata()['source']);
    }

    public function test_dimension_and_weight(): void
    {
        $analyzer = new FactualAccuracyAnalyzer($this->createMock(LLMManager::class));
        $this->assertSame('factual_accuracy', $analyzer->getDimension());
        $this->assertSame(0.20, $analyzer->getWeight());
    }
}
