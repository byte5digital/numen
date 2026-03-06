<?php

namespace App\Services\AI\Exceptions;

use RuntimeException;

class ProviderUnavailableException extends RuntimeException
{
    public function __construct(
        public readonly string $provider,
        public readonly string $model,
        string $reason = 'Service unavailable',
        ?\Throwable $previous = null,
    ) {
        parent::__construct(
            "Provider '{$provider}' unavailable for model '{$model}': {$reason}",
            503,
            $previous,
        );
    }
}
