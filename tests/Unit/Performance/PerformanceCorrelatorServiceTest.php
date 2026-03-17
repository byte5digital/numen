<?php

namespace Tests\Unit\Performance;

use App\Models\Performance\ContentAttribute;
use App\Models\Performance\ContentPerformanceSnapshot;
use App\Models\Performance\PerformanceCorrelation;
use App\Services\Performance\PerformanceCorrelatorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PerformanceCorrelatorServiceTest extends TestCase
{
    use RefreshDatabase;

    private PerformanceCorrelatorService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new PerformanceCorrelatorService;
    }

    public function test_pearson_correlation_perfect_positive(): void
    {
        $x = [1.0, 2.0, 3.0, 4.0, 5.0];
        $y = [2.0, 4.0, 6.0, 8.0, 10.0];

        $result = $this->service->pearsonCorrelation($x, $y);

        $this->assertNotNull($result);
        $this->assertEqualsWithDelta(1.0, $result, 0.0001);
    }

    public function test_pearson_correlation_perfect_negative(): void
    {
        $x = [1.0, 2.0, 3.0, 4.0, 5.0];
        $y = [10.0, 8.0, 6.0, 4.0, 2.0];

        $result = $this->service->pearsonCorrelation($x, $y);

        $this->assertNotNull($result);
        $this->assertEqualsWithDelta(-1.0, $result, 0.0001);
    }

    public function test_pearson_correlation_returns_null_for_constant_values(): void
    {
        $x = [5.0, 5.0, 5.0, 5.0];
        $y = [1.0, 2.0, 3.0, 4.0];

        $this->assertNull($this->service->pearsonCorrelation($x, $y));
    }

    public function test_pearson_correlation_returns_null_for_insufficient_data(): void
    {
        $this->assertNull($this->service->pearsonCorrelation([1.0], [2.0]));
        $this->assertNull($this->service->pearsonCorrelation([], []));
    }

    public function test_correlate_returns_empty_when_no_attributes(): void
    {
        $result = $this->service->correlate('NONEXISTENT');

        $this->assertSame([], $result['correlations']);
        $this->assertSame([], $result['insights']);
    }

    public function test_correlate_returns_empty_with_insufficient_snapshots(): void
    {
        $attr = ContentAttribute::factory()->create(['content_id' => 'CONTENT01']);

        ContentPerformanceSnapshot::factory()->create([
            'content_id' => 'CONTENT01',
            'period_type' => 'daily',
        ]);

        $result = $this->service->correlate('CONTENT01');

        $this->assertSame([], $result['correlations']);
    }

    public function test_correlate_stores_correlations_in_database(): void
    {
        $spaceId = 'SPACE01';
        $contentIds = ['C001', 'C002', 'C003', 'C004'];

        foreach ($contentIds as $i => $contentId) {
            ContentAttribute::factory()->create([
                'space_id' => $spaceId,
                'content_id' => $contentId,
                'word_count' => 500 + ($i * 500),
                'image_count' => $i + 1,
            ]);

            // Create daily snapshots for the correlate check
            ContentPerformanceSnapshot::factory()->count(3)->create([
                'space_id' => $spaceId,
                'content_id' => $contentId,
                'period_type' => 'daily',
            ]);

            // Create weekly snapshots for the sibling correlation
            ContentPerformanceSnapshot::factory()->create([
                'space_id' => $spaceId,
                'content_id' => $contentId,
                'period_type' => 'weekly',
                'engagement_events' => 100 + ($i * 100),
                'avg_scroll_depth' => 0.2 + ($i * 0.2),
                'views' => 200 + ($i * 200),
                'composite_score' => 30 + ($i * 20),
            ]);
        }

        $result = $this->service->correlate('C001');

        $this->assertNotEmpty($result['correlations']);
        $this->assertGreaterThan(0, PerformanceCorrelation::where('space_id', $spaceId)->count());
    }

    public function test_generate_insight_returns_null_for_weak_correlation(): void
    {
        $insight = $this->service->generateInsight('word_count', 'views', 0.05, 50);
        $this->assertNull($insight);
    }

    public function test_generate_insight_returns_description_for_strong_correlation(): void
    {
        $insight = $this->service->generateInsight('word_count', 'engagement_events', 0.85, 100);

        $this->assertNotNull($insight);
        $this->assertStringContainsString('strong', $insight);
        $this->assertStringContainsString('positive', $insight);
        $this->assertStringContainsString('word count', $insight);
    }

    public function test_generate_insight_negative_correlation(): void
    {
        $insight = $this->service->generateInsight('image_count', 'bounce_rate', -0.65, 50);

        $this->assertNotNull($insight);
        $this->assertStringContainsString('negative', $insight);
        $this->assertStringContainsString('decreases', $insight);
    }

    public function test_factory_creates_valid_correlation(): void
    {
        $correlation = PerformanceCorrelation::factory()->create();

        $this->assertNotNull($correlation->id);
        $this->assertNotNull($correlation->correlation_coefficient);
        $this->assertIsString($correlation->attribute_name);
        $this->assertIsString($correlation->metric_name);
    }
}
