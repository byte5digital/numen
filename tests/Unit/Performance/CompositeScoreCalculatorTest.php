<?php

namespace Tests\Unit\Performance;

use App\Services\Performance\CompositeScoreCalculator;
use PHPUnit\Framework\TestCase;

class CompositeScoreCalculatorTest extends TestCase
{
    private CompositeScoreCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new CompositeScoreCalculator;
    }

    public function test_returns_zero_for_worst_case_metrics(): void
    {
        // bounce_rate=1.0 means 0 bounce score; everything else 0
        $score = $this->calculator->calculate([
            'views' => 0,
            'unique_visitors' => 0,
            'avg_time_on_page' => 0,
            'scroll_depth' => 0,
            'bounce_rate' => 1.0,
            'conversions' => 0,
            'social_shares' => 0,
        ]);

        $this->assertEquals(0.0, $score);
    }

    public function test_all_zero_with_zero_bounce_gives_engagement_only(): void
    {
        // bounce_rate=0 means perfect bounce score = 100
        // engagement = (0 * 0.4) + (0 * 0.3) + (100 * 0.3) = 30
        // overall = 30 * 0.4 = 12
        $score = $this->calculator->calculate([
            'views' => 0,
            'unique_visitors' => 0,
            'avg_time_on_page' => 0,
            'scroll_depth' => 0,
            'bounce_rate' => 0,
            'conversions' => 0,
            'social_shares' => 0,
        ]);

        $this->assertEquals(12.0, $score);
    }

    public function test_returns_score_between_zero_and_hundred(): void
    {
        $score = $this->calculator->calculate([
            'views' => 500,
            'unique_visitors' => 300,
            'avg_time_on_page' => 120,
            'scroll_depth' => 0.6,
            'bounce_rate' => 0.3,
            'conversions' => 10,
            'social_shares' => 20,
        ]);

        $this->assertGreaterThanOrEqual(0, $score);
        $this->assertLessThanOrEqual(100, $score);
    }

    public function test_perfect_metrics_approach_hundred(): void
    {
        $score = $this->calculator->calculate([
            'views' => 10000,
            'unique_visitors' => 5000,
            'avg_time_on_page' => 180,
            'scroll_depth' => 1.0,
            'bounce_rate' => 0.0,
            'conversions' => 500,
            'social_shares' => 1000,
        ]);

        $this->assertGreaterThan(90, $score);
        $this->assertLessThanOrEqual(100, $score);
    }

    public function test_zero_views_gives_zero_conversion_score(): void
    {
        $score = $this->calculator->calculate([
            'views' => 0,
            'unique_visitors' => 0,
            'avg_time_on_page' => 120,
            'scroll_depth' => 0.5,
            'bounce_rate' => 0.5,
            'conversions' => 0,
            'social_shares' => 0,
        ]);

        // Only engagement contributes (no reach, no conversion)
        $this->assertGreaterThan(0, $score);
        $this->assertLessThan(50, $score);
    }

    public function test_high_bounce_rate_lowers_score(): void
    {
        $base = [
            'views' => 1000,
            'unique_visitors' => 500,
            'avg_time_on_page' => 120,
            'scroll_depth' => 0.5,
            'conversions' => 10,
            'social_shares' => 5,
        ];

        $lowBounce = $this->calculator->calculate(array_merge($base, ['bounce_rate' => 0.1]));
        $highBounce = $this->calculator->calculate(array_merge($base, ['bounce_rate' => 0.9]));

        $this->assertGreaterThan($highBounce, $lowBounce);
    }

    public function test_score_never_exceeds_hundred(): void
    {
        $score = $this->calculator->calculate([
            'views' => 1000000,
            'unique_visitors' => 500000,
            'avg_time_on_page' => 999,
            'scroll_depth' => 2.0,
            'bounce_rate' => 0.0,
            'conversions' => 100000,
            'social_shares' => 100000,
        ]);

        $this->assertLessThanOrEqual(100, $score);
    }
}
