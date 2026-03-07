<?php

namespace App\Services\Search;

/**
 * Conversational (RAG) query value object.
 */
final class AskQuery
{
    public function __construct(
        public readonly string $question,
        public readonly string $spaceId,
        public readonly ?string $conversationId = null,
        public readonly ?string $locale = null,
        public readonly ?string $sessionId = null,
    ) {}
}
