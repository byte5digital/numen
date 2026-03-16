<?php

namespace App\Services\Quality;

use App\Events\Quality\ContentQualityScored;
use App\Models\Content;
use App\Models\ContentQualityConfig;
use App\Models\ContentQualityScore;
use App\Models\ContentQualityScoreItem;
use App\Services\Quality\Analyzers\BrandConsistencyAnalyzer;
use App\Services\Quality\Analyzers\EngagementPredictionAnalyzer;
use App\Services\Quality\Analyzers\FactualAccuracyAnalyzer;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;

class ContentQualityService
{
    /** @var array<string, QualityAnalyzerContract> */
    private array $analyzers;

    public function __construct(
        ReadabilityAnalyzer $readabilityAnalyzer,
        SeoAnalyzer $seoAnalyzer,
        BrandConsistencyAnalyzer $brandConsistencyAnalyzer,
        FactualAccuracyAnalyzer $factualAccuracyAnalyzer,
        EngagementPredictionAnalyzer $engagementPredictionAnalyzer,
    ) {
        $this->analyzers = [
            'readability' => $readabilityAnalyzer,
            'seo' => $seoAnalyzer,
            'brand_consistency' => $brandConsistencyAnalyzer,
            'factual_accuracy' => $factualAccuracyAnalyzer,
            'engagement_prediction' => $engagementPredictionAnalyzer,
        ];
    }

    /** Score a piece of content and persist the result. */
    public function score(Content $content, ?ContentQualityConfig $config = null): ContentQualityScore
    {
        $cacheKey = "quality:content:{$content->id}";

        // Return cached result if available
        $cached = Cache::get($cacheKey);
        if ($cached instanceof ContentQualityScore) {
            return $cached;
        }

        $startMs = (int) round(microtime(true) * 1000);

        // Determine enabled dimensions and weights
        $enabledDimensions = $config !== null ? $config->enabled_dimensions : array_keys($this->analyzers);
        $dimensionWeights = $config !== null ? $config->dimension_weights : $this->defaultWeights();

        // Run enabled analyzers
        /** @var array<string, QualityDimensionResult> $results */
        $results = [];
        foreach ($this->analyzers as $dimension => $analyzer) {
            if (! in_array($dimension, $enabledDimensions, true)) {
                continue;
            }
            $results[$dimension] = $analyzer->analyze($content);
        }

        // Calculate weighted overall score
        $overallScore = $this->calculateWeightedScore($results, $dimensionWeights);

        $durationMs = (int) round(microtime(true) * 1000) - $startMs;

        // Persist score record
        $qualityScore = ContentQualityScore::create([
            'space_id' => $content->space_id,
            'content_id' => $content->id,
            'content_version_id' => $content->current_version_id,
            'overall_score' => $overallScore,
            'readability_score' => isset($results['readability']) ? $results['readability']->getScore() : null,
            'seo_score' => isset($results['seo']) ? $results['seo']->getScore() : null,
            'brand_score' => isset($results['brand_consistency']) ? $results['brand_consistency']->getScore() : null,
            'factual_score' => isset($results['factual_accuracy']) ? $results['factual_accuracy']->getScore() : null,
            'engagement_score' => isset($results['engagement_prediction']) ? $results['engagement_prediction']->getScore() : null,
            'scoring_model' => 'content-quality-v1',
            'scoring_duration_ms' => $durationMs,
            'scored_at' => now(),
        ]);

        // Persist individual score items (findings)
        foreach ($results as $dimension => $result) {
            foreach ($result->getItems() as $item) {
                ContentQualityScoreItem::create([
                    'score_id' => $qualityScore->id,
                    'dimension' => $dimension,
                    'category' => $item['type'] ?? 'general',
                    'rule_key' => $item['rule_key'] ?? $item['type'] ?? 'general',
                    'label' => $item['label'] ?? $item['message'],
                    'severity' => $item['severity'] ?? 'info',
                    'score_impact' => $item['score_impact'] ?? 0.0,
                    'message' => $item['message'],
                    'suggestion' => $item['suggestion'] ?? null,
                    'metadata' => $item['meta'] ?? null,
                ]);
            }
        }

        // Fire event
        Event::dispatch(new ContentQualityScored($qualityScore));

        // Cache for 1 hour
        Cache::put($cacheKey, $qualityScore, 3600);

        return $qualityScore;
    }

    /** Invalidate the cache for a specific content item. */
    public function invalidate(Content $content): void
    {
        Cache::forget("quality:content:{$content->id}");
    }

    /**
     * Calculate weighted average score across dimensions.
     *
     * @param  array<string, QualityDimensionResult>  $results
     * @param  array<string, float>  $weights
     */
    private function calculateWeightedScore(array $results, array $weights): float
    {
        if (empty($results)) {
            return 0.0;
        }

        $totalWeight = 0.0;
        $weightedSum = 0.0;

        foreach ($results as $dimension => $result) {
            $weight = (float) ($weights[$dimension] ?? 0.0);
            if ($weight <= 0.0) {
                continue;
            }
            $weightedSum += $result->getScore() * $weight;
            $totalWeight += $weight;
        }

        if ($totalWeight <= 0.0) {
            // Equal-weight fallback
            $scores = array_map(fn (QualityDimensionResult $r) => $r->getScore(), $results);

            return array_sum($scores) / count($scores);
        }

        return round($weightedSum / $totalWeight, 2);
    }

    /**
     * Default dimension weights (equal across all five dimensions).
     *
     * @return array<string, float>
     */
    private function defaultWeights(): array
    {
        return [
            'readability' => 0.20,
            'seo' => 0.20,
            'brand_consistency' => 0.20,
            'factual_accuracy' => 0.20,
            'engagement_prediction' => 0.20,
        ];
    }
}
