<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Performance\ContentPerformanceEvent;
use App\Models\Performance\SpacePerformanceModel;
use App\Models\Space;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class PerformanceOverviewController extends Controller
{
    /**
     * GET /api/v1/spaces/{space}/performance/overview
     */
    public function __invoke(Space $space): JsonResponse
    {
        $spaceId = $space->id;

        // Top performers by composite score (last 30 days)
        $topPerformers = DB::table('content_performance_snapshots')
            ->where('space_id', $spaceId)
            ->where('period_type', 'daily')
            ->where('period_start', '>=', Carbon::now()->subDays(30))
            ->select(DB::raw('content_id, AVG(composite_score) as avg_score, SUM(views) as total_views'))
            ->groupBy('content_id')
            ->orderByDesc('avg_score')
            ->limit(10)
            ->get()
            ->map(fn (object $row) => [
                'content_id' => $row->content_id,
                'avg_composite_score' => round((float) $row->avg_score, 2),
                'total_views' => (int) $row->total_views,
            ])
            ->values()
            ->all();

        // Trend: daily aggregate views for last 14 days
        $trends = DB::table('content_performance_snapshots')
            ->where('space_id', $spaceId)
            ->where('period_type', 'daily')
            ->where('period_start', '>=', Carbon::now()->subDays(14))
            ->select(DB::raw('period_start, SUM(views) as total_views, AVG(composite_score) as avg_score'))
            ->groupBy('period_start')
            ->orderBy('period_start')
            ->get()
            ->map(fn (object $row) => [
                'date' => Carbon::parse($row->period_start)->toDateString(),
                'total_views' => (int) $row->total_views,
                'avg_composite_score' => round((float) $row->avg_score, 2),
            ])
            ->values()
            ->all();

        // Recent events count (last 24h)
        $recentEventsCount = ContentPerformanceEvent::where('space_id', $spaceId)
            ->where('occurred_at', '>=', Carbon::now()->subDay())
            ->count();

        // Space model summary
        $model = SpacePerformanceModel::where('space_id', $spaceId)->first();
        $modelSummary = $model ? [
            'model_confidence' => (float) $model->model_confidence,
            'sample_size' => $model->sample_size,
            'model_version' => $model->model_version,
            'computed_at' => $model->computed_at?->toIso8601String(),
        ] : null;

        return response()->json([
            'data' => [
                'space_id' => $spaceId,
                'top_performers' => $topPerformers,
                'trends' => $trends,
                'recent_events_count' => $recentEventsCount,
                'model' => $modelSummary,
            ],
        ]);
    }
}
