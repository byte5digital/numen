<?php

namespace App\Services\Performance;

use App\Models\Performance\ContentAttribute;
use App\Models\Performance\ContentPerformanceSnapshot;
use App\Models\Performance\PerformanceCorrelation;
use Illuminate\Support\Collection;

class PerformanceCorrelatorService
{
    /**
     * Numeric content attributes to correlate against performance metrics.
     *
     * @var array<string, string>
     */
    private const ATTRIBUTE_METRIC_PAIRS = [
        'word_count' => 'engagement_events',
        'image_count' => 'avg_scroll_depth',
        'heading_count' => 'views',
        'ai_quality_score' => 'composite_score',
    ];

    /**
     * Analyze correlations between content attributes and performance for a given content item.
     *
     * @return array{correlations: list<array<string, mixed>>, insights: list<string>}
     */
    public function correlate(string $contentId): array
    {
        $attribute = ContentAttribute::where('content_id', $contentId)->first();

        if (! $attribute) {
            return ['correlations' => [], 'insights' => []];
        }

        $snapshots = ContentPerformanceSnapshot::where('content_id', $contentId)
            ->where('period_type', 'daily')
            ->orderBy('period_start')
            ->get();

        if ($snapshots->count() < 2) {
            return ['correlations' => [], 'insights' => []];
        }

        $correlations = [];
        $insights = [];

        // Correlate numeric attributes against metrics using sibling content
        $siblingAttributes = ContentAttribute::where('space_id', $attribute->space_id)->get();
        $siblingContentIds = $siblingAttributes->pluck('content_id')->all();

        $siblingSnapshots = ContentPerformanceSnapshot::whereIn('content_id', $siblingContentIds)
            ->where('period_type', 'weekly')
            ->get()
            ->groupBy('content_id');

        foreach (self::ATTRIBUTE_METRIC_PAIRS as $attrName => $primaryMetric) {
            $pairs = $this->buildAttributeMetricPairs($siblingAttributes, $siblingSnapshots, $attrName, $primaryMetric);

            if (count($pairs) < 3) {
                continue;
            }

            $coefficient = $this->pearsonCorrelation(
                array_column($pairs, 'attribute'),
                array_column($pairs, 'metric'),
            );

            if ($coefficient === null) {
                continue;
            }

            $insight = $this->generateInsight($attrName, $primaryMetric, $coefficient, count($pairs));

            $correlation = PerformanceCorrelation::updateOrCreate(
                [
                    'space_id' => $attribute->space_id,
                    'content_id' => $contentId,
                    'attribute_name' => $attrName,
                    'metric_name' => $primaryMetric,
                ],
                [
                    'correlation_coefficient' => round($coefficient, 4),
                    'sample_size' => count($pairs),
                    'insight' => $insight,
                    'metadata' => ['method' => 'pearson', 'pairs_used' => count($pairs)],
                ],
            );

            $correlations[] = $correlation->toArray();
            if ($insight) {
                $insights[] = $insight;
            }
        }

        // Publish-time correlation (hour of creation vs views)
        $timeCorrelation = $this->correlatePublishTime($attribute, $siblingAttributes, $siblingSnapshots);
        if ($timeCorrelation) {
            $correlations[] = $timeCorrelation->toArray();
            if ($timeCorrelation->insight) {
                $insights[] = $timeCorrelation->insight;
            }
        }

        return ['correlations' => $correlations, 'insights' => $insights];
    }

    /**
     * Build paired arrays of attribute values and metric values from sibling content.
     *
     * @return list<array{attribute: float, metric: float}>
     */
    private function buildAttributeMetricPairs(
        Collection $attributes,
        Collection $snapshotsByContent,
        string $attrName,
        string $metricName,
    ): array {
        $pairs = [];

        foreach ($attributes as $attr) {
            $attrValue = $attr->{$attrName};
            if ($attrValue === null) {
                continue;
            }

            $contentSnapshots = $snapshotsByContent->get($attr->content_id);
            if (! $contentSnapshots || $contentSnapshots->isEmpty()) {
                continue;
            }

            $avgMetric = $contentSnapshots->avg($metricName);
            if ($avgMetric === null) {
                continue;
            }

            $pairs[] = ['attribute' => (float) $attrValue, 'metric' => (float) $avgMetric];
        }

        return $pairs;
    }

    /**
     * Correlate publish time (hour of day from created_at) with views.
     */
    private function correlatePublishTime(
        ContentAttribute $attribute,
        Collection $siblingAttributes,
        Collection $snapshotsByContent,
    ): ?PerformanceCorrelation {
        $pairs = [];

        foreach ($siblingAttributes as $attr) {
            $hour = $attr->created_at?->hour;
            if ($hour === null) {
                continue;
            }

            $contentSnapshots = $snapshotsByContent->get($attr->content_id);
            if (! $contentSnapshots || $contentSnapshots->isEmpty()) {
                continue;
            }

            $avgViews = $contentSnapshots->avg('views');
            if ($avgViews === null) {
                continue;
            }

            $pairs[] = ['attribute' => (float) $hour, 'metric' => (float) $avgViews];
        }

        if (count($pairs) < 3) {
            return null;
        }

        $coefficient = $this->pearsonCorrelation(
            array_column($pairs, 'attribute'),
            array_column($pairs, 'metric'),
        );

        if ($coefficient === null) {
            return null;
        }

        $insight = $this->generateInsight('publish_hour', 'views', $coefficient, count($pairs));

        return PerformanceCorrelation::updateOrCreate(
            [
                'space_id' => $attribute->space_id,
                'content_id' => $attribute->content_id,
                'attribute_name' => 'publish_hour',
                'metric_name' => 'views',
            ],
            [
                'correlation_coefficient' => round($coefficient, 4),
                'sample_size' => count($pairs),
                'insight' => $insight,
                'metadata' => ['method' => 'pearson', 'pairs_used' => count($pairs)],
            ],
        );
    }

    /**
     * Calculate Pearson correlation coefficient between two arrays.
     *
     * @param  list<float>  $x
     * @param  list<float>  $y
     */
    public function pearsonCorrelation(array $x, array $y): ?float
    {
        $n = count($x);
        if ($n !== count($y) || $n < 2) {
            return null;
        }

        $meanX = array_sum($x) / $n;
        $meanY = array_sum($y) / $n;

        $numerator = 0.0;
        $denomX = 0.0;
        $denomY = 0.0;

        for ($i = 0; $i < $n; $i++) {
            $diffX = $x[$i] - $meanX;
            $diffY = $y[$i] - $meanY;
            $numerator += $diffX * $diffY;
            $denomX += $diffX * $diffX;
            $denomY += $diffY * $diffY;
        }

        $denominator = sqrt($denomX * $denomY);

        if ($denominator == 0) {
            return null;
        }

        return $numerator / $denominator;
    }

    /**
     * Generate a human-readable insight from a correlation result.
     */
    public function generateInsight(string $attribute, string $metric, float $coefficient, int $sampleSize): ?string
    {
        $strength = abs($coefficient);

        if ($strength < 0.1) {
            return null;
        }

        $direction = $coefficient > 0 ? 'positive' : 'negative';

        $strengthLabel = match (true) {
            $strength >= 0.7 => 'strong',
            $strength >= 0.4 => 'moderate',
            default => 'weak',
        };

        $attrLabel = str_replace('_', ' ', $attribute);
        $metricLabel = str_replace('_', ' ', $metric);

        $verb = $coefficient > 0 ? 'increases' : 'decreases';

        return sprintf(
            'There is a %s %s correlation between %s and %s — as %s grows, %s %s (r=%.2f, n=%d).',
            $strengthLabel,
            $direction,
            $attrLabel,
            $metricLabel,
            $attrLabel,
            $metricLabel,
            $verb,
            $coefficient,
            $sampleSize,
        );
    }
}
