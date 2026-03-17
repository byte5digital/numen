<?php

namespace App\Services\Performance;

use App\Models\Content;
use App\Models\Performance\ContentPerformanceSnapshot;
use App\Models\Performance\ContentRefreshSuggestion;
use App\Models\Performance\SpacePerformanceModel;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class ContentRefreshAdvisorService
{
    private const DECLINING_WEEKS_THRESHOLD = 3;

    private const HIGH_BOUNCE_RATE = 0.65;

    private const LOW_SCROLL_DEPTH = 0.30;

    private const STALENESS_DAYS = 90;

    public function __construct(
    ) {}

    /**
     * Analyze a single content item and identify refresh opportunities.
     *
     * @return array{needs_refresh: bool, reasons: list<array{type: string, detail: string, severity: string}>, priority: string, urgency_score: float}
     */
    public function analyze(string $contentId): array
    {
        $content = Content::find($contentId);
        if (! $content) {
            return ['needs_refresh' => false, 'reasons' => [], 'priority' => 'low', 'urgency_score' => 0.0];
        }

        $reasons = [];
        $urgencyScore = 0.0;

        $decliningResult = $this->checkDecliningViews($contentId);
        if ($decliningResult) {
            $reasons[] = $decliningResult;
            $urgencyScore += 30.0;
        }

        $bounceResult = $this->checkHighBounceRate($contentId);
        if ($bounceResult) {
            $reasons[] = $bounceResult;
            $urgencyScore += 20.0;
        }

        $scrollResult = $this->checkLowScrollDepth($contentId);
        if ($scrollResult) {
            $reasons[] = $scrollResult;
            $urgencyScore += 15.0;
        }

        $stalenessResult = $this->checkStaleness($content);
        if ($stalenessResult) {
            $reasons[] = $stalenessResult;
            $urgencyScore += 25.0;
        }

        $underperformingResult = $this->checkUnderperforming($contentId, $content->space_id);
        if ($underperformingResult) {
            $reasons[] = $underperformingResult;
            $urgencyScore += 10.0;
        }

        $urgencyScore = min(100.0, $urgencyScore);
        $priority = $this->determinePriority($urgencyScore);

        return [
            'needs_refresh' => count($reasons) > 0,
            'reasons' => $reasons,
            'priority' => $priority,
            'urgency_score' => round($urgencyScore, 2),
        ];
    }

    /**
     * Batch generate refresh suggestions for all content in a space.
     *
     * @return Collection<int, ContentRefreshSuggestion>
     */
    public function generateSuggestions(string $spaceId): Collection
    {
        $contentIds = Content::where('space_id', $spaceId)
            ->where('status', 'published')
            ->pluck('id');

        $suggestions = new Collection;

        foreach ($contentIds as $contentId) {
            $analysis = $this->analyze($contentId);

            if (! $analysis['needs_refresh']) {
                continue;
            }

            $suggestion = ContentRefreshSuggestion::updateOrCreate(
                [
                    'space_id' => $spaceId,
                    'content_id' => $contentId,
                    'status' => 'pending',
                ],
                [
                    'trigger_type' => $this->determineTriggerType($analysis['reasons']),
                    'performance_context' => $this->buildPerformanceContext($contentId, $spaceId),
                    'suggestions' => $this->buildSuggestionList($analysis['reasons']),
                    'urgency_score' => $analysis['urgency_score'],
                    'triggered_at' => now(),
                ],
            );

            $suggestions->push($suggestion);
        }

        return $suggestions->sortByDesc('urgency_score')->values();
    }

    /**
     * @return array{type: string, detail: string, severity: string}|null
     */
    private function checkDecliningViews(string $contentId): ?array
    {
        $snapshots = ContentPerformanceSnapshot::where('content_id', $contentId)
            ->where('period_type', 'weekly')
            ->orderByDesc('period_start')
            ->take(self::DECLINING_WEEKS_THRESHOLD + 1)
            ->get();

        if ($snapshots->count() < self::DECLINING_WEEKS_THRESHOLD + 1) {
            return null;
        }

        $views = $snapshots->pluck('views')->toArray();
        $declining = true;
        for ($i = 0; $i < count($views) - 1; $i++) {
            if ($views[$i] >= $views[$i + 1]) {
                $declining = false;
                break;
            }
        }

        if (! $declining) {
            return null;
        }

        $oldest = end($views);
        $dropPercent = $oldest > 0
            ? round((1 - $views[0] / max(1, $oldest)) * 100, 1)
            : 0;

        return [
            'type' => 'declining_views',
            'detail' => sprintf('Views declined for %d+ consecutive weeks (%.1f%% drop)', self::DECLINING_WEEKS_THRESHOLD, $dropPercent),
            'severity' => 'high',
        ];
    }

    /**
     * @return array{type: string, detail: string, severity: string}|null
     */
    private function checkHighBounceRate(string $contentId): ?array
    {
        $snapshot = ContentPerformanceSnapshot::where('content_id', $contentId)
            ->where('period_type', 'weekly')
            ->orderByDesc('period_start')
            ->first();

        if (! $snapshot || $snapshot->bounce_rate === null) {
            return null;
        }

        if ((float) $snapshot->bounce_rate < self::HIGH_BOUNCE_RATE) {
            return null;
        }

        return [
            'type' => 'high_bounce_rate',
            'detail' => sprintf('Bounce rate is %.0f%% (threshold: %.0f%%)', (float) $snapshot->bounce_rate * 100, self::HIGH_BOUNCE_RATE * 100),
            'severity' => 'medium',
        ];
    }

    /**
     * @return array{type: string, detail: string, severity: string}|null
     */
    private function checkLowScrollDepth(string $contentId): ?array
    {
        $snapshot = ContentPerformanceSnapshot::where('content_id', $contentId)
            ->where('period_type', 'weekly')
            ->orderByDesc('period_start')
            ->first();

        if (! $snapshot || $snapshot->avg_scroll_depth === null) {
            return null;
        }

        if ((float) $snapshot->avg_scroll_depth > self::LOW_SCROLL_DEPTH) {
            return null;
        }

        return [
            'type' => 'low_scroll_depth',
            'detail' => sprintf('Scroll depth is %.0f%% (threshold: %.0f%%)', (float) $snapshot->avg_scroll_depth * 100, self::LOW_SCROLL_DEPTH * 100),
            'severity' => 'medium',
        ];
    }

    /**
     * @return array{type: string, detail: string, severity: string}|null
     */
    private function checkStaleness(Content $content): ?array
    {
        $lastUpdate = $content->updated_at;
        $daysSinceUpdate = $lastUpdate ? (int) $lastUpdate->diffInDays(Carbon::now()) : 999;

        if ($daysSinceUpdate < self::STALENESS_DAYS) {
            return null;
        }

        return [
            'type' => 'staleness',
            'detail' => sprintf('Content not updated in %d days (threshold: %d days)', $daysSinceUpdate, self::STALENESS_DAYS),
            'severity' => 'high',
        ];
    }

    /**
     * @return array{type: string, detail: string, severity: string}|null
     */
    private function checkUnderperforming(string $contentId, string $spaceId): ?array
    {
        $latestSnapshot = ContentPerformanceSnapshot::where('content_id', $contentId)
            ->where('period_type', 'weekly')
            ->orderByDesc('period_start')
            ->first();

        if (! $latestSnapshot) {
            return null;
        }

        $spaceAvg = ContentPerformanceSnapshot::where('space_id', $spaceId)
            ->where('period_type', 'weekly')
            ->where('period_start', $latestSnapshot->period_start)
            ->avg('composite_score');

        if ($spaceAvg === null || (float) $spaceAvg <= 0) {
            return null;
        }

        $ratio = (float) $latestSnapshot->composite_score / (float) $spaceAvg;

        if ($ratio >= 0.5) {
            return null;
        }

        return [
            'type' => 'underperforming',
            'detail' => sprintf('Composite score (%.1f) is below 50%% of space average (%.1f)', (float) $latestSnapshot->composite_score, (float) $spaceAvg),
            'severity' => 'low',
        ];
    }

    private function determinePriority(float $urgencyScore): string
    {
        if ($urgencyScore >= 50) {
            return 'high';
        }

        if ($urgencyScore >= 25) {
            return 'medium';
        }

        return 'low';
    }

    /**
     * @param  list<array{type: string, detail: string, severity: string}>  $reasons
     */
    private function determineTriggerType(array $reasons): string
    {
        $types = array_column($reasons, 'type');

        if (in_array('declining_views', $types, true)) {
            return 'performance_drop';
        }

        if (in_array('staleness', $types, true)) {
            return 'staleness';
        }

        return 'performance_drop';
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPerformanceContext(string $contentId, string $spaceId): array
    {
        $latest = ContentPerformanceSnapshot::where('content_id', $contentId)
            ->where('period_type', 'weekly')
            ->orderByDesc('period_start')
            ->first();

        $spaceModel = SpacePerformanceModel::where('space_id', $spaceId)->first();

        return [
            'current_score' => $latest ? (float) $latest->composite_score : 0,
            'current_views' => $latest ? $latest->views : 0,
            'bounce_rate' => $latest ? (float) $latest->bounce_rate : null,
            'scroll_depth' => $latest ? (float) $latest->avg_scroll_depth : null,
            'space_avg_score' => $spaceModel ? (float) $spaceModel->model_confidence : null,
        ];
    }

    /**
     * @param  list<array{type: string, detail: string, severity: string}>  $reasons
     * @return list<array{type: string, priority: string, detail: string}>
     */
    private function buildSuggestionList(array $reasons): array
    {
        $suggestions = [];

        foreach ($reasons as $reason) {
            $actionType = match ($reason['type']) {
                'declining_views' => 'update_content',
                'high_bounce_rate' => 'improve_engagement',
                'low_scroll_depth' => 'add_visuals',
                'staleness' => 'update_statistics',
                'underperforming' => 'optimize_seo',
                default => 'review',
            };

            $suggestions[] = [
                'type' => $actionType,
                'priority' => $reason['severity'],
                'detail' => $reason['detail'],
            ];
        }

        return $suggestions;
    }
}
