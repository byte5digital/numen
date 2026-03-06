<?php

namespace Tests\Unit;

use App\Services\Anthropic\CostTracker;
use Tests\TestCase;

class CostTrackerTest extends TestCase
{
    private CostTracker $tracker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tracker = new CostTracker;
    }

    public function test_opus_cost_calculation(): void
    {
        // Opus: $15/M input, $75/M output
        $cost = $this->tracker->calculateCost('claude-opus-4-20250514', 1000, 500);
        // (1000 * 15 + 500 * 75) / 1_000_000 = (15000 + 37500) / 1_000_000 = 0.0525
        $this->assertEqualsWithDelta(0.0525, $cost, 0.0001);
    }

    public function test_sonnet_cost_calculation(): void
    {
        // Sonnet: $3/M input, $15/M output
        $cost = $this->tracker->calculateCost('claude-sonnet-4-20250514', 1000, 500);
        // (1000 * 3 + 500 * 15) / 1_000_000 = (3000 + 7500) / 1_000_000 = 0.0105
        $this->assertEqualsWithDelta(0.0105, $cost, 0.0001);
    }

    public function test_haiku_cost_calculation(): void
    {
        // Haiku: $0.80/M input, $4/M output
        $cost = $this->tracker->calculateCost('claude-haiku-3-5-20241022', 1000, 500);
        // (1000 * 0.80 + 500 * 4) / 1_000_000 = (800 + 2000) / 1_000_000 = 0.0028
        $this->assertEqualsWithDelta(0.0028, $cost, 0.0001);
    }

    public function test_cache_tokens_are_cheaper(): void
    {
        $costWithoutCache = $this->tracker->calculateCost('claude-sonnet-4-20250514', 10000, 500);
        $costWithCache = $this->tracker->calculateCost('claude-sonnet-4-20250514', 2000, 500, 8000);

        // Cache tokens cost 10x less, so cost with cache should be lower
        $this->assertLessThan($costWithoutCache, $costWithCache);
    }

    public function test_typical_article_generation_cost(): void
    {
        // Typical article: ~2k input tokens, ~3k output tokens with Sonnet
        $cost = $this->tracker->calculateCost('claude-sonnet-4-20250514', 2000, 3000);
        // Should be well under $1
        $this->assertLessThan(1.0, $cost);
        // Should be roughly $0.051
        $this->assertEqualsWithDelta(0.051, $cost, 0.01);
    }

    public function test_hundred_articles_cost_estimate(): void
    {
        // 100 articles through full pipeline:
        // Generation (Sonnet): 2k in, 3k out × 100
        $genCost = $this->tracker->calculateCost('claude-sonnet-4-20250514', 200000, 300000);
        // SEO (Haiku): 4k in, 2k out × 100
        $seoCost = $this->tracker->calculateCost('claude-haiku-3-5-20241022', 400000, 200000);
        // Review (Opus): 5k in, 1k out × 100
        $reviewCost = $this->tracker->calculateCost('claude-opus-4-20250514', 500000, 100000);

        $totalCost = $genCost + $seoCost + $reviewCost;

        // bytyBot estimated ~$25/month for 100 articles — let's verify it's in range
        $this->assertGreaterThan(5, $totalCost);
        $this->assertLessThan(50, $totalCost);
    }
}
