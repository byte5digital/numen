<?php

namespace App\Services\Performance;

use App\Models\Performance\ContentAbTest;
use App\Models\Performance\ContentAbVariant;

class ABTestService
{
    public function __construct(
        private readonly TrafficSplitter $trafficSplitter,
        private readonly StatisticalSignificanceCalculator $calculator,
    ) {}

    /**
     * Create a new A/B test with variants.
     *
     * @param  array{name: string, hypothesis?: string, metric?: string, variants: array<array{content_id: string, label: string, is_control?: bool, generation_params?: array}>}  $data
     */
    public function createTest(string $spaceId, array $data): ContentAbTest
    {
        $test = ContentAbTest::create([
            'space_id' => $spaceId,
            'name' => $data['name'],
            'hypothesis' => $data['hypothesis'] ?? null,
            'status' => 'draft',
            'metric' => $data['metric'] ?? 'conversion_rate',
            'traffic_split' => $data['traffic_split'] ?? 0.5,
            'min_sample_size' => $data['min_sample_size'] ?? 100,
            'significance_threshold' => $data['significance_threshold'] ?? 0.95,
        ]);

        $hasControl = false;
        foreach ($data['variants'] as $variantData) {
            $isControl = $variantData['is_control'] ?? (! $hasControl);
            if ($isControl) {
                $hasControl = true;
            }

            ContentAbVariant::create([
                'test_id' => $test->id,
                'content_id' => $variantData['content_id'],
                'label' => $variantData['label'],
                'is_control' => $isControl,
                'generation_params' => $variantData['generation_params'] ?? null,
                'view_count' => 0,
                'conversion_rate' => 0,
            ]);
        }

        $test->load('variants');

        return $test;
    }

    /**
     * Get the active (running) test for a given space.
     */
    public function getActiveTest(string $spaceId): ?ContentAbTest
    {
        return ContentAbTest::where('space_id', $spaceId)
            ->where('status', 'running')
            ->latest('started_at')
            ->first();
    }

    /**
     * Deterministically assign a visitor to a variant.
     */
    public function assignVariant(ContentAbTest $test, string $visitorId): ContentAbVariant
    {
        if ($test->status === 'draft') {
            $test->update([
                'status' => 'running',
                'started_at' => now(),
            ]);
        }

        $variant = $this->trafficSplitter->split($test, $visitorId);

        $variant->increment('view_count');

        return $variant;
    }

    /**
     * Record a conversion for a specific variant and visitor.
     */
    public function recordConversion(ContentAbTest $test, string $variantId, string $visitorId): void
    {
        /** @var ContentAbVariant $variant */
        $variant = $test->variants()->where('id', $variantId)->firstOrFail();

        // Update conversion rate
        $viewCount = max($variant->view_count, 1);
        $currentConversions = (int) round($variant->conversion_rate * $viewCount);
        $newConversions = $currentConversions + 1;
        $newRate = $newConversions / $viewCount;

        $variant->update([
            'conversion_rate' => round($newRate, 4),
        ]);
    }

    /**
     * End a test and declare a winner.
     */
    public function endTest(ContentAbTest $test): array
    {
        $results = $this->getResults($test);

        $winnerId = null;
        $conclusion = ['summary' => 'Test ended without a clear winner.'];

        if ($results['is_significant'] ?? false) {
            $bestVariant = collect($results['variants'])
                ->sortByDesc('conversion_rate')
                ->first();

            if ($bestVariant) {
                $winnerId = $bestVariant['id'];
                $conclusion = [
                    'summary' => "Variant '{$bestVariant['label']}' won with {$bestVariant['conversion_rate']} conversion rate.",
                    'lift' => $results['significance']['lift_percentage'] ?? 0,
                    'confidence' => $results['significance']['confidence_level'] ?? 0,
                ];
            }
        }

        $test->update([
            'status' => 'completed',
            'ended_at' => now(),
            'winner_variant_id' => $winnerId,
            'conclusion' => $conclusion,
        ]);

        return array_merge($results, [
            'winner_variant_id' => $winnerId,
            'conclusion' => $conclusion,
        ]);
    }

    /**
     * Get current results with statistical analysis.
     */
    public function getResults(ContentAbTest $test): array
    {
        $variants = $test->variants()->orderBy('id')->get();

        /** @var ContentAbVariant|null $control */
        $control = $variants->firstWhere('is_control', true);
        $challengers = $variants->where('is_control', false);

        $variantResults = $variants->map(fn (ContentAbVariant $v) => [
            'id' => $v->id,
            'label' => $v->label,
            'content_id' => $v->content_id,
            'is_control' => $v->is_control,
            'view_count' => $v->view_count,
            'conversion_rate' => (float) $v->conversion_rate,
            'conversions' => (int) round((float) $v->conversion_rate * max($v->view_count, 1)),
        ])->values()->all();

        $significance = null;
        $isSignificant = false;

        if ($control && $challengers->isNotEmpty()) {
            /** @var ContentAbVariant $challenger */
            $challenger = $challengers->sortByDesc('conversion_rate')->first();

            $controlConversions = (int) round((float) $control->conversion_rate * max($control->view_count, 1));
            $challengerConversions = (int) round((float) $challenger->conversion_rate * max($challenger->view_count, 1));

            $significance = $this->calculator->calculateSignificance(
                ['conversions' => $controlConversions, 'visitors' => $control->view_count],
                ['conversions' => $challengerConversions, 'visitors' => $challenger->view_count],
            );

            $isSignificant = $significance['is_significant'];
        }

        return [
            'test_id' => $test->id,
            'status' => $test->status,
            'variants' => $variantResults,
            'significance' => $significance,
            'is_significant' => $isSignificant,
            'total_visitors' => $variants->sum('view_count'),
        ];
    }
}
