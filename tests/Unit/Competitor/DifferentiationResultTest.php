<?php

namespace Tests\Unit\Competitor;

use App\Services\Competitor\DifferentiationResult;
use Tests\TestCase;

class DifferentiationResultTest extends TestCase
{
    public function test_stores_all_fields(): void
    {
        $result = new DifferentiationResult(
            similarityScore: 0.45,
            differentiationScore: 0.55,
            angles: ['angle 1', 'angle 2'],
            gaps: ['gap 1'],
            recommendations: ['rec 1', 'rec 2', 'rec 3'],
        );

        $this->assertEqualsWithDelta(0.45, $result->similarityScore, 0.0001);
        $this->assertEqualsWithDelta(0.55, $result->differentiationScore, 0.0001);
        $this->assertSame(['angle 1', 'angle 2'], $result->angles);
        $this->assertSame(['gap 1'], $result->gaps);
        $this->assertSame(['rec 1', 'rec 2', 'rec 3'], $result->recommendations);
    }

    public function test_to_array_returns_expected_keys(): void
    {
        $result = new DifferentiationResult(
            similarityScore: 0.3,
            differentiationScore: 0.7,
            angles: ['fresh perspective'],
            gaps: ['missing coverage'],
            recommendations: ['add section X'],
        );

        $array = $result->toArray();

        $this->assertArrayHasKey('similarity_score', $array);
        $this->assertArrayHasKey('differentiation_score', $array);
        $this->assertArrayHasKey('angles', $array);
        $this->assertArrayHasKey('gaps', $array);
        $this->assertArrayHasKey('recommendations', $array);
        $this->assertEqualsWithDelta(0.3, $array['similarity_score'], 0.0001);
        $this->assertEqualsWithDelta(0.7, $array['differentiation_score'], 0.0001);
    }

    public function test_differentiation_score_complements_similarity(): void
    {
        $similarity = 0.35;
        $differentiation = round(1.0 - $similarity, 6);

        $result = new DifferentiationResult(
            similarityScore: $similarity,
            differentiationScore: $differentiation,
            angles: [],
            gaps: [],
            recommendations: [],
        );

        $this->assertEqualsWithDelta(1.0, $result->similarityScore + $result->differentiationScore, 0.0001);
    }
}
