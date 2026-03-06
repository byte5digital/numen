<?php

namespace App\Services\AI\Exceptions;

use RuntimeException;

class ProviderRateLimitException extends RuntimeException
{
    public function __construct(
        public readonly string $provider,
        public readonly string $model,
        public readonly int $retryAfterSeconds = 60,
        ?\Throwable $previous = null,
    ) {
        parent::__construct(
            "Provider '{$provider}' is rate limited for model '{$model}'. Retry after {$retryAfterSeconds}s.",
            429,
            $previous,
        );
    }
}
