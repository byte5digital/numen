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
        // 1. Load conversation + last 15 messages for context
        $conversation = ChatConversation::where('id', $conversationId)
            ->where('space_id', $space->id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $history = $conversation->messages()
            ->orderByDesc('created_at')
            ->limit(15)
            ->get()
            ->reverse()
            ->values();

        // 2. Build messages array for LLM (conversation history)
        $llmMessages = $history->map(fn (ChatMessage $msg) => [
            'role' => $msg->role,
            'content' => $msg->content,
        ])->values()->all();

        // Append the new user message
        $llmMessages[] = ['role' => 'user', 'content' => $message];

        // 3. Save user message to DB
        $conversation->messages()->create([
            'role' => 'user',
            'content' => $message,
        ]);

        // 4. Build system prompt
        $systemPrompt = $this->buildSystemPrompt($user, $space);

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
        $conversation->messages()->create([
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

        // 12. Yield done chunk
        yield ['type' => 'done', 'cost_usd' => $response->costUsd];
    }

    /**
     * Build the CMS assistant system prompt with available actions based on user permissions.
     */
    private function buildSystemPrompt(User $user, Space $space): string
    {
        $actions = $this->buildAvailableActions($user, $space);
        $actionsJson = json_encode($actions, JSON_PRETTY_PRINT);
        $spaceName = $space->name;
        $userName = $user->name;

        return <<<PROMPT
            You are a CMS assistant for the "{$spaceName}" space, helping {$userName} manage their content.

            You have access to the following actions based on the user's permissions:
            {$actionsJson}

            RESPONSE FORMAT (always respond with valid JSON):
            {
              "message": "Human-readable response to the user",
              "intent": {
                "action": "one of the available actions or null",
                "entity": "content|pipeline|null",
                "params": {},
                "confidence": 0.0,
                "requires_confirmation": false
              }
            }

            RULES:
            1. Always respond with valid JSON matching the format above.
            2. If no action is needed, set "action" to "query.generic" and "requires_confirmation" to false.
            3. For destructive actions (delete, publish), set "requires_confirmation" to true unless the user explicitly confirmed.
            4. Keep "message" conversational and helpful.
            5. Set "confidence" between 0.0 and 1.0 based on how certain you are about the intent.
            6. Never perform actions the user doesn't have permission for.
            7. If asked to do something not in the available actions, explain politely that you don't have permission.
            PROMPT;
    }

    /**
     * Build list of available actions based on user permissions.
     *
     * @return array<string, string>
     */
    private function buildAvailableActions(User $user, Space $space): array
    {
        $actions = ['query.generic' => 'Answer general questions about content in the space'];

        if ($user->isAdmin() || $user->hasPermission('content.view', $space->id)) {
            $actions['content.query'] = 'Query and list content items with filters';
        }

        if ($user->isAdmin() || $user->hasPermission('content.create', $space->id)) {
            $actions['content.create'] = 'Create new content or a content brief';
        }

        if ($user->isAdmin() || $user->hasPermission('content.update', $space->id)) {
            $actions['content.update'] = 'Update existing content fields';
        }

        if ($user->isAdmin() || $user->hasPermission('content.delete', $space->id)) {
            $actions['content.delete'] = 'Delete content (requires confirmation)';
        }

        if ($user->isAdmin() || $user->hasPermission('content.publish', $space->id)) {
            $actions['content.publish'] = 'Publish content (requires confirmation)';
            $actions['content.unpublish'] = 'Unpublish / archive content (requires confirmation)';
        }

        if ($user->isAdmin() || $user->hasPermission('pipeline.trigger', $space->id)) {
            $actions['pipeline.trigger'] = 'Trigger a content pipeline run';
        }

        return $actions;
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
