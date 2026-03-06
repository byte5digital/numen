<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Provider-agnostic cost tracker.
 * Pricing is per million tokens as of early 2026.
 */
class CostTracker
{
    /** @var array<string, array{input: float, output: float, cache: float}> */
    private static array $pricing = [
        // Anthropic
        'claude-opus-4-6' => ['input' => 15.0,  'output' => 75.0,  'cache' => 1.50],
        'claude-opus-4' => ['input' => 15.0,  'output' => 75.0,  'cache' => 1.50],
        'claude-sonnet-4-6' => ['input' => 3.0,   'output' => 15.0,  'cache' => 0.30],
        'claude-sonnet-4' => ['input' => 3.0,   'output' => 15.0,  'cache' => 0.30],
        'claude-haiku-4-5-20251001' => ['input' => 0.80,  'output' => 4.0,   'cache' => 0.08],
        'claude-3-5-haiku-20241022' => ['input' => 0.80,  'output' => 4.0,   'cache' => 0.08],
        'claude-3-5-sonnet-20241022' => ['input' => 3.0,   'output' => 15.0,  'cache' => 0.30],
        // GPT-5 (2025)
        'gpt-5' => ['input' => 75.0,  'output' => 300.0, 'cache' => 37.50],
        'gpt-5-mini' => ['input' => 1.10,  'output' => 4.40,  'cache' => 0.275],
        // OpenAI — GPT-4.1 family (2025)
        'gpt-4.1' => ['input' => 2.00,  'output' => 8.0,   'cache' => 0.50],
        'gpt-4.1-mini' => ['input' => 0.40,  'output' => 1.60,  'cache' => 0.10],
        'gpt-4.1-nano' => ['input' => 0.10,  'output' => 0.40,  'cache' => 0.025],
        // GPT-4.5
        'gpt-4.5-preview' => ['input' => 75.0,  'output' => 150.0, 'cache' => 37.50],
        // GPT-4o family
        'gpt-4o' => ['input' => 2.50,  'output' => 10.0,  'cache' => 1.25],
        'gpt-4o-mini' => ['input' => 0.15,  'output' => 0.60,  'cache' => 0.075],
        'gpt-4-turbo' => ['input' => 10.0,  'output' => 30.0,  'cache' => 0.0],
        'gpt-3.5-turbo' => ['input' => 0.50,  'output' => 1.50,  'cache' => 0.0],
        // o-series reasoning models
        'o4-mini' => ['input' => 1.10,  'output' => 4.40,  'cache' => 0.275],
        'o3' => ['input' => 10.0,  'output' => 40.0,  'cache' => 2.50],
        'o3-mini' => ['input' => 1.10,  'output' => 4.40,  'cache' => 0.55],
        'o1' => ['input' => 15.0,  'output' => 60.0,  'cache' => 7.50],
        'o1-mini' => ['input' => 3.0,   'output' => 12.0,  'cache' => 1.50],
        // Azure AI Foundry (same pricing as OpenAI equivalents by default)
        'azure-gpt-4o' => ['input' => 2.50,  'output' => 10.0,  'cache' => 0.0],
        'azure-gpt-4o-mini' => ['input' => 0.165, 'output' => 0.66,  'cache' => 0.0],
    ];

    public function calculateCost(
        string $model,
        int $inputTokens,
        int $outputTokens,
        int $cacheTokens = 0,
    ): float {
        $prices = $this->getPricing($model);

        return ($inputTokens * $prices['input']
            + $outputTokens * $prices['output']
            + $cacheTokens * $prices['cache'])
            / 1_000_000;
    }

    public function recordUsage(float $costUsd, ?string $spaceId = null): bool
    {
        $scope = $spaceId ?? 'global';

        // Daily tracking
        $dayKey = 'ai_cost:daily:'.$scope.':'.now()->format('Y-m-d');
        $dailySpend = (float) Cache::get($dayKey, 0) + $costUsd;
        Cache::put($dayKey, $dailySpend, now()->endOfDay());

        // Monthly tracking
        $monthKey = 'ai_cost:monthly:'.$scope.':'.now()->format('Y-m');
        $monthlySpend = (float) Cache::get($monthKey, 0) + $costUsd;
        Cache::put($monthKey, $monthlySpend, now()->endOfMonth());

        $dailyLimit = config('numen.cost_limits.daily_usd', 50);
        $monthlyLimit = config('numen.cost_limits.monthly_usd', 500);

        if ($dailySpend > $dailyLimit) {
            Log::warning('AI daily cost limit exceeded', [
                'daily_spend' => $dailySpend,
                'limit' => $dailyLimit,
                'space_id' => $spaceId,
            ]);

            return false;
        }

        if ($monthlySpend > $monthlyLimit) {
            Log::warning('AI monthly cost limit exceeded', [
                'monthly_spend' => $monthlySpend,
                'limit' => $monthlyLimit,
                'space_id' => $spaceId,
            ]);

            return false;
        }

        return true;
    }

    /**
     * Check if cost limits would be exceeded WITHOUT recording usage.
     * Used for pre-flight checks before making API calls.
     */
    public function isWithinLimits(?string $spaceId = null): bool
    {
        $scope = $spaceId ?? 'global';

        $dayKey = 'ai_cost:daily:'.$scope.':'.now()->format('Y-m-d');
        $dailySpend = (float) Cache::get($dayKey, 0);
        $dailyLimit = config('numen.cost_limits.daily_usd', 50);

        if ($dailySpend >= $dailyLimit) {
            return false;
        }

        $monthKey = 'ai_cost:monthly:'.$scope.':'.now()->format('Y-m');
        $monthlySpend = (float) Cache::get($monthKey, 0);
        $monthlyLimit = config('numen.cost_limits.monthly_usd', 500);

        if ($monthlySpend >= $monthlyLimit) {
            return false;
        }

        return true;
    }

    public function getDailySpend(?string $spaceId = null): float
    {
        $key = 'ai_cost:daily:'.($spaceId ?? 'global').':'.now()->format('Y-m-d');

        return (float) Cache::get($key, 0);
    }

    public function getMonthlySpend(?string $spaceId = null): float
    {
        $key = 'ai_cost:monthly:'.($spaceId ?? 'global').':'.now()->format('Y-m');

        return (float) Cache::get($key, 0);
    }

    private function getPricing(string $model): array
    {
        // Exact match first
        if (isset(self::$pricing[$model])) {
            return self::$pricing[$model];
        }

        // Fuzzy match by model family
        foreach (self::$pricing as $pattern => $prices) {
            if (str_starts_with($model, $pattern)) {
                return $prices;
            }
        }

        // Default: Sonnet-equivalent pricing
        return ['input' => 3.0, 'output' => 15.0, 'cache' => 0.0];
    }
}
