<?php

namespace App\Services\AI;

/**
 * Normalized response from any LLM provider.
 */
readonly class LLMResponse
{
    public function __construct(
        public string $content,
        public string $model,
        public string $provider,
        public int    $inputTokens,
        public int    $outputTokens,
        public float  $costUsd,
        public int    $latencyMs,
        public string $stopReason = 'end_turn',
        public array  $raw = [],
    ) {}

    public function totalTokens(): int
    {
        return $this->inputTokens + $this->outputTokens;
    }
}
