<?php

namespace App\Agents;

class AgentResult
{
    public function __construct(
        public readonly bool $success,
        public readonly array $data = [],
        public readonly ?string $text = null,
        public readonly ?float $score = null,
        public readonly array $metadata = [],
    ) {}

    public static function ok(string $text, array $data = [], ?float $score = null): self
    {
        return new self(success: true, data: $data, text: $text, score: $score);
    }

    public static function fail(string $reason, array $metadata = []): self
    {
        return new self(success: false, text: $reason, metadata: $metadata);
    }
}
