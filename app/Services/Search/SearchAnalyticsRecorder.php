<?php

namespace App\Services\Search;

use App\Models\SearchAnalytic;
use App\Services\Search\Results\SearchResultCollection;
use Illuminate\Support\Facades\Log;

/**
 * Records search analytics asynchronously.
 * Fire-and-forget: errors are logged but never propagate to the user.
 */
class SearchAnalyticsRecorder
{
    public function record(
        SearchQuery $query,
        SearchResultCollection $results,
        float $elapsedSeconds,
    ): void {
        try {
            SearchAnalytic::create([
                'space_id' => $query->spaceId,
                'query' => $query->query,
                'query_normalized' => strtolower(trim($query->query)),
                'tier' => $results->tierUsed(),
                'results_count' => $results->total(),
                'response_time_ms' => (int) ($elapsedSeconds * 1000),
                'locale' => $query->locale,
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('SearchAnalyticsRecorder: failed to record', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function recordClick(
        string $spaceId,
        string $query,
        string $contentId,
        int $position,
        ?string $sessionId = null,
    ): void {
        try {
            SearchAnalytic::create([
                'space_id' => $spaceId,
                'query' => $query,
                'query_normalized' => strtolower(trim($query)),
                'tier' => 'click',
                'results_count' => 0,
                'clicked_content_id' => $contentId,
                'click_position' => $position,
                'response_time_ms' => 0,
                'session_id' => $sessionId,
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('SearchAnalyticsRecorder: failed to record click', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
