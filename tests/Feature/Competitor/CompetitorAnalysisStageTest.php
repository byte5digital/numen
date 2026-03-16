<?php

namespace Tests\Feature\Competitor;

use App\Models\CompetitorContentItem;
use App\Models\ContentBrief;
use App\Models\ContentFingerprint;
use App\Models\ContentPipeline;
use App\Models\PipelineRun;
use App\Models\Space;
use App\Pipelines\Stages\CompetitorAnalysisStage;
use App\Plugin\HookRegistry;
use App\Services\AI\LLMManager;
use App\Services\AI\LLMResponse;
use App\Services\Competitor\ContentFingerprintService;
use App\Services\Competitor\DifferentiationAnalysisService;
use App\Services\Competitor\SimilarContentFinder;
use App\Services\Competitor\SimilarityCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class CompetitorAnalysisStageTest extends TestCase
{
    use RefreshDatabase;

    private Space $space;

    private ContentBrief $brief;

    private ContentPipeline $pipeline;

    private PipelineRun $run;

    private CompetitorAnalysisStage $stage;

    private LLMManager $llm;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Space $space */
        $space = Space::factory()->create();
        $this->space = $space;

        /** @var ContentBrief $brief */
        $brief = ContentBrief::factory()->create([
            'space_id' => $this->space->id,
            'title' => 'Getting Started with Machine Learning',
            'target_keywords' => ['machine learning', 'beginner guide'],
        ]);
        $this->brief = $brief;

        /** @var ContentPipeline $pipeline */
        $pipeline = ContentPipeline::factory()->create([
            'space_id' => $this->space->id,
            'stages' => [
                ['name' => 'competitor_analysis', 'type' => 'competitor_analysis'],
                ['name' => 'generate', 'type' => 'ai_generate'],
            ],
        ]);
        $this->pipeline = $pipeline;

        $this->run = PipelineRun::create([
            'pipeline_id' => $this->pipeline->id,
            'content_brief_id' => $this->brief->id,
            'status' => 'running',
            'current_stage' => 'competitor_analysis',
            'stage_results' => [],
            'context' => ['brief' => $this->brief->toArray()],
            'started_at' => now(),
        ]);

        $llmJson = json_encode([
            'angles' => ['Beginner-friendly tone', 'Practical examples'],
            'gaps' => ['Missing cost comparison', 'No code samples'],
            'recommendations' => ['Add comparison table', 'Include code snippets'],
        ]);

        /** @var LLMManager&\Mockery\MockInterface $llm */
        $llm = Mockery::mock(LLMManager::class);
        $this->llm = $llm;
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

        $calculator = new SimilarityCalculator;
        $fingerprintService = new ContentFingerprintService;
        $finder = new SimilarContentFinder($calculator);
        $analysisService = new DifferentiationAnalysisService($this->llm, $calculator, $fingerprintService);

        $this->stage = new CompetitorAnalysisStage($analysisService, $fingerprintService, $finder);
    }

    // ── Static contract ────────────────────────────────────────────────────

    public function test_stage_type_is_competitor_analysis(): void
    {
        $this->assertSame('competitor_analysis', CompetitorAnalysisStage::type());
    }

    public function test_stage_label_is_set(): void
    {
        $this->assertNotEmpty(CompetitorAnalysisStage::label());
    }

    public function test_config_schema_has_expected_keys(): void
    {
        $schema = CompetitorAnalysisStage::configSchema();
        $this->assertArrayHasKey('enabled', $schema);
        $this->assertArrayHasKey('similarity_threshold', $schema);
        $this->assertArrayHasKey('max_competitors', $schema);
    }

    // ── Disabled config ────────────────────────────────────────────────────

    public function test_stage_skips_when_stage_config_disabled(): void
    {
        $result = $this->stage->handle($this->run, ['enabled' => false]);

        $this->assertTrue($result['skipped']);
        $this->assertSame('disabled', $result['reason']);
    }

    public function test_stage_skips_when_global_config_disabled(): void
    {
        config(['numen.competitor_analysis.enabled' => false]);

        $result = $this->stage->handle($this->run, []);

        $this->assertTrue($result['skipped']);
        $this->assertSame('disabled', $result['reason']);
    }

    // ── No competitors ─────────────────────────────────────────────────────

    public function test_stage_skips_gracefully_when_no_competitors_in_db(): void
    {
        // No competitor fingerprints exist → findSimilar returns empty
        $result = $this->stage->handle($this->run, []);

        $this->assertTrue($result['skipped']);
        $this->assertSame('no_similar_competitors', $result['reason']);
    }

    // ── Enrichment ─────────────────────────────────────────────────────────

    public function test_stage_enriches_brief_when_similar_competitors_exist(): void
    {
        $this->seedSimilarCompetitor();

        $result = $this->stage->handle($this->run, []);

        $this->assertTrue($result['enriched'] ?? false, 'Expected enriched=true but got: '.json_encode($result));
        $this->assertSame($this->brief->id, $result['brief_id']);
        $this->assertGreaterThanOrEqual(1, $result['competitor_count']);
    }

    public function test_stage_updates_run_context_with_competitor_data(): void
    {
        $this->seedSimilarCompetitor();

        $this->stage->handle($this->run, []);

        $this->run->refresh();
        $this->assertArrayHasKey('competitor_analysis', $this->run->context);
    }

    public function test_stage_enriches_brief_requirements(): void
    {
        $this->seedSimilarCompetitor();

        $this->stage->handle($this->run, []);

        $this->brief->refresh();
        $this->assertArrayHasKey('competitor_differentiation', $this->brief->requirements ?? []);
    }

    // ── Pipeline registration ──────────────────────────────────────────────

    public function test_stage_is_registered_in_hook_registry(): void
    {
        /** @var HookRegistry $registry */
        $registry = app(HookRegistry::class);

        $this->assertTrue($registry->hasPipelineStageHandler('competitor_analysis'));
        $this->assertSame(
            CompetitorAnalysisStage::class,
            $registry->getPipelineStageHandler('competitor_analysis'),
        );
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    private function seedSimilarCompetitor(): void
    {
        /** @var CompetitorContentItem $item */
        $item = CompetitorContentItem::factory()->create([
            'space_id' => $this->space->id,
            'title' => 'Machine Learning Basics for Beginners',
        ]);

        ContentFingerprint::factory()->create([
            'fingerprintable_type' => CompetitorContentItem::class,
            'fingerprintable_id' => $item->id,
            'topics' => ['machine learning', 'artificial intelligence'],
            'entities' => ['python', 'tensorflow'],
            'keywords' => ['machine learning', 'beginner', 'tutorial'],
        ]);

        // Brief's own fingerprint
        ContentFingerprint::factory()->create([
            'fingerprintable_type' => ContentBrief::class,
            'fingerprintable_id' => $this->brief->id,
            'topics' => ['machine learning', 'deep learning'],
            'entities' => ['python'],
            'keywords' => ['machine learning', 'beginner guide'],
        ]);
    }
}
