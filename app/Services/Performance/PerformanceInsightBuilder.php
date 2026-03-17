<?php

namespace App\Services\Performance;

use App\Models\Performance\ContentAttribute;
use App\Models\Performance\ContentPerformanceSnapshot;
use App\Models\Performance\SpacePerformanceModel;

class PerformanceInsightBuilder
{
    /** @var array<string, mixed> */
    private array $insights = [];

    public function __construct(
        private readonly SpacePerformanceModelService $modelService,
    ) {}

    /**
     * Compile performance insights into structured data for AI prompts.
     *
     * @return array<string, mixed>
     */
    public function buildInsights(string $spaceId, ?string $contentId = null): array
    {
        $model = SpacePerformanceModel::where('space_id', $spaceId)->first();

        $this->insights = [
            'space_id' => $spaceId,
            'has_model' => $model !== null,
            'model_confidence' => $model ? (float) $model->model_confidence : 0.0,
            'top_performing_topics' => $this->getTopPerformingTopics($model),
            'optimal_word_count' => $this->getOptimalWordCountRange($spaceId, $model),
            'best_publish_times' => $this->getBestPublishTimes($spaceId),
            'audience_engagement_patterns' => $this->getEngagementPatterns($spaceId),
            'conversion_drivers' => $this->getConversionDrivers($spaceId, $model),
            'recommendations' => $model ? $this->modelService->getRecommendations($spaceId) : [],
        ];

        if ($contentId) {
            $this->insights['content_specific'] = $this->getContentSpecificInsights($contentId);
        }

        return $this->insights;
    }

    /**
     * Format insights as natural language for injection into AI prompts.
     */
    public function toPromptContext(): string
    {
        if (empty($this->insights) || ! $this->insights['has_model']) {
            return '';
        }

        $lines = ['## Performance Insights'];
        $confidence = $this->insights['model_confidence'];
        $lines[] = sprintf('(Based on performance data — confidence: %.0f%%)', $confidence * 100);
        $lines[] = '';

        $topics = $this->insights['top_performing_topics'];
        if (! empty($topics)) {
            $lines[] = '### Top Performing Topics';
            foreach (array_slice($topics, 0, 5) as $topic => $score) {
                $lines[] = sprintf('- **%s** (avg score: %.1f)', $topic, $score);
            }
            $lines[] = '';
        }

        $wordCount = $this->insights['optimal_word_count'];
        if ($wordCount['min'] > 0) {
            $lines[] = sprintf(
                '### Optimal Word Count: %d–%d words',
                $wordCount['min'],
                $wordCount['max'],
            );
            $lines[] = '';
        }

        $publishTimes = $this->insights['best_publish_times'];
        if (! empty($publishTimes)) {
            $lines[] = '### Best Publish Times';
            foreach (array_slice($publishTimes, 0, 3) as $entry) {
                $lines[] = sprintf('- %s (avg score: %.1f)', $entry['label'], $entry['avg_score']);
            }
            $lines[] = '';
        }

        $engagement = $this->insights['audience_engagement_patterns'];
        if (! empty($engagement)) {
            $lines[] = '### Audience Engagement';
            if (isset($engagement['avg_time_on_page'])) {
                $lines[] = sprintf('- Avg time on page: %.0fs', $engagement['avg_time_on_page']);
            }
            if (isset($engagement['avg_scroll_depth'])) {
                $lines[] = sprintf('- Avg scroll depth: %.0f%%', $engagement['avg_scroll_depth'] * 100);
            }
            if (isset($engagement['avg_bounce_rate'])) {
                $lines[] = sprintf('- Avg bounce rate: %.0f%%', $engagement['avg_bounce_rate'] * 100);
            }
            $lines[] = '';
        }

        $recommendations = $this->insights['recommendations'];
        if (! empty($recommendations)) {
            $lines[] = '### Key Recommendations';
            foreach ($recommendations as $rec) {
                $lines[] = sprintf('- %s', $rec['message']);
            }
            $lines[] = '';
        }

        return implode("\n", $lines);
    }

    /**
     * Get the compiled insights array (after calling buildInsights).
     *
     * @return array<string, mixed>
     */
    public function getInsights(): array
    {
        return $this->insights;
    }

    /**
     * @return array<string, float>
     */
    private function getTopPerformingTopics(?SpacePerformanceModel $model): array
    {
        if (! $model) {
            return [];
        }

        $topicScores = $model->topic_scores ?? [];
        arsort($topicScores);

        return array_slice($topicScores, 0, 10, true);
    }

    /**
     * @return array{min: int, max: int, avg: int}
     */
    private function getOptimalWordCountRange(string $spaceId, ?SpacePerformanceModel $model): array
    {
        $default = ['min' => 0, 'max' => 0, 'avg' => 0];

        if (! $model) {
            return $default;
        }

        // Find word counts of top performers
        $topPerformers = $model->top_performers ?? [];
        if (empty($topPerformers)) {
            return $default;
        }

        $wordCounts = ContentAttribute::where('space_id', $spaceId)
            ->whereIn('content_id', $topPerformers)
            ->whereNotNull('word_count')
            ->pluck('word_count')
            ->filter(fn ($v) => $v > 0);

        if ($wordCounts->isEmpty()) {
            return $default;
        }

        $avg = (int) $wordCounts->avg();

        return [
            'min' => max(1, (int) ($avg * 0.8)),
            'max' => (int) ($avg * 1.2),
            'avg' => $avg,
        ];
    }

    /**
     * @return list<array{hour: int, label: string, avg_score: float}>
     */
    private function getBestPublishTimes(string $spaceId): array
    {
        $attributes = ContentAttribute::where('space_id', $spaceId)->get();
        $hourScores = [];

        foreach ($attributes as $attr) {
            $hour = $attr->created_at?->hour;
            if ($hour === null) {
                continue;
            }

            $snapshot = ContentPerformanceSnapshot::where('content_id', $attr->content_id)
                ->where('period_type', 'weekly')
                ->latest('period_start')
                ->first();

            if (! $snapshot) {
                continue;
            }

            $hourScores[$hour][] = (float) $snapshot->composite_score;
        }

        $results = [];
        foreach ($hourScores as $hour => $scores) {
            if (count($scores) < 2) {
                continue;
            }
            $results[] = [
                'hour' => $hour,
                'label' => sprintf('%02d:00', $hour),
                'avg_score' => round(array_sum($scores) / count($scores), 1),
            ];
        }

        usort($results, fn ($a, $b) => $b['avg_score'] <=> $a['avg_score']);

        return $results;
    }

    /**
     * @return array<string, float>
     */
    private function getEngagementPatterns(string $spaceId): array
    {
        $snapshots = ContentPerformanceSnapshot::where('space_id', $spaceId)
            ->where('period_type', 'weekly')
            ->get();

        if ($snapshots->isEmpty()) {
            return [];
        }

        return array_filter([
            'avg_time_on_page' => round((float) $snapshots->avg('avg_time_on_page_s'), 1),
            'avg_scroll_depth' => round((float) $snapshots->avg('avg_scroll_depth'), 4),
            'avg_bounce_rate' => round((float) $snapshots->avg('bounce_rate'), 4),
            'avg_engagement_events' => round((float) $snapshots->avg('engagement_events'), 1),
        ], fn ($v) => $v > 0);
    }

    /**
     * @return array<string, mixed>
     */
    private function getConversionDrivers(string $spaceId, ?SpacePerformanceModel $model): array
    {
        if (! $model) {
            return [];
        }

        $weights = $model->attribute_weights ?? [];
        $drivers = [];

        foreach ($weights as $attr => $weight) {
            if ($weight >= 0.3) {
                $drivers[$attr] = round($weight, 2);
            }
        }

        arsort($drivers);

        return $drivers;
    }

    /**
     * @return array<string, mixed>
     */
    private function getContentSpecificInsights(string $contentId): array
    {
        $snapshot = ContentPerformanceSnapshot::where('content_id', $contentId)
            ->where('period_type', 'weekly')
            ->latest('period_start')
            ->first();

        if (! $snapshot) {
            return [];
        }

        return [
            'latest_score' => (float) $snapshot->composite_score,
            'views' => $snapshot->views,
            'engagement_events' => $snapshot->engagement_events,
            'conversions' => $snapshot->conversions,
            'scroll_depth' => (float) $snapshot->avg_scroll_depth,
        ];
    }
}
