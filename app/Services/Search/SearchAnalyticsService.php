<?php

namespace App\Services\Search;

use Illuminate\Support\Facades\DB;

/**
 * Queries search analytics data for the admin dashboard.
 */
class SearchAnalyticsService
{
    /**
     * @return array<string, mixed>
     */
    public function getDashboard(string $spaceId, string $period = '7d'): array
    {
        $days = match ($period) {
            '30d' => 30,
            '90d' => 90,
            default => 7,
        };

        $since = now()->subDays($days);

        $table = 'search_analytics';

        $topQueries = DB::table($table)
            ->where('space_id', $spaceId)
            ->where('created_at', '>=', $since)
            ->where('tier', '!=', 'click')
            ->select('query_normalized', DB::raw('COUNT(*) as count'), DB::raw('AVG(results_count) as avg_results'))
            ->groupBy('query_normalized')
            ->orderByDesc('count')
            ->limit(20)
            ->get();

        $zeroResultQueries = DB::table($table)
            ->where('space_id', $spaceId)
            ->where('created_at', '>=', $since)
            ->where('results_count', 0)
            ->where('tier', '!=', 'click')
            ->select('query_normalized', DB::raw('COUNT(*) as count'))
            ->groupBy('query_normalized')
            ->orderByDesc('count')
            ->limit(20)
            ->get();

        $avgResponseTime = DB::table($table)
            ->where('space_id', $spaceId)
            ->where('created_at', '>=', $since)
            ->where('tier', '!=', 'click')
            ->avg('response_time_ms');

        $tierUsageRows = DB::table($table)
            ->where('space_id', $spaceId)
            ->where('created_at', '>=', $since)
            ->where('tier', '!=', 'click')
            ->select('tier', DB::raw('COUNT(*) as tier_count'))
            ->groupBy('tier')
            ->get();

        $tierUsage = [];
        foreach ($tierUsageRows as $row) {
            $tierUsage[(string) $row->tier] = (int) $row->tier_count;
        }

        $dailyVolume = DB::table($table)
            ->where('space_id', $spaceId)
            ->where('created_at', '>=', $since)
            ->where('tier', '!=', 'click')
            ->select(DB::raw('DATE(created_at) as date'), DB::raw('COUNT(*) as count'))
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('date')
            ->get();

        $totalSearches = DB::table($table)
            ->where('space_id', $spaceId)
            ->where('created_at', '>=', $since)
            ->where('tier', '!=', 'click')
            ->count();

        $totalClicks = DB::table($table)
            ->where('space_id', $spaceId)
            ->where('created_at', '>=', $since)
            ->where('tier', 'click')
            ->count();

        return [
            'period' => $period,
            'top_queries' => $topQueries,
            'zero_result_queries' => $zeroResultQueries,
            'avg_response_time_ms' => round((float) ($avgResponseTime ?? 0), 1),
            'tier_usage' => $tierUsage,
            'daily_volume' => $dailyVolume,
            'total_searches' => $totalSearches,
            'click_through_rate' => $totalSearches > 0
                ? round(($totalClicks / $totalSearches) * 100, 1)
                : 0.0,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getContentGaps(string $spaceId): array
    {
        $gaps = DB::table('search_analytics')
            ->where('space_id', $spaceId)
            ->where('results_count', 0)
            ->where('tier', '!=', 'click')
            ->where('created_at', '>=', now()->subDays(30))
            ->select('query_normalized', DB::raw('COUNT(*) as count'))
            ->groupBy('query_normalized')
            ->orderByDesc('count')
            ->limit(50)
            ->get();

        return $gaps->map(function (\stdClass $gap): array {
            return [
                'query' => (string) $gap->query_normalized,
                'count' => (int) $gap->count,
                'suggested_action' => 'Create content covering this topic',
            ];
        })->toArray();
    }
}
