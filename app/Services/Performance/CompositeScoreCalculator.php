<?php

namespace App\Services\Performance;

class CompositeScoreCalculator
{
    private const WEIGHT_ENGAGEMENT = 0.40;

    private const WEIGHT_REACH = 0.30;

    private const WEIGHT_CONVERSION = 0.30;

    /**
     * Calculate a 0-100 composite performance score from snapshot metrics.
     *
     * @param  array{views: int, unique_visitors: int, avg_time_on_page: float, scroll_depth: float, bounce_rate: float, conversions: int, social_shares: int}  $metrics
     */
    public function calculate(array $metrics): float
    {
        $engagement = $this->calculateEngagement(
            (float) ($metrics['avg_time_on_page'] ?? 0),
            (float) ($metrics['scroll_depth'] ?? 0),
            (float) ($metrics['bounce_rate'] ?? 0),
        );

        $reach = $this->calculateReach(
            (int) ($metrics['views'] ?? 0),
            (int) ($metrics['unique_visitors'] ?? 0),
            (int) ($metrics['social_shares'] ?? 0),
        );

        $conversion = $this->calculateConversion(
            (int) ($metrics['conversions'] ?? 0),
            (int) ($metrics['views'] ?? 0),
        );

        $score = ($engagement * self::WEIGHT_ENGAGEMENT)
               + ($reach * self::WEIGHT_REACH)
               + ($conversion * self::WEIGHT_CONVERSION);

        return round(min(100.0, max(0.0, $score)), 2);
    }

    /**
     * Engagement score (0-100) based on time on page, scroll depth, and bounce rate.
     *
     * - avg_time_on_page: seconds, target ~180s = 100
     * - scroll_depth: 0-1 fraction, 1.0 = 100
     * - bounce_rate: 0-1, lower is better (0 = 100, 1 = 0)
     */
    private function calculateEngagement(float $avgTimeOnPage, float $scrollDepth, float $bounceRate): float
    {
        $timeScore = min(100.0, ($avgTimeOnPage / 180.0) * 100.0);
        $scrollScore = min(100.0, $scrollDepth * 100.0);
        $bounceScore = max(0.0, (1.0 - $bounceRate) * 100.0);

        return ($timeScore * 0.40) + ($scrollScore * 0.30) + ($bounceScore * 0.30);
    }

    /**
     * Reach score (0-100) based on views, unique visitors, and social shares.
     *
     * Uses logarithmic scaling: 10k views = ~100.
     */
    private function calculateReach(int $views, int $uniqueVisitors, int $socialShares): float
    {
        $viewScore = $views > 0
            ? min(100.0, (log10($views) / log10(10000)) * 100.0)
            : 0.0;

        $visitorScore = $uniqueVisitors > 0
            ? min(100.0, (log10($uniqueVisitors) / log10(5000)) * 100.0)
            : 0.0;

        $shareScore = $socialShares > 0
            ? min(100.0, (log10($socialShares + 1) / log10(1000)) * 100.0)
            : 0.0;

        return ($viewScore * 0.50) + ($visitorScore * 0.30) + ($shareScore * 0.20);
    }

    /**
     * Conversion score (0-100) based on conversion rate.
     *
     * 5% conversion rate = 100 score.
     */
    private function calculateConversion(int $conversions, int $views): float
    {
        if ($views === 0) {
            return 0.0;
        }

        $rate = $conversions / $views;

        return min(100.0, ($rate / 0.05) * 100.0);
    }
}
