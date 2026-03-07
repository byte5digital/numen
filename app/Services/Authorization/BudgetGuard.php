<?php

namespace App\Services\Authorization;

use App\Models\AIGenerationLog;
use App\Models\Space;
use App\Models\User;

/**
 * Last line of defense before any LLM / image API call.
 *
 * Checks:
 *  1. User has ai.generate (or ai.image.generate) permission
 *  2. Model is in the allowed_models set (or user has ai.model.* wildcard)
 *  3. Daily generation count is within the role limit
 *  4. Monthly cost is within the role limit
 *  5. Estimated cost doesn't exceed the require_approval_above_cost_usd threshold
 */
class BudgetGuard
{
    public function __construct(
        private readonly AuthorizationService $authz,
    ) {}

    /**
     * Check whether a user may trigger an AI text generation.
     *
     * @param  float  $estimatedCostUsd  Estimated cost of this generation (0 if unknown)
     * @param  string  $model  Model identifier e.g. 'claude-sonnet-4-6'
     */
    public function canGenerate(
        User $user,
        Space $space,
        string $model,
        float $estimatedCostUsd = 0.0,
    ): BudgetCheckResult {
        // Bypass everything for ai.budget.unlimited
        if ($this->authz->can($user, 'ai.budget.unlimited', $space)) {
            return BudgetCheckResult::Allowed;
        }

        // Check generate permission
        if (! $this->authz->can($user, 'ai.generate', $space)) {
            return BudgetCheckResult::Denied;
        }

        // Check model tier permission
        if (! $this->modelAllowed($user, $space, $model)) {
            return BudgetCheckResult::Denied;
        }

        $limits = $this->authz->resolveAiLimits($user, $space);

        // Check daily generation count
        if ($limits['daily_generations'] > 0) {
            $todayCount = $this->todayGenerationCount($user);
            if ($todayCount >= $limits['daily_generations']) {
                return BudgetCheckResult::Denied;
            }
        }

        // Check monthly cost
        if ($limits['monthly_cost_limit_usd'] > 0) {
            $monthCost = $this->monthCostUsd($user);
            if ($monthCost >= $limits['monthly_cost_limit_usd']) {
                return BudgetCheckResult::Denied;
            }
        }

        // Check approval threshold
        if ($estimatedCostUsd > 0 && $limits['require_approval_above_cost_usd'] !== null) {
            if ($estimatedCostUsd > $limits['require_approval_above_cost_usd']) {
                return BudgetCheckResult::NeedsApproval;
            }
        }

        return BudgetCheckResult::Allowed;
    }

    /**
     * Check whether a user may trigger an AI image generation.
     *
     * @param  float  $estimatedCostUsd  Estimated cost of this generation (0 if unknown)
     */
    public function canGenerateImage(
        User $user,
        Space $space,
        float $estimatedCostUsd = 0.0,
    ): BudgetCheckResult {
        if ($this->authz->can($user, 'ai.budget.unlimited', $space)) {
            return BudgetCheckResult::Allowed;
        }

        if (! $this->authz->can($user, 'ai.image.generate', $space)) {
            return BudgetCheckResult::Denied;
        }

        $limits = $this->authz->resolveAiLimits($user, $space);

        // Check daily image generation count
        if ($limits['daily_image_generations'] > 0) {
            $todayCount = $this->todayImageGenerationCount($user);
            if ($todayCount >= $limits['daily_image_generations']) {
                return BudgetCheckResult::Denied;
            }
        }

        // Check monthly cost (shared limit across text + image)
        if ($limits['monthly_cost_limit_usd'] > 0) {
            $monthCost = $this->monthCostUsd($user);
            if ($monthCost >= $limits['monthly_cost_limit_usd']) {
                return BudgetCheckResult::Denied;
            }
        }

        if ($estimatedCostUsd > 0 && $limits['require_approval_above_cost_usd'] !== null) {
            if ($estimatedCostUsd > $limits['require_approval_above_cost_usd']) {
                return BudgetCheckResult::NeedsApproval;
            }
        }

        return BudgetCheckResult::Allowed;
    }

    // ── Private helpers ───────────────────────────────────────────────────

    private function modelAllowed(User $user, Space $space, string $model): bool
    {
        // Wildcard model permissions
        if ($this->authz->can($user, 'ai.model.*', $space) || $this->authz->can($user, '*', $space)) {
            return true;
        }

        // Explicit tier permissions
        $tierMap = $this->modelTierMap();
        $tier = $tierMap[$model] ?? null;

        if ($tier !== null && $this->authz->can($user, "ai.model.{$tier}", $space)) {
            return true;
        }

        // Check allowed_models list from AI limits
        $limits = $this->authz->resolveAiLimits($user, $space);

        return in_array($model, $limits['allowed_models'], true);
    }

    /**
     * Map known model identifiers to their tier permission slug.
     *
     * @return array<string, string>
     */
    private function modelTierMap(): array
    {
        return [
            // Opus tier
            'claude-opus-4-5' => 'opus',
            'claude-opus-4' => 'opus',
            // Sonnet tier
            'claude-sonnet-4-6' => 'sonnet',
            'claude-sonnet-4-5' => 'sonnet',
            'claude-3-5-sonnet' => 'sonnet',
            // Haiku tier
            'claude-haiku-4-5-20251001' => 'haiku',
            'claude-haiku-4-5' => 'haiku',
            'claude-haiku-3-5' => 'haiku',
            'claude-3-haiku' => 'haiku',
        ];
    }

    private function todayGenerationCount(User $user): int
    {
        return AIGenerationLog::query()
            ->whereDate('created_at', today())
            ->where('user_id', $user->id)
            ->count();
    }

    private function todayImageGenerationCount(User $user): int
    {
        return AIGenerationLog::query()
            ->whereDate('created_at', today())
            ->where('purpose', 'image_generation')
            ->where('user_id', $user->id)
            ->count();
    }

    private function monthCostUsd(User $user): float
    {
        return (float) AIGenerationLog::query()
            ->whereYear('created_at', now()->year)
            ->whereMonth('created_at', now()->month)
            ->where('user_id', $user->id)
            ->sum('cost_usd');
    }
}
