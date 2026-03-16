<?php

namespace App\Services\Competitor;

use App\Models\CompetitorSource;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Monitors the health of competitor source crawlers.
 * Reports stale, high-error, or inactive sources.
 */
class CrawlerHealthMonitor
{
    private const STALE_THRESHOLD_HOURS = 24;

    private const MAX_ERROR_COUNT = 5;

    /**
     * @return Collection<int, array{source: CompetitorSource, issues: array<int, string>}>
     */
    public function unhealthySources(): Collection
    {
        return CompetitorSource::where('is_active', true)
            ->get()
            ->filter(fn (CompetitorSource $source) => count($this->issuesFor($source)) > 0)
            ->map(fn (CompetitorSource $source) => [
                'source' => $source,
                'issues' => $this->issuesFor($source),
            ])
            ->values();
    }

    /**
     * @return array<int, string>
     */
    public function issuesFor(CompetitorSource $source): array
    {
        $issues = [];

        if ($source->error_count >= self::MAX_ERROR_COUNT) {
            $issues[] = "High error count: {$source->error_count} errors";
        }

        if (! $source->last_crawled_at) {
            $issues[] = 'Never crawled';
        } elseif ($source->last_crawled_at->diffInHours(Carbon::now()) >= self::STALE_THRESHOLD_HOURS) {
            $hours = $source->last_crawled_at->diffInHours(Carbon::now());
            $issues[] = "Stale: last crawled {$hours}h ago";
        }

        return $issues;
    }

    /**
     * Run a health check and log warnings for any unhealthy sources.
     *
     * @return array{healthy: int, unhealthy: int, issues: array<int, array{source_id: string, source_name: string, issues: array<int, string>}>}
     */
    public function check(): array
    {
        $unhealthy = $this->unhealthySources();
        $total = CompetitorSource::where('is_active', true)->count();

        $issues = $unhealthy->map(fn (array $entry) => [
            'source_id' => $entry['source']->id,
            'source_name' => $entry['source']->name,
            'issues' => $entry['issues'],
        ])->all();

        if (! empty($issues)) {
            Log::warning('CrawlerHealthMonitor: unhealthy sources detected', [
                'count' => count($issues),
                'issues' => $issues,
            ]);
        }

        return [
            'healthy' => $total - count($issues),
            'unhealthy' => count($issues),
            'issues' => $issues,
        ];
    }
}
