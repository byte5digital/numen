<?php

namespace App\Services\AI\Exceptions;

use RuntimeException;

class CostLimitExceededException extends RuntimeException
{
    public function __construct(
        string $message = 'AI cost limit exceeded. Generation blocked.',
        public readonly float $currentSpend = 0,
        public readonly float $limit = 0,
        public readonly string $period = 'daily',
    ) {
        parent::__construct($message);
    }
}
