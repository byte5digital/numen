<?php

namespace App\Services\PipelineTemplates;

use InvalidArgumentException;

class VariableResolver
{
    /**
     * @param  array<string, mixed>  $definition
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    public function resolve(array $definition, array $values): array
    {
        $variables = $definition['variables'] ?? [];
        $this->validateRequiredVariables($variables, $values);
        $coercedValues = $this->coerceValues($variables, $values);

        return $this->replacePlaceholders($definition, $coercedValues);
    }

    /**
     * @param  array<int, array<string, mixed>>  $variables
     * @param  array<string, mixed>  $values
     */
    private function validateRequiredVariables(array $variables, array $values): void
    {
        $missing = [];
        foreach ($variables as $variable) {
            $name = $variable['name'] ?? '';
            $required = $variable['required'] ?? true;
            if ($required && ! array_key_exists($name, $values)) {
                $missing[] = $name;
            }
        }
        if (! empty($missing)) {
            throw new InvalidArgumentException(
                'Missing required template variables: '.implode(', ', $missing),
            );
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $variables
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function coerceValues(array $variables, array $values): array
    {
        $result = $values;
        foreach ($variables as $variable) {
            $name = $variable['name'] ?? '';
            $type = $variable['type'] ?? 'string';
            if (! array_key_exists($name, $values)) {
                if (array_key_exists('default', $variable)) {
                    $result[$name] = $this->coerce($variable['default'], $type);
                }

                continue;
            }
            $result[$name] = $this->coerce($values[$name], $type);
        }

        return $result;
    }

    private function coerce(mixed $value, string $type): mixed
    {
        return match ($type) {
            'number' => is_numeric($value) ? $value + 0 : (float) $value,
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? (bool) $value,
            'select' => (string) $value,
            'multiselect' => is_array($value) ? $value : [(string) $value],
            default => (string) $value,
        };
    }

    /**
     * @param  array<string, mixed>  $definition
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function replacePlaceholders(array $definition, array $values): array
    {
        array_walk_recursive($definition, function (mixed &$item) use ($values): void {
            if (! is_string($item)) {
                return;
            }
            $item = preg_replace_callback(
                '/\{\{([a-zA-Z_][a-zA-Z0-9_]*)\}\}/',
                function (array $matches) use ($values): string {
                    $key = $matches[1];
                    if (! array_key_exists($key, $values)) {
                        return $matches[0];
                    }
                    $val = $values[$key];

                    return is_array($val) ? implode(', ', $val) : (string) $val;
                },
                $item,
            ) ?? $item;
        });

        return $definition;
    }
}
