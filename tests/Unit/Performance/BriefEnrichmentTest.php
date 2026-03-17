<?php

namespace Tests\Unit\Performance;

use App\Models\ContentBrief;
use App\Models\Performance\ContentAttribute;
use App\Models\Performance\ContentPerformanceSnapshot;
use App\Models\Performance\SpacePerformanceModel;
use App\Models\Space;
use App\Services\Performance\BriefEnrichmentService;
use App\Services\Performance\PerformanceCorrelatorService;
use App\Services\Performance\PerformanceInsightBuilder;
use App\Services\Performance\SpacePerformanceModelService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class BriefEnrichmentTest extends TestCase
{
    use RefreshDatabase;

    private BriefEnrichmentService $service;

    private Space $space;

    protected function setUp(): void
    {
        parent::setUp();

        $modelService = new SpacePerformanceModelService(
            new PerformanceCorrelatorService,
        );
        $insightBuilder = new PerformanceInsightBuilder($modelService);
        $this->service = new BriefEnrichmentService($insightBuilder);
        $this->space = Space::factory()->create();
    }

    public function test_enrich_brief_returns_unchanged_without_model(): void
    {
        $brief = ContentBrief::factory()->create([
            'space_id' => $this->space->id,
            'requirements' => ['existing' => 'value'],
        ]);

        $result = $this->service->enrichBrief($brief);

        $this->assertEquals(['existing' => 'value'], $result->requirements);
    }

    public function test_enrich_brief_adds_performance_recommendations(): void
    {
        $contentIds = $this->seedSpaceData();

        SpacePerformanceModel::factory()->create([
            'space_id' => $this->space->id,
            'top_performers' => $contentIds,
            'topic_scores' => ['seo' => 85.0, 'marketing' => 72.0],
            'attribute_weights' => ['word_count' => 0.45, 'image_count' => 0.35],
            'model_confidence' => 0.80,
        ]);

        $brief = ContentBrief::factory()->create([
            'space_id' => $this->space->id,
            'requirements' => [],
        ]);

        $result = $this->service->enrichBrief($brief);

        $this->assertArrayHasKey('performance_recommendations', $result->requirements);
        $recs = $result->requirements['performance_recommendations'];

        $this->assertEquals(0.80, $recs['model_confidence']);
        $this->assertArrayHasKey('generated_at', $recs);
    }

    public function test_enrich_brief_includes_optimal_word_count(): void
    {
        $contentIds = $this->seedSpaceData();

        SpacePerformanceModel::factory()->create([
            'space_id' => $this->space->id,
            'top_performers' => $contentIds,
            'topic_scores' => [],
            'attribute_weights' => ['word_count' => 0.45],
            'model_confidence' => 0.75,
        ]);

        $brief = ContentBrief::factory()->create([
            'space_id' => $this->space->id,
        ]);

        $result = $this->service->enrichBrief($brief);
        $recs = $result->requirements['performance_recommendations'];

        $this->assertArrayHasKey('optimal_word_count', $recs);
        $this->assertArrayHasKey('min', $recs['optimal_word_count']);
        $this->assertArrayHasKey('max', $recs['optimal_word_count']);
        $this->assertArrayHasKey('target', $recs['optimal_word_count']);
        $this->assertGreaterThan(0, $recs['optimal_word_count']['min']);
    }

    public function test_enrich_brief_includes_topics_to_emphasize(): void
    {
        SpacePerformanceModel::factory()->create([
            'space_id' => $this->space->id,
            'top_performers' => [],
            'topic_scores' => ['seo' => 85.0, 'marketing' => 72.0, 'ai' => 90.5],
            'attribute_weights' => [],
            'model_confidence' => 0.70,
        ]);

        $brief = ContentBrief::factory()->create([
            'space_id' => $this->space->id,
        ]);

        $result = $this->service->enrichBrief($brief);
        $recs = $result->requirements['performance_recommendations'];

        $this->assertArrayHasKey('topics_to_emphasize', $recs);
        $this->assertContains('ai', $recs['topics_to_emphasize']);
        $this->assertContains('seo', $recs['topics_to_emphasize']);
    }

    public function test_enrich_brief_includes_optimal_media_count_when_correlated(): void
    {
        SpacePerformanceModel::factory()->create([
            'space_id' => $this->space->id,
            'top_performers' => [],
            'topic_scores' => [],
            'attribute_weights' => ['image_count' => 0.50],
            'model_confidence' => 0.65,
        ]);

        $brief = ContentBrief::factory()->create([
            'space_id' => $this->space->id,
        ]);

        $result = $this->service->enrichBrief($brief);
        $recs = $result->requirements['performance_recommendations'];

        $this->assertArrayHasKey('optimal_media_count', $recs);
        $this->assertArrayHasKey('min', $recs['optimal_media_count']);
    }

    public function test_enrich_brief_preserves_existing_requirements(): void
    {
        SpacePerformanceModel::factory()->create([
            'space_id' => $this->space->id,
            'model_confidence' => 0.60,
        ]);

        $brief = ContentBrief::factory()->create([
            'space_id' => $this->space->id,
            'requirements' => ['tone' => 'professional', 'length' => 'long'],
        ]);

        $result = $this->service->enrichBrief($brief);

        $this->assertEquals('professional', $result->requirements['tone']);
        $this->assertEquals('long', $result->requirements['length']);
        $this->assertArrayHasKey('performance_recommendations', $result->requirements);
    }

    public function test_enrich_brief_includes_prompt_context(): void
    {
        SpacePerformanceModel::factory()->create([
            'space_id' => $this->space->id,
            'top_performers' => [],
            'topic_scores' => ['seo' => 85.0],
            'attribute_weights' => [],
            'model_confidence' => 0.80,
        ]);

        $brief = ContentBrief::factory()->create([
            'space_id' => $this->space->id,
        ]);

        $result = $this->service->enrichBrief($brief);
        $recs = $result->requirements['performance_recommendations'];

        $this->assertArrayHasKey('prompt_context', $recs);
        $this->assertStringContainsString('Performance Insights', $recs['prompt_context']);
    }

    public function test_enrich_brief_is_persisted(): void
    {
        SpacePerformanceModel::factory()->create([
            'space_id' => $this->space->id,
            'model_confidence' => 0.70,
        ]);

        $brief = ContentBrief::factory()->create([
            'space_id' => $this->space->id,
        ]);

        $this->service->enrichBrief($brief);

        $fresh = ContentBrief::find($brief->id);
        $this->assertArrayHasKey('performance_recommendations', $fresh->requirements);
    }

    /**
     * @return list<string>
     */
    private function seedSpaceData(int $count = 3): array
    {
        $contentIds = [];

        for ($i = 0; $i < $count; $i++) {
            $contentId = strtoupper(Str::ulid());
            $contentIds[] = $contentId;

            ContentAttribute::factory()->create([
                'space_id' => $this->space->id,
                'content_id' => $contentId,
                'word_count' => 800 + ($i * 200),
                'image_count' => $i + 1,
            ]);

            ContentPerformanceSnapshot::factory()->create([
                'space_id' => $this->space->id,
                'content_id' => $contentId,
                'period_type' => 'weekly',
                'composite_score' => 70 + ($i * 10),
            ]);
        }

        return $contentIds;
    }
}
