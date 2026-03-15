<?php

namespace App\Services\Chat;

use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\Space;
use App\Models\User;
use App\Services\AI\CostTracker;
use App\Services\AI\LLMManager;
use Generator;
use Illuminate\Support\Facades\Log;

/**
 * Handles the CMS conversational assistant flow.
 *
 * The handle() method returns a Generator that yields typed chunks:
 *   {type: "text",   content: "..."}
 *   {type: "intent", intent: {action, entity, params, confidence, requires_confirmation}}
 *   {type: "done",   cost_usd: float}
 *
 * The LLM is prompted to respond with JSON:
 * {
 *   "message": "human-readable reply",
 *   "intent": {
 *     "action": "content.query|content.create|...",
 *     "entity": "content|pipeline|...",
 *     "params": {...},
 *     "confidence": 0.0-1.0,
 *     "requires_confirmation": bool
 *   }
 * }
 */
class ConversationService
{
    public function __construct(
        private readonly LLMManager $llmManager,
        private readonly CostTracker $costTracker,
        private readonly ConversationContextManager $contextManager,
        private readonly SystemPromptBuilder $systemPromptBuilder,
    ) {}

    /**
     * Process a chat message and yield typed chunks for streaming.
     *
     * @return Generator<int, array<string, mixed>>
     */
    public function handle(
        User $user,
        Space $space,
        string $conversationId,
        string $message,
    ): Generator {
        // 1. Load conversation
        $conversation = ChatConversation::where('id', $conversationId)
            ->where('space_id', $space->id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        // 2. Build full context via context manager (summary + window)
        $llmMessages = $this->contextManager->getFullContext($conversation);

        // Append the new user message
        $llmMessages[] = ['role' => 'user', 'content' => $message];

        // 3. Save user message to DB
        $conversation->messages()->create([
            'role' => 'user',
            'content' => $message,
        ]);

        // 4. Build system prompt using SystemPromptBuilder
        $systemPrompt = $this->systemPromptBuilder->build($space, $user);

        // 5. Call LLM
        try {
            $response = $this->llmManager->complete([
                'model' => config('numen.chat.model', 'claude-haiku-4-5-20251001'),
                'system' => $systemPrompt,
                'messages' => $llmMessages,
                'max_tokens' => 1024,
                'temperature' => 0.4,
                '_purpose' => 'cms_chat',
            ]);
        } catch (\Throwable $e) {
            Log::error('ConversationService: LLM call failed', [
                'conversation_id' => $conversationId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }

        // 6. Parse LLM response (JSON with message + intent)
        $raw = $response->content;
        $parsed = $this->parseResponse($raw);

        $humanMessage = $parsed['message'] ?? $raw;
        $intent = $parsed['intent'] ?? null;

        // 7. Yield text chunk
        yield ['type' => 'text', 'content' => $humanMessage];

        // 8. Yield intent chunk if present
        if ($intent !== null && isset($intent['action'])) {
            yield ['type' => 'intent', 'intent' => $intent];
        }

        // 9. Save assistant message to DB
        /** @var ChatMessage $assistantMessage */
        $assistantMessage = $conversation->messages()->create([
            'role' => 'assistant',
            'content' => $humanMessage,
            'intent' => $intent,
            'input_tokens' => $response->inputTokens,
            'output_tokens' => $response->outputTokens,
            'cost_usd' => $response->costUsd,
        ]);

        // 10. Update conversation last_active_at
        $conversation->update(['last_active_at' => now()]);

        // 11. Track cost
        $this->costTracker->recordUsage($response->costUsd, $space->id);

        // 12. Trigger summarization if conversation is long enough
        $this->contextManager->summarizeOlder($conversation);

        // 13. Yield done chunk
        yield ['type' => 'done', 'cost_usd' => $response->costUsd];
    }

    /**
     * Parse LLM response — expects JSON but falls back gracefully.
     *
     * @return array<string, mixed>
     */
    private function parseResponse(string $raw): array
    {
        // Strip markdown code fences if present
        $cleaned = (string) preg_replace('/^```(?:json)?\s*/m', '', $raw);
        $cleaned = (string) preg_replace('/\s*```$/m', '', $cleaned);
        $cleaned = trim($cleaned);

        $decoded = json_decode($cleaned, true);

        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return $decoded;
        }

        // Fallback: return raw as message with no intent
        Log::warning('ConversationService: LLM response was not valid JSON', [
            'raw_length' => strlen($raw),
        ]);

        return ['message' => $raw, 'intent' => null];
    }
}
