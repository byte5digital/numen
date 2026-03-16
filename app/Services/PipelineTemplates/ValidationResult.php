<?php

namespace App\Services\PipelineTemplates;

class ValidationResult
{
    /** @param string[] $errors */
    public function __construct(private readonly array $errors = []) {}

    public function isValid(): bool
    {
        return empty($this->errors);
    }

    /** @return string[] */
    public function errors(): array
    {
        return $this->errors;
    }
}
