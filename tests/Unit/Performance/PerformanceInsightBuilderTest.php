<?php

namespace Tests\Unit\Performance;

use App\Models\Performance\ContentAttribute;
use App\Models\Performance\ContentPerformanceSnapshot;
use App\Models\Performance\SpacePerformanceModel;
use App\Services\Performance\PerformanceCorrelatorService;
use App\Services\Performance\PerformanceInsightBuilder;
use App\Services\Performance\SpacePerformanceModelService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PerformanceInsightBuilderTest extends TestCase
{
    use RefreshDatabase;

    private PerformanceInsightBuilder $builder;

    private string $spaceId;

    protected function setUp(): void
    {
        parent::setUp();

        $modelService = new SpacePerformanceModelService(
            new PerformanceCorrelatorService,
        );
        $this->builder = new PerformanceInsightBuilder($modelService);
        $this->spaceId = strtoupper(Str::ulid());
    }

    public function test_build_insights_returns_empty_structure_without_model(): void
    {
        $insights = $this->builder->buildInsights($this->spaceId);

        $this->assertFalse($insights['has_model']);
        $this->assertEquals(0.0, $insights['model_confidence']);
        $this->assertEmpty($insights['top_performing_topics']);
        $this->assertEmpty($insights['recommendations']);
    }

    public function test_build_insights_compiles_data_with_model(): void
    {
        $contentIds = $this->seedSpaceData();

        $model = SpacePerformanceModel::factory()->create([
            'space_id' => $this->spaceId,
            'top_performers' => $contentIds,
            'topic_scores' => ['seo' => 85.0, 'marketing' => 72.0, 'ai' => 90.5],
            'attribute_weights' => ['word_count' => 0.45, 'image_count' => 0.35, 'ai_quality_score' => 0.60],
            'model_confidence' => 0.75,
        ]);

        $insights = $this->builder->buildInsights($this->spaceId);

        $this->assertTrue($insights['has_model']);
        $this->assertEquals(0.75, $insights['model_confidence']);
        $this->assertNotEmpty($insights['top_performing_topics']);
        $this->assertArrayHasKey('ai', $insights['top_performing_topics']);
        $this->assertArrayHasKey('min', $insights['optimal_word_count']);
        $this->assertArrayHasKey('max', $insights['optimal_word_count']);
    }

    public function test_build_insights_includes_content_specific_data(): void
    {
        $contentId = strtoupper(Str::ulid());

        ContentPerformanceSnapshot::factory()->create([
            'space_id' => $this->spaceId,
            'content_id' => $contentId,
            'period_type' => 'weekly',
            'composite_score' => 88.5,
            'views' => 1200,
            'engagement_events' => 45,
        ]);

        SpacePerformanceModel::factory()->create([
            'space_id' => $this->spaceId,
            'top_performers' => [$contentId],
        ]);

        $insights = $this->builder->buildInsights($this->spaceId, $contentId);

        $this->assertArrayHasKey('content_specific', $insights);
        $this->assertEquals(88.5, $insights['content_specific']['latest_score']);
        $this->assertEquals(1200, $insights['content_specific']['views']);
    }

    public function test_to_prompt_context_returns_empty_without_model(): void
    {
        $this->builder->buildInsights($this->spaceId);
        $context = $this->builder->toPromptContext();

        $this->assertSame('', $context);
    }

    public function test_to_prompt_context_returns_formatted_string(): void
    {
        $this->seedSpaceData();

        SpacePerformanceModel::factory()->create([
            'space_id' => $this->spaceId,
            'top_performers' => [],
            'topic_scores' => ['seo' => 85.0, 'content marketing' => 72.0],
            'attribute_weights' => ['word_count' => 0.45],
            'model_confidence' => 0.80,
        ]);

        $this->builder->buildInsights($this->spaceId);
        $context = $this->builder->toPromptContext();

        $this->assertStringContainsString('## Performance Insights', $context);
        $this->assertStringContainsString('80%', $context);
        $this->assertStringContainsString('Top Performing Topics', $context);
        $this->assertStringContainsString('seo', $context);
    }

    public function test_engagement_patterns_are_compiled(): void
    {
        $contentId = strtoupper(Str::ulid());

        ContentPerformanceSnapshot::factory()->create([
            'space_id' => $this->spaceId,
            'content_id' => $contentId,
            'period_type' => 'weekly',
            'avg_time_on_page_s' => 120.5,
            'avg_scroll_depth' => 0.75,
            'bounce_rate' => 0.35,
            'engagement_events' => 25,
        ]);

        SpacePerformanceModel::factory()->create([
            'space_id' => $this->spaceId,
        ]);

        $insights = $this->builder->buildInsights($this->spaceId);

        $this->assertNotEmpty($insights['audience_engagement_patterns']);
        $this->assertArrayHasKey('avg_time_on_page', $insights['audience_engagement_patterns']);
        $this->assertArrayHasKey('avg_scroll_depth', $insights['audience_engagement_patterns']);
    }

    public function test_conversion_drivers_filters_low_weight_attributes(): void
    {
        SpacePerformanceModel::factory()->create([
            'space_id' => $this->spaceId,
            'attribute_weights' => [
                'ai_quality_score' => 0.60,
                'word_count' => 0.45,
                'image_count' => 0.10,  // Below 0.3 threshold
            ],
        ]);

        $insights = $this->builder->buildInsights($this->spaceId);

        $this->assertArrayHasKey('ai_quality_score', $insights['conversion_drivers']);
        $this->assertArrayHasKey('word_count', $insights['conversion_drivers']);
        $this->assertArrayNotHasKey('image_count', $insights['conversion_drivers']);
    }

    public function test_best_publish_times_requires_minimum_samples(): void
    {
        // Only 1 content item per hour — should be filtered out (needs >= 2)
        $contentId = strtoupper(Str::ulid());

        ContentAttribute::factory()->create([
            'space_id' => $this->spaceId,
            'content_id' => $contentId,
            'created_at' => now()->setHour(10),
        ]);

        ContentPerformanceSnapshot::factory()->create([
            'space_id' => $this->spaceId,
            'content_id' => $contentId,
            'period_type' => 'weekly',
        ]);

        SpacePerformanceModel::factory()->create([
            'space_id' => $this->spaceId,
        ]);

        $insights = $this->builder->buildInsights($this->spaceId);

        $this->assertEmpty($insights['best_publish_times']);
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
                'space_id' => $this->spaceId,
                'content_id' => $contentId,
                'word_count' => 800 + ($i * 200),
                'image_count' => $i + 1,
                'topics' => ['seo', 'marketing'],
            ]);

            ContentPerformanceSnapshot::factory()->create([
                'space_id' => $this->spaceId,
                'content_id' => $contentId,
                'period_type' => 'weekly',
                'composite_score' => 70 + ($i * 10),
                'views' => 500 + ($i * 200),
                'avg_time_on_page_s' => 90 + ($i * 30),
                'avg_scroll_depth' => 0.5 + ($i * 0.1),
                'bounce_rate' => 0.4 - ($i * 0.05),
                'engagement_events' => 20 + ($i * 10),
            ]);
        }

        return $contentIds;
    }
}
