<?php

namespace App\Services\Competitor;

use App\Models\CompetitorAlertEvent;
use App\Models\CompetitorContentItem;
use App\Models\DifferentiationAnalysis;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Enforces data retention policies for competitor intelligence data.
 */
class RetentionPolicyService
{
    /** Keep competitor content items for 90 days by default */
    private const DEFAULT_CONTENT_RETENTION_DAYS = 90;

    /** Keep differentiation analyses for 180 days by default */
    private const DEFAULT_ANALYSIS_RETENTION_DAYS = 180;

    /** Keep alert events for 30 days by default */
    private const DEFAULT_ALERT_EVENT_RETENTION_DAYS = 30;

    /**
     * Run all retention policies and return a summary of what was pruned.
     *
     * @return array{content_pruned: int, analyses_pruned: int, alert_events_pruned: int}
     */
    public function run(): array
    {
        $contentPruned = $this->pruneCompetitorContent();
        $analysesPruned = $this->pruneDifferentiationAnalyses();
        $alertEventsPruned = $this->pruneAlertEvents();

        Log::info('RetentionPolicyService: pruning complete', [
            'content_pruned' => $contentPruned,
            'analyses_pruned' => $analysesPruned,
            'alert_events_pruned' => $alertEventsPruned,
        ]);

        return [
            'content_pruned' => $contentPruned,
            'analyses_pruned' => $analysesPruned,
            'alert_events_pruned' => $alertEventsPruned,
        ];
    }

    private function pruneCompetitorContent(): int
    {
        $days = (int) config('numen.competitor_analysis.content_retention_days', self::DEFAULT_CONTENT_RETENTION_DAYS);
        $cutoff = Carbon::now()->subDays($days);

        return CompetitorContentItem::where('crawled_at', '<', $cutoff)->delete();
    }

    private function pruneDifferentiationAnalyses(): int
    {
        $days = (int) config('numen.competitor_analysis.analysis_retention_days', self::DEFAULT_ANALYSIS_RETENTION_DAYS);
        $cutoff = Carbon::now()->subDays($days);

        return DifferentiationAnalysis::where('analyzed_at', '<', $cutoff)->delete();
    }

    private function pruneAlertEvents(): int
    {
        $days = (int) config('numen.competitor_analysis.alert_event_retention_days', self::DEFAULT_ALERT_EVENT_RETENTION_DAYS);
        $cutoff = Carbon::now()->subDays($days);

        return CompetitorAlertEvent::where('notified_at', '<', $cutoff)->delete();
    }
}
