<?php

namespace App\Services\PipelineTemplates;

final class ValidationResult
{
    /**
     * @param  array<string>  $errors
     * @param  array<string>  $warnings
     */
    public function __construct(
        private readonly array $errors = [],
        private readonly array $warnings = [],
    ) {}

    /** @param  array<string>  $warnings */
    public static function valid(array $warnings = []): self
    {
        return new self([], $warnings);
    }

    /**
     * @param  array<string>  $errors
     * @param  array<string>  $warnings
     */
    public static function invalid(array $errors, array $warnings = []): self
    {
        return new self($errors, $warnings);
    }

    public function isValid(): bool
    {
        return empty($this->errors);
    }

    /** @return array<string> */
    public function errors(): array
    {
        return $this->errors;
    }

    /** @return array<string> */
    public function warnings(): array
    {
        return $this->warnings;
    }
}
