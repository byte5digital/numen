<?php

namespace App\Services\Anthropic;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class CostTracker
{
    /**
     * Calculate cost for a given model and token usage.
     * Pricing as of early 2026 (per million tokens).
     */
    public function calculateCost(string $model, int $inputTokens, int $outputTokens, int $cacheTokens = 0): float
    {
        [$inputPrice, $outputPrice, $cachePrice] = match (true) {
            str_contains($model, 'opus') => [15.0, 75.0, 1.50],
            str_contains($model, 'sonnet') => [3.0, 15.0, 0.30],
            str_contains($model, 'haiku') => [0.80, 4.0, 0.08],
            default => [3.0, 15.0, 0.30], // default to Sonnet pricing
        };

        return ($inputTokens * $inputPrice + $outputTokens * $outputPrice + $cacheTokens * $cachePrice) / 1_000_000;
    }

    /**
     * Record usage against daily budget. Returns true if within budget.
     */
    public function recordUsage(float $costUsd, ?string $spaceId = null): bool
    {
        $key = 'ai_cost:daily:'.($spaceId ?? 'global').':'.now()->format('Y-m-d');
        $dailySpend = (float) Cache::get($key, 0) + $costUsd;
        Cache::put($key, $dailySpend, now()->endOfDay());

        $limit = config('numen.cost_limits.daily_usd', 50);

        if ($dailySpend > $limit) {
            Log::warning('AI daily cost limit exceeded', [
                'daily_spend' => $dailySpend,
                'limit' => $limit,
                'space_id' => $spaceId,
            ]);

            return false;
        }

        return true;
    }

    /**
     * Get current daily spend.
     */
    public function getDailySpend(?string $spaceId = null): float
    {
        $key = 'ai_cost:daily:'.($spaceId ?? 'global').':'.now()->format('Y-m-d');

        return (float) Cache::get($key, 0);
    }
}
