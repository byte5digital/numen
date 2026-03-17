<?php

namespace App\Services\Performance;

class StatisticalSignificanceCalculator
{
    /**
     * Calculate statistical significance between control and variant data.
     *
     * @param  array{conversions: int, visitors: int}  $controlData
     * @param  array{conversions: int, visitors: int}  $variantData
     * @return array{p_value: float, confidence_level: float, is_significant: bool, lift_percentage: float, sample_size_needed: int, sufficient_sample: bool}
     */
    public function calculateSignificance(array $controlData, array $variantData): array
    {
        $controlVisitors = max($controlData['visitors'], 1);
        $variantVisitors = max($variantData['visitors'], 1);
        $controlConversions = $controlData['conversions'];
        $variantConversions = $variantData['conversions'];

        $controlRate = $controlConversions / $controlVisitors;
        $variantRate = $variantConversions / $variantVisitors;

        $liftPercentage = $controlRate > 0
            ? (($variantRate - $controlRate) / $controlRate) * 100
            : ($variantRate > 0 ? 100.0 : 0.0);

        $sampleSizeNeeded = $this->calculateMinSampleSize($controlRate);
        $sufficientSample = $controlVisitors >= $sampleSizeNeeded && $variantVisitors >= $sampleSizeNeeded;

        // Z-test for proportions
        $pValue = $this->zTestForProportions(
            $controlConversions,
            $controlVisitors,
            $variantConversions,
            $variantVisitors,
        );

        $confidenceLevel = 1.0 - $pValue;

        return [
            'p_value' => round($pValue, 6),
            'confidence_level' => round($confidenceLevel, 6),
            'is_significant' => $sufficientSample && $pValue < 0.05,
            'lift_percentage' => round($liftPercentage, 4),
            'sample_size_needed' => $sampleSizeNeeded,
            'sufficient_sample' => $sufficientSample,
        ];
    }

    /**
     * Two-proportion z-test returning a p-value.
     */
    private function zTestForProportions(
        int $conversionsA,
        int $visitorsA,
        int $conversionsB,
        int $visitorsB,
    ): float {
        $totalVisitors = $visitorsA + $visitorsB;
        if ($totalVisitors === 0) {
            return 1.0;
        }

        $pooledRate = ($conversionsA + $conversionsB) / $totalVisitors;

        if ($pooledRate <= 0.0 || $pooledRate >= 1.0) {
            return 1.0;
        }

        $standardError = sqrt(
            $pooledRate * (1 - $pooledRate) * (1 / $visitorsA + 1 / $visitorsB)
        );

        if ($standardError <= 0.0) {
            return 1.0;
        }

        $rateA = $conversionsA / $visitorsA;
        $rateB = $conversionsB / $visitorsB;
        $zScore = abs($rateB - $rateA) / $standardError;

        // Two-tailed p-value from z-score using complementary error function
        return 2.0 * $this->normalCdf(-$zScore);
    }

    /**
     * Standard normal CDF approximation (Abramowitz & Stegun).
     */
    private function normalCdf(float $x): float
    {
        // Use the complementary error function
        return 0.5 * (1.0 + $this->erf($x / sqrt(2.0)));
    }

    /**
     * Error function approximation (max error ~1.5e-7).
     */
    private function erf(float $x): float
    {
        $sign = $x >= 0 ? 1 : -1;
        $x = abs($x);

        $t = 1.0 / (1.0 + 0.3275911 * $x);
        $y = 1.0 - (
            (((1.061405429 * $t - 1.453152027) * $t + 1.421413741) * $t - 0.284496736) * $t + 0.254829592
        ) * $t * exp(-$x * $x);

        return $sign * $y;
    }

    /**
     * Estimate minimum sample size per variant for 80% power and 5% significance.
     *
     * Uses a simplified formula: n = 16 * p * (1-p) / delta^2
     * where delta is the minimum detectable absolute difference.
     */
    private function calculateMinSampleSize(float $baselineRate, float $absoluteMde = 0.05): int
    {
        if ($baselineRate <= 0.0 || $baselineRate >= 1.0) {
            return 100;
        }

        // Simplified formula for two-proportion z-test (80% power, 95% significance)
        // n per group ≈ 16 * p̄(1 - p̄) / δ²
        $pBar = $baselineRate;
        $numerator = 16.0 * $pBar * (1.0 - $pBar);
        $denominator = $absoluteMde ** 2;

        if ($denominator <= 0) {
            return 100;
        }

        return max(100, (int) ceil($numerator / $denominator));
    }
}
