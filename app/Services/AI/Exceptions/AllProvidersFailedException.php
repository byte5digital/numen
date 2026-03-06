<?php

namespace App\Services\AI\Exceptions;

use RuntimeException;

class AllProvidersFailedException extends RuntimeException
{
    public function __construct(
        public readonly array $attempts,  // [['provider' => ..., 'error' => ...], ...]
        ?\Throwable $previous = null,
    ) {
        $summary = collect($attempts)
            ->map(fn ($a) => "{$a['provider']}: {$a['error']}")
            ->implode('; ');

        parent::__construct(
            "All LLM providers failed. Attempts: [{$summary}]",
            503,
            $previous,
        );
    }
}
