<?php

namespace App\Services\Performance;

use App\Models\Performance\ContentAbTest;
use App\Models\Performance\ContentAbVariant;
use Illuminate\Database\Eloquent\Collection;

class TrafficSplitter
{
    /**
     * Deterministically assign a visitor to a variant based on hash.
     */
    public function split(ContentAbTest $test, string $visitorId): ContentAbVariant
    {
        /** @var Collection<int, ContentAbVariant> $variants */
        $variants = $test->variants()->orderBy('id')->get();

        if ($variants->isEmpty()) {
            throw new \RuntimeException('Test has no variants.');
        }

        $hash = crc32($test->id.':'.$visitorId);
        $bucket = ($hash & 0x7FFFFFFF) % 10000; // 0–9999

        $weights = $this->getWeights($variants);
        $cumulative = 0;

        foreach ($variants as $index => $variant) {
            $cumulative += (int) round($weights[$index] * 10000);
            if ($bucket < $cumulative) {
                return $variant;
            }
        }

        /** @var ContentAbVariant */
        return $variants->last();
    }

    /**
     * Update traffic distribution weights for a test's variants.
     *
     * @param  array<string, float>  $weights  variant_id => weight (0.0–1.0)
     */
    public function adjustWeights(ContentAbTest $test, array $weights): void
    {
        $total = array_sum($weights);
        if (abs($total - 1.0) > 0.01) {
            throw new \InvalidArgumentException('Weights must sum to 1.0, got '.$total);
        }

        foreach ($weights as $variantId => $weight) {
            ContentAbVariant::where('id', $variantId)
                ->where('test_id', $test->id)
                ->update(['weight' => round($weight, 4)]);
        }
    }

    /**
     * Get normalised weights for variants.
     *
     * @param  Collection<int, ContentAbVariant>  $variants
     * @return array<int, float>
     */
    private function getWeights(Collection $variants): array
    {
        $hasWeights = $variants->some(fn (ContentAbVariant $v) => $v->weight !== null && (float) $v->weight > 0);

        if ($hasWeights) {
            $total = $variants->sum('weight');
            if ($total > 0) {
                return $variants->map(fn (ContentAbVariant $v) => ((float) ($v->weight ?? 0)) / $total)->values()->all();
            }
        }

        // Equal split
        $count = $variants->count();

        return array_fill(0, $count, 1.0 / $count);
    }
}
