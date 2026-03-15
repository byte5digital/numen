<?php

namespace App\Plugin\Contracts;

/**
 * Contract for plugin-provided LLM providers.
 *
 * Implementations are registered via HookRegistry::registerLLMProvider() and
 * wired into LLMManager at boot time via AppServiceProvider.
 */
interface LLMProviderContract
{
    /**
     * Unique provider identifier (e.g. "my-company-llm").
     * Used as the key when referencing this provider in model strings.
     */
    public function name(): string;

    /**
     * Generate a text completion for the given prompt.
     *
     * @param  array<string, mixed>  $options  Provider-specific options (model, temperature, …)
     */
    public function generateText(string $prompt, array $options = []): string;

    /**
     * Generate a chat completion for a list of messages.
     *
     * Each message is an associative array with at least 'role' and 'content' keys.
     *
     * @param  array<int, array<string, string>>  $messages
     * @param  array<string, mixed>  $options
     */
    public function generateChat(array $messages, array $options = []): string;

    /**
     * Whether this provider supports streaming responses.
     */
    public function supportsStreaming(): bool;
}
