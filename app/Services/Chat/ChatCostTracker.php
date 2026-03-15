<?php

namespace App\Services\Chat;

use App\Models\ChatMessage;
use App\Services\AI\CostTracker;
use Illuminate\Support\Facades\Log;

/**
 * Chat-specific cost tracking wrapper around the global CostTracker.
 *
 * Persists token counts + cost to ChatMessage records and logs per-chat
 * usage to the global CostTracker under the 'chat' category.
 */
class ChatCostTracker
{
    public function __construct(
        private readonly CostTracker $costTracker,
    ) {}

    /**
     * Update a ChatMessage with token/cost data and record to global tracker.
     */
    public function trackMessage(
        ChatMessage $message,
        int $inputTokens,
        int $outputTokens,
        float $costUsd,
    ): void {
        $message->update([
            'input_tokens' => $inputTokens,
            'output_tokens' => $outputTokens,
            'cost_usd' => $costUsd,
        ]);

        $spaceId = $message->conversation->space_id ?? null;

        $this->costTracker->recordUsage($costUsd, $spaceId);

        Log::info('ChatCostTracker: message tracked', [
            'message_id' => $message->id,
            'conversation_id' => $message->conversation_id,
            'input_tokens' => $inputTokens,
            'output_tokens' => $outputTokens,
            'cost_usd' => $costUsd,
            'category' => 'chat',
        ]);
    }

    /**
     * Sum of cost_usd for all messages in a conversation.
     */
    public function getConversationCost(string $conversationId): float
    {
        return (float) ChatMessage::where('conversation_id', $conversationId)
            ->sum('cost_usd');
    }

    /**
     * Sum of cost_usd for a user's messages today.
     * Used for optional per-user rate limiting.
     */
    public function getUserDailyCost(int $userId): float
    {
        return (float) ChatMessage::whereHas(
            'conversation',
            fn ($q) => $q->where('user_id', $userId)
        )
            ->whereDate('created_at', now()->toDateString())
            ->sum('cost_usd');
    }
}
