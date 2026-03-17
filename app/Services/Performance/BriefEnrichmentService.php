<?php

namespace App\Services\Performance;

use App\Models\ContentBrief;
use App\Models\Performance\SpacePerformanceModel;

class BriefEnrichmentService
{
    public function __construct(
        private readonly PerformanceInsightBuilder $insightBuilder,
    ) {}

    /**
     * Enrich a content brief with performance-based recommendations.
     *
     * Adds a 'performance_recommendations' key to the brief's requirements
     * with optimal content parameters based on historical performance data.
     */
    public function enrichBrief(ContentBrief $brief): ContentBrief
    {
        $model = SpacePerformanceModel::where('space_id', $brief->space_id)->first();

        if (! $model) {
            return $brief;
        }

        $insights = $this->insightBuilder->buildInsights($brief->space_id);
        $recommendations = $this->buildBriefRecommendations($insights, $model);

        $requirements = $brief->requirements ?? [];
        $requirements['performance_recommendations'] = $recommendations;

        $brief->requirements = $requirements;
        $brief->save();

        return $brief;
    }

    /**
     * @param  array<string, mixed>  $insights
     * @return array<string, mixed>
     */
    private function buildBriefRecommendations(array $insights, SpacePerformanceModel $model): array
    {
        $recommendations = [
            'model_confidence' => (float) $model->model_confidence,
            'generated_at' => now()->toIso8601String(),
        ];

        // Optimal word count
        $wordCount = $insights['optimal_word_count'] ?? [];
        if (($wordCount['min'] ?? 0) > 0) {
            $recommendations['optimal_word_count'] = [
                'min' => $wordCount['min'],
                'max' => $wordCount['max'],
                'target' => $wordCount['avg'],
            ];
        }

        // Optimal title length (based on top performers)
        $recommendations['optimal_title_length'] = [
            'min' => 40,
            'max' => 65,
        ];

        // Optimal media count from attribute weights
        $weights = $model->attribute_weights ?? [];
        if (isset($weights['image_count']) && $weights['image_count'] > 0.2) {
            $recommendations['optimal_media_count'] = [
                'min' => 2,
                'max' => 5,
                'note' => 'Images strongly correlate with performance in this space.',
            ];
        }

        // Best publish times
        $publishTimes = $insights['best_publish_times'] ?? [];
        if (! empty($publishTimes)) {
            $best = $publishTimes[0];
            $recommendations['optimal_publish_time'] = [
                'hour_utc' => $best['hour'],
                'label' => $best['label'],
            ];
        }

        // Topics to emphasize
        $topTopics = $insights['top_performing_topics'] ?? [];
        if (! empty($topTopics)) {
            $recommendations['topics_to_emphasize'] = array_keys(array_slice($topTopics, 0, 5, true));
        }

        // Conversion drivers
        $drivers = $insights['conversion_drivers'] ?? [];
        if (! empty($drivers)) {
            $recommendations['conversion_drivers'] = $drivers;
        }

        // AI prompt context for pipeline stages
        $promptContext = $this->insightBuilder->toPromptContext();
        if ($promptContext !== '') {
            $recommendations['prompt_context'] = $promptContext;
        }

        return $recommendations;
    }
}
