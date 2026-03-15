<?php

namespace App\Services\Quality;

use App\Models\ContentQualityScore;
use App\Models\Space;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class QualityTrendAggregator
{
    /**
     * Aggregate daily average quality scores per dimension for a space over a date range.
     *
     * Returns an array keyed by date (Y-m-d), each containing dimension averages and overall.
     *
     * @return array<string, array<string, float|null>>
     */
    public function getSpaceTrends(Space $space, Carbon $from, Carbon $to): array
    {
        /** @var \Illuminate\Support\Collection<int, object> $rows */
        $rows = DB::table('content_quality_scores')
            ->select([
                DB::raw('DATE(scored_at) as score_date'),
                DB::raw('AVG(overall_score)    as avg_overall'),
                DB::raw('AVG(readability_score) as avg_readability'),
                DB::raw('AVG(seo_score)         as avg_seo'),
                DB::raw('AVG(brand_score)       as avg_brand'),
                DB::raw('AVG(factual_score)     as avg_factual'),
                DB::raw('AVG(engagement_score)  as avg_engagement'),
                DB::raw('COUNT(*)               as total_scored'),
            ])
            ->where('space_id', $space->id)
            ->whereBetween('scored_at', [$from->startOfDay(), $to->endOfDay()])
            ->groupBy(DB::raw('DATE(scored_at)'))
            ->orderBy('score_date')
            ->get();

        $trends = [];
        foreach ($rows as $row) {
            $trends[(string) $row->score_date] = [
                'overall' => $row->avg_overall !== null ? round((float) $row->avg_overall, 2) : null,
                'readability' => $row->avg_readability !== null ? round((float) $row->avg_readability, 2) : null,
                'seo' => $row->avg_seo !== null ? round((float) $row->avg_seo, 2) : null,
                'brand' => $row->avg_brand !== null ? round((float) $row->avg_brand, 2) : null,
                'factual' => $row->avg_factual !== null ? round((float) $row->avg_factual, 2) : null,
                'engagement' => $row->avg_engagement !== null ? round((float) $row->avg_engagement, 2) : null,
                'total' => (int) $row->total_scored,
            ];
        }

        return $trends;
    }

    /**
     * Return top-scoring content in a space, ordered by overall_score descending.
     * Only the most recent score per content item is considered.
     *
     * @return Collection<int, ContentQualityScore>
     */
    public function getSpaceLeaderboard(Space $space, int $limit = 10): Collection
    {
        // Subquery: get the latest score_id per content_id
        $latestIds = DB::table('content_quality_scores as cqs_inner')
            ->select(DB::raw('MAX(id) as latest_id'))
            ->where('space_id', $space->id)
            ->groupBy('content_id');

        return ContentQualityScore::with('content')
            ->whereIn('id', $latestIds->pluck('latest_id'))
            ->orderByDesc('overall_score')
            ->limit($limit)
            ->get();
    }

    /**
     * Return histogram distribution data for each dimension within a space.
     * Buckets: 0-10, 10-20, ..., 90-100.
     *
     * @return array<string, array<string, int>>
     */
    public function getDimensionDistribution(Space $space): array
    {
        $dimensions = [
            'overall' => 'overall_score',
            'readability' => 'readability_score',
            'seo' => 'seo_score',
            'brand' => 'brand_score',
            'factual' => 'factual_score',
            'engagement' => 'engagement_score',
        ];

        $distribution = [];

        foreach ($dimensions as $label => $column) {
            // Initialise all buckets
            $buckets = [];
            for ($i = 0; $i < 10; $i++) {
                $buckets[($i * 10).'-'.(($i + 1) * 10)] = 0;
            }

            $scores = DB::table('content_quality_scores')
                ->select($column)
                ->where('space_id', $space->id)
                ->whereNotNull($column)
                ->pluck($column);

            foreach ($scores as $score) {
                $bucketIndex = min((int) floor((float) $score / 10), 9);
                $key = ($bucketIndex * 10).'-'.(($bucketIndex + 1) * 10);
                $buckets[$key]++;
            }

            $distribution[$label] = $buckets;
        }

        return $distribution;
    }
}
