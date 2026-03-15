<?php

namespace App\Services\Chat;

use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Services\AI\LLMManager;
use Illuminate\Support\Facades\Log;

/**
 * Manages conversation context windowing and summarization.
 *
 * Keeps token usage bounded by:
 * 1. Returning only the last N messages (window)
 * 2. Summarizing older messages via cheap haiku LLM call
 * 3. Prepending the stored summary to the context window
 */
class ConversationContextManager
{
    private const SUMMARY_THRESHOLD = 30;

    private const WINDOW_SIZE = 15;

    private const SUMMARY_MODEL = 'claude-haiku-4-5-20251001';

    public function __construct(
        private readonly LLMManager $llmManager,
    ) {}

    /**
     * Load the last $windowSize messages and return them as LLM-ready [{role, content}] array.
     *
     * @return array<int, array{role: string, content: string}>
     */
    public function buildContext(ChatConversation $conversation, int $windowSize = self::WINDOW_SIZE): array
    {
        /** @var \Illuminate\Database\Eloquent\Collection<int, ChatMessage> $messages */
        $messages = $conversation->messages()
            ->orderByDesc('created_at')
            ->limit($windowSize)
            ->get()
            ->reverse()
            ->values();

        return $messages->map(fn (ChatMessage $msg) => [
            'role' => $msg->role,
            'content' => $msg->content,
        ])->values()->all();
    }

    /**
     * If the conversation exceeds the threshold, summarize older messages and
     * store the summary in conversation.context['summary'].
     */
    public function summarizeOlder(ChatConversation $conversation): void
    {
        $totalCount = $conversation->messages()->count();

        if ($totalCount <= self::SUMMARY_THRESHOLD) {
            return;
        }

        // Messages older than the window (skip the recent window)
        /** @var \Illuminate\Database\Eloquent\Collection<int, ChatMessage> $olderMessages */
        $olderMessages = $conversation->messages()
            ->orderBy('created_at')
            ->limit($totalCount - self::WINDOW_SIZE)
            ->get();

        if ($olderMessages->isEmpty()) {
            return;
        }

        // Format them for the summarization prompt
        $transcript = $olderMessages->map(fn (ChatMessage $msg) => strtoupper($msg->role).': '.$msg->content)
            ->join("\n\n");

        try {
            $response = $this->llmManager->complete([
                'model' => self::SUMMARY_MODEL,
                'system' => 'You are a concise summarizer. Summarize the conversation excerpt into a single compact paragraph that captures key facts, decisions, and user intent. Write in third person.',
                'messages' => [
                    ['role' => 'user', 'content' => "Summarize this conversation history:\n\n".$transcript],
                ],
                'max_tokens' => 512,
                'temperature' => 0.2,
                '_purpose' => 'cms_chat_summarize',
            ]);

            $summary = trim($response->content);
            $lastMessageId = $olderMessages->last()->id;

            $context = $conversation->context ?? [];
            $context['summary'] = $summary;
            $context['summary_covers_up_to'] = $lastMessageId;

            $conversation->update(['context' => $context]);

            Log::info('ConversationContextManager: stored summary', [
                'conversation_id' => $conversation->id,
                'messages_summarized' => $olderMessages->count(),
            ]);
        } catch (\Throwable $e) {
            // Non-fatal: log and continue without summary
            Log::warning('ConversationContextManager: summarization failed', [
                'conversation_id' => $conversation->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Return the full context to send to the LLM:
     * [stored summary as synthetic exchange (if any)] + [recent window messages]
     *
     * @return array<int, array{role: string, content: string}>
     */
    public function getFullContext(ChatConversation $conversation): array
    {
        $context = [];

        // Prepend stored summary as a synthetic user+assistant exchange
        $stored = $conversation->context ?? [];
        if (! empty($stored['summary'])) {
            $context[] = [
                'role' => 'user',
                'content' => '[Earlier conversation summary]',
            ];
            $context[] = [
                'role' => 'assistant',
                'content' => $stored['summary'],
            ];
        }

        // Append recent window
        $window = $this->buildContext($conversation, self::WINDOW_SIZE);

        return array_merge($context, $window);
    }
}
