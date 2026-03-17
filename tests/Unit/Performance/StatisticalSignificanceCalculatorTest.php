<?php

namespace Tests\Unit\Performance;

use App\Services\Performance\StatisticalSignificanceCalculator;
use PHPUnit\Framework\TestCase;

class StatisticalSignificanceCalculatorTest extends TestCase
{
    private StatisticalSignificanceCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new StatisticalSignificanceCalculator;
    }

    public function test_returns_expected_keys(): void
    {
        $result = $this->calculator->calculateSignificance(
            ['conversions' => 50, 'visitors' => 1000],
            ['conversions' => 70, 'visitors' => 1000],
        );

        $this->assertArrayHasKey('p_value', $result);
        $this->assertArrayHasKey('confidence_level', $result);
        $this->assertArrayHasKey('is_significant', $result);
        $this->assertArrayHasKey('lift_percentage', $result);
        $this->assertArrayHasKey('sample_size_needed', $result);
        $this->assertArrayHasKey('sufficient_sample', $result);
    }

    public function test_detects_significant_difference(): void
    {
        // Large samples, clear difference
        $result = $this->calculator->calculateSignificance(
            ['conversions' => 100, 'visitors' => 2000],
            ['conversions' => 200, 'visitors' => 2000],
        );

        $this->assertTrue($result['is_significant']);
        $this->assertLessThan(0.05, $result['p_value']);
        $this->assertGreaterThan(0.95, $result['confidence_level']);
        $this->assertGreaterThan(0, $result['lift_percentage']);
    }

    public function test_detects_non_significant_with_small_sample(): void
    {
        // Tiny sample — insufficient to declare significance
        $result = $this->calculator->calculateSignificance(
            ['conversions' => 3, 'visitors' => 10],
            ['conversions' => 4, 'visitors' => 10],
        );

        $this->assertFalse($result['is_significant']);
        $this->assertFalse($result['sufficient_sample']);
    }

    public function test_handles_zero_conversions(): void
    {
        $result = $this->calculator->calculateSignificance(
            ['conversions' => 0, 'visitors' => 500],
            ['conversions' => 0, 'visitors' => 500],
        );

        $this->assertFalse($result['is_significant']);
        $this->assertEquals(0.0, $result['lift_percentage']);
    }

    public function test_handles_equal_rates(): void
    {
        $result = $this->calculator->calculateSignificance(
            ['conversions' => 50, 'visitors' => 1000],
            ['conversions' => 50, 'visitors' => 1000],
        );

        $this->assertFalse($result['is_significant']);
        $this->assertGreaterThan(0.05, $result['p_value']);
    }

    public function test_lift_percentage_calculation(): void
    {
        $result = $this->calculator->calculateSignificance(
            ['conversions' => 100, 'visitors' => 1000], // 10%
            ['conversions' => 120, 'visitors' => 1000], // 12%
        );

        // Lift should be ~20%
        $this->assertEqualsWithDelta(20.0, $result['lift_percentage'], 0.1);
    }

    public function test_minimum_sample_size_returned(): void
    {
        $result = $this->calculator->calculateSignificance(
            ['conversions' => 50, 'visitors' => 1000],
            ['conversions' => 60, 'visitors' => 1000],
        );

        $this->assertGreaterThanOrEqual(100, $result['sample_size_needed']);
    }
}
