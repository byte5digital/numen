<?php

namespace App\Services\PipelineTemplates;

class TemplateSchemaValidator
{
    /** @param array<string, mixed> $definition */
    public function validate(array $definition): ValidationResult
    {
        $errors = [];

        if (empty($definition['stages']) || ! is_array($definition['stages'])) {
            $errors[] = 'Pipeline definition must have a stages array.';
        }

        return new ValidationResult($errors);
    }
}
