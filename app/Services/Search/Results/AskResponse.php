<?php

namespace App\Services\Search\Results;

/**
 * Response from the conversational RAG pipeline.
 */
final class AskResponse
{
    /**
     * @param  array<int, array<string, mixed>>  $sources
     * @param  array<int, string>  $followUpSuggestions
     */
    public function __construct(
        public readonly string $answer,
        public readonly array $sources,
        public readonly float $confidence,
        public readonly array $followUpSuggestions,
        public readonly ?string $conversationId,
        public readonly string $tierUsed = 'ask',
        public readonly int $tokensUsed = 0,
    ) {}

    public static function noAnswer(string $question, ?string $conversationId = null): self
    {
        return new self(
            answer: "I don't have enough information in the published content to answer that question.",
            sources: [],
            confidence: 0.0,
            followUpSuggestions: [],
            conversationId: $conversationId,
            tierUsed: 'ask',
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'answer' => $this->answer,
            'sources' => $this->sources,
            'confidence' => $this->confidence,
            'follow_ups' => $this->followUpSuggestions,
            'conversation_id' => $this->conversationId,
            'meta' => [
                'tier_used' => $this->tierUsed,
                'tokens_used' => $this->tokensUsed,
            ],
        ];
    }
}
