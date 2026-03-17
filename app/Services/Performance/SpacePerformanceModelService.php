<?php

namespace App\Services\Performance;

use App\Models\Performance\ContentAttribute;
use App\Models\Performance\ContentPerformanceSnapshot;
use App\Models\Performance\PerformanceCorrelation;
use App\Models\Performance\SpacePerformanceModel;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class SpacePerformanceModelService
{
    public function __construct(
        private readonly PerformanceCorrelatorService $correlator,
    ) {}

    public function buildModel(string $spaceId): SpacePerformanceModel
    {
        $contentIds = ContentAttribute::where('space_id', $spaceId)
            ->pluck('content_id')
            ->all();

        foreach ($contentIds as $contentId) {
            $this->correlator->correlate($contentId);
        }

        $correlations = PerformanceCorrelation::where('space_id', $spaceId)->get();
        $attributeWeights = $this->computeAttributeWeights($correlations);

        $snapshots = ContentPerformanceSnapshot::where('space_id', $spaceId)
            ->where('period_type', 'weekly')
            ->get()
            ->groupBy('content_id');

        $contentScores = $snapshots->map(fn (Collection $s) => (float) $s->avg('composite_score'))
            ->sortDesc();

        $topPerformers = $contentScores->take(5)->keys()->values()->all();
        $bottomPerformers = $contentScores->reverse()->take(5)->keys()->values()->all();

        $topicScores = $this->computeTopicScores($spaceId);
        $personaScores = $this->computePersonaScores($spaceId);

        $sampleSize = count($contentIds);
        $modelConfidence = $this->calculateConfidence($sampleSize, $correlations->count());

        return SpacePerformanceModel::updateOrCreate(
            ['space_id' => $spaceId],
            [
                'attribute_weights' => $attributeWeights,
                'top_performers' => $topPerformers,
                'bottom_performers' => $bottomPerformers,
                'topic_scores' => $topicScores,
                'persona_scores' => $personaScores,
                'sample_size' => $sampleSize,
                'model_confidence' => round($modelConfidence, 4),
                'model_version' => 'v1',
                'computed_at' => Carbon::now(),
            ],
        );
    }

    /**
     * @return list<array{type: string, message: string, confidence: float, attribute: string}>
     */
    public function getRecommendations(string $spaceId): array
    {
        $model = SpacePerformanceModel::where('space_id', $spaceId)->first();

        if (! $model) {
            return [];
        }

        $recommendations = [];

        $correlations = PerformanceCorrelation::where('space_id', $spaceId)
            ->where('sample_size', '>=', 3)
            ->orderByRaw('ABS(correlation_coefficient) DESC')
            ->get();

        foreach ($correlations->unique('attribute_name')->take(5) as $correlation) {
            $rec = $this->buildRecommendation($correlation, $spaceId);
            if ($rec) {
                $recommendations[] = $rec;
            }
        }

        $topicScores = $model->topic_scores ?? [];
        if (count($topicScores) >= 2) {
            arsort($topicScores);
            $bestTopic = array_key_first($topicScores);
            $bestScore = reset($topicScores);

            $recommendations[] = [
                'type' => 'topic',
                'message' => sprintf(
                    'Content about "%s" performs best with an average score of %.1f.',
                    $bestTopic,
                    $bestScore,
                ),
                'confidence' => (float) $model->model_confidence,
                'attribute' => 'topic',
            ];
        }

        return $recommendations;
    }

    public function refreshModel(string $spaceId): SpacePerformanceModel
    {
        PerformanceCorrelation::where('space_id', $spaceId)->delete();

        return $this->buildModel($spaceId);
    }

    /** @return array<string, float> */
    private function computeAttributeWeights(Collection $correlations): array
    {
        return $correlations
            ->groupBy('attribute_name')
            ->map(fn (Collection $group) => round((float) $group->avg(fn ($c) => abs((float) $c->correlation_coefficient)), 4))
            ->sortDesc()
            ->all();
    }

    /** @return array<string, float> */
    private function computeTopicScores(string $spaceId): array
    {
        $attributes = ContentAttribute::where('space_id', $spaceId)
            ->whereNotNull('topics')
            ->get();

        $topicScores = [];
        $topicCounts = [];

        foreach ($attributes as $attr) {
            $topics = $attr->topics ?? [];
            $snapshot = ContentPerformanceSnapshot::where('content_id', $attr->content_id)
                ->where('period_type', 'weekly')
                ->latest('period_start')
                ->first();

            if (! $snapshot) {
                continue;
            }

            foreach ($topics as $topic) {
                $topicScores[$topic] = ($topicScores[$topic] ?? 0) + (float) $snapshot->composite_score;
                $topicCounts[$topic] = ($topicCounts[$topic] ?? 0) + 1;
            }
        }

        $result = [];
        foreach ($topicScores as $topic => $total) {
            $result[$topic] = round($total / $topicCounts[$topic], 1);
        }

        arsort($result);

        return $result;
    }

    /** @return array<string, float> */
    private function computePersonaScores(string $spaceId): array
    {
        $attributes = ContentAttribute::where('space_id', $spaceId)
            ->whereNotNull('persona_id')
            ->get();

        $personaScores = [];
        $personaCounts = [];

        foreach ($attributes as $attr) {
            $snapshot = ContentPerformanceSnapshot::where('content_id', $attr->content_id)
                ->where('period_type', 'weekly')
                ->latest('period_start')
                ->first();

            if (! $snapshot) {
                continue;
            }

            $key = $attr->persona_id;
            $personaScores[$key] = ($personaScores[$key] ?? 0) + (float) $snapshot->composite_score;
            $personaCounts[$key] = ($personaCounts[$key] ?? 0) + 1;
        }

        $result = [];
        foreach ($personaScores as $persona => $total) {
            $result[$persona] = round($total / $personaCounts[$persona], 1);
        }

        arsort($result);

        return $result;
    }

    /** @return array{type: string, message: string, confidence: float, attribute: string}|null */
    private function buildRecommendation(PerformanceCorrelation $correlation, string $spaceId): ?array
    {
        $coefficient = (float) $correlation->correlation_coefficient;
        $strength = abs($coefficient);

        if ($strength < 0.2) {
            return null;
        }

        $message = $this->recommendationMessage($correlation, $spaceId);

        if (! $message) {
            return null;
        }

        return [
            'type' => 'attribute',
            'message' => $message,
            'confidence' => round($strength, 2),
            'attribute' => $correlation->attribute_name,
        ];
    }

    private function recommendationMessage(PerformanceCorrelation $correlation, string $spaceId): ?string
    {
        $attr = $correlation->attribute_name;
        $metric = str_replace('_', ' ', $correlation->metric_name);
        $coefficient = (float) $correlation->correlation_coefficient;

        if ($attr === 'word_count') {
            $avgWordCount = ContentAttribute::where('space_id', $spaceId)->avg('word_count');
            if ($avgWordCount === null) {
                return null;
            }

            if ($coefficient > 0) {
                $target = (int) ($avgWordCount * 1.2);

                return sprintf(
                    'Longer articles tend to drive more %s. Aim for %d+ words (current avg: %d).',
                    $metric,
                    $target,
                    (int) $avgWordCount,
                );
            }

            $target = (int) ($avgWordCount * 0.8);

            return sprintf(
                'Shorter articles perform better for %s. Consider keeping posts under %d words.',
                $metric,
                $target,
            );
        }

        if ($attr === 'image_count') {
            return $coefficient > 0
                ? sprintf('Adding more images correlates with better %s.', $metric)
                : sprintf('Content with fewer images tends to have better %s.', $metric);
        }

        if ($attr === 'publish_hour') {
            return $correlation->insight;
        }

        if ($attr === 'ai_quality_score') {
            return $coefficient > 0
                ? sprintf('Higher AI quality scores strongly correlate with better %s.', $metric)
                : null;
        }

        return $correlation->insight;
    }

    private function calculateConfidence(int $sampleSize, int $correlationCount): float
    {
        if ($sampleSize < 5) {
            return 0.1;
        }

        $sizeScore = min(1.0, $sampleSize / 100);
        $correlationScore = min(1.0, $correlationCount / 20);

        return ($sizeScore * 0.6) + ($correlationScore * 0.4);
    }
}
