<?php

namespace Tests\Unit\Competitor;

use App\Models\CompetitorContentItem;
use App\Models\ContentBrief;
use App\Models\ContentFingerprint;
use App\Models\DifferentiationAnalysis;
use App\Services\AI\LLMManager;
use App\Services\AI\LLMResponse;
use App\Services\Competitor\ContentFingerprintService;
use App\Services\Competitor\DifferentiationAnalysisService;
use App\Services\Competitor\SimilarContentFinder;
use App\Services\Competitor\SimilarityCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class DifferentiationAnalysisServiceTest extends TestCase
{
    use RefreshDatabase;

    private LLMManager $llm;

    private SimilarityCalculator $calculator;

    private ContentFingerprintService $fingerprintService;

    private DifferentiationAnalysisService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $llmJson = json_encode([
            'angles' => ['Take a beginner-friendly approach', 'Focus on real-world examples'],
            'gaps' => ['Missing cost comparison', 'No performance benchmarks'],
            'recommendations' => ['Add a comparison table', 'Include user testimonials'],
        ]);

        $this->llm = Mockery::mock(LLMManager::class);
        $this->llm->shouldReceive('complete')
            ->andReturn(new LLMResponse(
                content: $llmJson,
                model: 'claude-haiku-4-5-20251001',
                provider: 'anthropic',
                inputTokens: 100,
                outputTokens: 80,
                costUsd: 0.001,
                latencyMs: 500,
            ));

        $this->calculator = new SimilarityCalculator;
        $this->fingerprintService = new ContentFingerprintService;
        $this->service = new DifferentiationAnalysisService($this->llm, $this->calculator, $this->fingerprintService);
    }

    private function makeCompetitorEntry(array $topics = [], array $keywords = []): array
    {
        $item = CompetitorContentItem::factory()->create([
            'title' => 'Competitor article about '.implode(', ', $topics ?: ['general topic']),
            'excerpt' => 'A comprehensive guide.',
            'body' => 'This article covers '.implode(', ', $topics ?: ['various topics']).' in depth.',
        ]);

        $fingerprint = ContentFingerprint::factory()->create([
            'fingerprintable_type' => CompetitorContentItem::class,
            'fingerprintable_id' => $item->id,
            'topics' => $topics,
            'entities' => [],
            'keywords' => array_fill_keys($keywords, 0.5),
        ]);

        return ['item' => $item, 'score' => 0.6, 'fingerprint' => $fingerprint];
    }

    public function test_analyze_creates_differentiation_analysis_records(): void
    {
        $brief = ContentBrief::factory()->create([
            'title' => 'Guide to machine learning',
            'target_keywords' => ['machine learning', 'AI', 'neural networks'],
        ]);

        $entries = collect([$this->makeCompetitorEntry(
            topics: ['machine learning', 'deep learning'],
            keywords: ['neural', 'training', 'model']
        )]);

        $analyses = $this->service->analyze($brief, $entries);

        $this->assertCount(1, $analyses);
        $this->assertInstanceOf(DifferentiationAnalysis::class, $analyses->first());

        $analysis = $analyses->first();
        $this->assertNotNull($analysis->similarity_score);
        $this->assertNotNull($analysis->differentiation_score);
        $this->assertIsArray($analysis->angles);
        $this->assertIsArray($analysis->gaps);
        $this->assertIsArray($analysis->recommendations);
        $this->assertNotEmpty($analysis->angles);
        $this->assertNotEmpty($analysis->recommendations);
    }

    public function test_analyze_scores_are_complementary(): void
    {
        $brief = ContentBrief::factory()->create([
            'title' => 'SEO best practices',
            'target_keywords' => ['SEO', 'backlinks'],
        ]);

        $entries = collect([$this->makeCompetitorEntry(
            topics: ['SEO', 'search ranking'],
            keywords: ['backlinks', 'keywords', 'meta']
        )]);

        $analyses = $this->service->analyze($brief, $entries);

        $analysis = $analyses->first();
        $sum = $analysis->similarity_score + $analysis->differentiation_score;
        $this->assertEqualsWithDelta(1.0, $sum, 0.01);
    }

    public function test_analyze_persists_to_database(): void
    {
        $brief = ContentBrief::factory()->create([
            'title' => 'Laravel tips',
        ]);

        $entries = collect([$this->makeCompetitorEntry(topics: ['laravel', 'php'])]);

        $this->service->analyze($brief, $entries);

        $this->assertDatabaseHas('differentiation_analyses', [
            'brief_id' => $brief->id,
            'space_id' => $brief->space_id,
        ]);
    }

    public function test_enrich_brief_adds_competitor_context(): void
    {
        $brief = ContentBrief::factory()->create([
            'title' => 'Guide to Docker containers',
            'target_keywords' => ['docker', 'containers', 'devops'],
            'requirements' => [],
        ]);

        // Set up a fingerprint for the brief via fingerprintService
        ContentFingerprint::factory()->create([
            'fingerprintable_type' => ContentBrief::class,
            'fingerprintable_id' => $brief->id,
            'topics' => ['docker', 'containers'],
            'keywords' => ['docker' => 0.8, 'containers' => 0.7, 'devops' => 0.5],
        ]);

        $competitorItem = CompetitorContentItem::factory()->create([
            'title' => 'Docker for beginners',
            'body' => 'Learn Docker from scratch.',
        ]);
        $competitorFp = ContentFingerprint::factory()->create([
            'fingerprintable_type' => CompetitorContentItem::class,
            'fingerprintable_id' => $competitorItem->id,
            'topics' => ['docker', 'containers'],
            'keywords' => ['docker' => 0.9, 'containers' => 0.6],
        ]);

        $finderMock = Mockery::mock(SimilarContentFinder::class);
        $finderMock->shouldReceive('findSimilar')
            ->once()
            ->andReturn(collect([
                ['item' => $competitorItem, 'score' => 0.7, 'fingerprint' => $competitorFp],
            ]));

        $enriched = $this->service->enrichBrief($brief, $finderMock);

        $this->assertNotNull($enriched->requirements);
        $this->assertArrayHasKey('competitor_differentiation', $enriched->requirements);

        $ctx = $enriched->requirements['competitor_differentiation'];
        $this->assertArrayHasKey('competitor_count', $ctx);
        $this->assertArrayHasKey('avg_similarity_score', $ctx);
        $this->assertArrayHasKey('avg_differentiation_score', $ctx);
        $this->assertArrayHasKey('unique_angles', $ctx);
        $this->assertArrayHasKey('content_gaps', $ctx);
        $this->assertArrayHasKey('differentiation_recommendations', $ctx);
        $this->assertSame(1, $ctx['competitor_count']);
    }

    public function test_enrich_brief_with_no_similar_content_returns_unchanged(): void
    {
        $brief = ContentBrief::factory()->create([
            'title' => 'Niche topic with zero competition',
            'requirements' => ['existing' => 'value'],
        ]);

        $finderMock = Mockery::mock(SimilarContentFinder::class);
        $finderMock->shouldReceive('findSimilar')
            ->once()
            ->andReturn(collect());

        $enriched = $this->service->enrichBrief($brief, $finderMock);

        $this->assertArrayNotHasKey('competitor_differentiation', $enriched->requirements ?? []);
        $this->assertSame('value', ($enriched->requirements ?? [])['existing']);
    }

    public function test_analyze_handles_multiple_competitors(): void
    {
        $brief = ContentBrief::factory()->create([
            'title' => 'Cloud computing guide',
            'target_keywords' => ['cloud', 'AWS', 'Azure'],
        ]);

        $entries = collect([
            $this->makeCompetitorEntry(topics: ['cloud', 'AWS'], keywords: ['cloud', 'aws', 'serverless']),
            $this->makeCompetitorEntry(topics: ['cloud', 'Azure'], keywords: ['cloud', 'azure', 'microsoft']),
        ]);

        $analyses = $this->service->analyze($brief, $entries);

        $this->assertCount(2, $analyses);
        $this->assertDatabaseCount('differentiation_analyses', 2);
    }
}
