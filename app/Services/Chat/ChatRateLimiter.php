<?php

namespace App\Services\Chat;

use App\Models\ChatMessage;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

/**
 * Checks per-minute message rate and daily cost limit for a user.
 */
class ChatRateLimiter
{
    public function __construct(
        private readonly ChatCostTracker $costTracker,
    ) {}

    /**
     * Returns true when the user is within all limits, false when over-limit.
     */
    public function check(User $user): bool
    {
        $maxPerMinute = (int) config('numen.chat.max_messages_per_minute', 20);
        $maxDailyCost = (float) config('numen.chat.max_daily_cost_per_user', 1.00);

        if ($this->getMessagesLastMinute($user) >= $maxPerMinute) {
            return false;
        }

        if ($this->costTracker->getUserDailyCost($user->id) >= $maxDailyCost) {
            return false;
        }

        return true;
    }

    /**
     * Returns remaining quota information for the user.
     *
     * @return array{messages_remaining: int, cost_remaining: float, resets_at: string}
     */
    public function getRemainingQuota(User $user): array
    {
        $maxPerMinute = (int) config('numen.chat.max_messages_per_minute', 20);
        $maxDailyCost = (float) config('numen.chat.max_daily_cost_per_user', 1.00);

        $usedLastMinute = $this->getMessagesLastMinute($user);
        $dailyCost = $this->costTracker->getUserDailyCost($user->id);

        return [
            'messages_remaining' => max(0, $maxPerMinute - $usedLastMinute),
            'cost_remaining' => max(0.0, round($maxDailyCost - $dailyCost, 6)),
            'resets_at' => now()->addMinute()->toIso8601String(),
        ];
    }

    /**
     * Count messages sent by a user in the last 60 seconds.
     */
    private function getMessagesLastMinute(User $user): int
    {
        $cacheKey = "chat_rate_limit:{$user->id}:minute";

        return (int) Cache::remember($cacheKey, 60, function () use ($user): int {
            return ChatMessage::whereHas(
                'conversation',
                fn ($q) => $q->where('user_id', $user->id)
            )
                ->where('role', 'user')
                ->where('created_at', '>=', now()->subMinute())
                ->count();
        });
    }
}
