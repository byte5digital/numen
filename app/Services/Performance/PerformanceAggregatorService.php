<?php

namespace App\Services\Performance;

use App\Models\Performance\ContentPerformanceEvent;
use App\Models\Performance\ContentPerformanceSnapshot;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class PerformanceAggregatorService
{
    public function __construct(
        private readonly CompositeScoreCalculator $scoreCalculator,
    ) {}

    public function aggregateDaily(string $contentId, Carbon $date): ContentPerformanceSnapshot
    {
        return $this->aggregate($contentId, 'daily', $date->copy()->startOfDay(), $date->copy()->endOfDay());
    }

    public function aggregateWeekly(string $contentId, Carbon $weekStart): ContentPerformanceSnapshot
    {
        $start = $weekStart->copy()->startOfWeek();

        return $this->aggregate($contentId, 'weekly', $start, $start->copy()->endOfWeek());
    }

    public function aggregateMonthly(string $contentId, Carbon $monthStart): ContentPerformanceSnapshot
    {
        $start = $monthStart->copy()->startOfMonth();

        return $this->aggregate($contentId, 'monthly', $start, $start->copy()->endOfMonth());
    }

    private function aggregate(string $contentId, string $periodType, Carbon $periodStart, Carbon $periodEnd): ContentPerformanceSnapshot
    {
        $events = ContentPerformanceEvent::where('content_id', $contentId)
            ->whereBetween('occurred_at', [$periodStart, $periodEnd])
            ->get();

        $spaceId = $events->first()->space_id ?? '';

        $totalViews = $events->whereIn('event_type', ['view', 'page_view'])->count();
        $uniqueVisitors = $events->whereIn('event_type', ['view', 'page_view'])->pluck('visitor_id')->filter()->unique()->count();
        $conversions = $events->where('event_type', 'conversion')->count();
        $socialShares = $events->where('event_type', 'social_share')->count();

        $timeOnPageEvents = $events->where('event_type', 'time_on_page');
        $avgTimeOnPage = $timeOnPageEvents->count() > 0
            ? (float) $timeOnPageEvents->avg('value')
            : null;

        $scrollEvents = $events->where('event_type', 'scroll_depth');
        $avgScrollDepth = $scrollEvents->count() > 0
            ? (float) $scrollEvents->avg('value')
            : null;

        $bounceCount = $events->where('event_type', 'bounce')->count();
        $bounceRate = $totalViews > 0 ? round($bounceCount / $totalViews, 4) : null;

        $engagementEvents = $events->whereIn('event_type', ['click', 'scroll_depth', 'engagement'])->count();

        $conversionRate = $totalViews > 0 ? round($conversions / $totalViews, 4) : null;

        $compositeScore = $this->scoreCalculator->calculate([
            'views' => $totalViews,
            'unique_visitors' => $uniqueVisitors,
            'avg_time_on_page' => $avgTimeOnPage ?? 0,
            'scroll_depth' => $avgScrollDepth ?? 0,
            'bounce_rate' => $bounceRate ?? 0,
            'conversions' => $conversions,
            'social_shares' => $socialShares,
        ]);

        return DB::transaction(function () use (
            $contentId, $spaceId, $periodType, $periodStart,
            $totalViews, $uniqueVisitors, $avgTimeOnPage, $bounceRate,
            $avgScrollDepth, $engagementEvents, $conversions, $conversionRate, $compositeScore,
        ) {
            return ContentPerformanceSnapshot::updateOrCreate(
                [
                    'content_id' => $contentId,
                    'period_type' => $periodType,
                    'period_start' => $periodStart,
                ],
                [
                    'space_id' => $spaceId,
                    'views' => $totalViews,
                    'unique_visitors' => $uniqueVisitors,
                    'avg_time_on_page_s' => $avgTimeOnPage,
                    'bounce_rate' => $bounceRate,
                    'avg_scroll_depth' => $avgScrollDepth,
                    'engagement_events' => $engagementEvents,
                    'conversions' => $conversions,
                    'conversion_rate' => $conversionRate,
                    'composite_score' => $compositeScore,
                ],
            );
        });
    }
}
