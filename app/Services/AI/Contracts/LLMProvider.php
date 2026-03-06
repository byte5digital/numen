<?php

namespace App\Services\AI\Contracts;

use App\Services\AI\LLMResponse;

/**
 * Unified interface for all LLM providers (Anthropic, OpenAI, Azure AI Foundry).
 */
interface LLMProvider
{
    /**
     * Complete a chat/message request.
     *
     * Normalized params:
     *   model       string  — model identifier (without provider prefix)
     *   system      string  — system prompt
     *   messages    array   — [['role' => 'user'|'assistant', 'content' => '...']]
     *   max_tokens  int
     *   temperature float
     *   _purpose    string  — internal label for logging
     *
     * @throws \App\Services\AI\Exceptions\ProviderRateLimitException on 429
     * @throws \App\Services\AI\Exceptions\ProviderUnavailableException on 500/503
     * @throws \RuntimeException on other errors
     */
    public function complete(array $params): LLMResponse;

    /**
     * Whether this provider is currently available (not rate-limited, API key set).
     */
    public function isAvailable(string $model): bool;

    /**
     * Provider name for logging/display ("anthropic", "openai", "azure").
     */
    public function getName(): string;
}
