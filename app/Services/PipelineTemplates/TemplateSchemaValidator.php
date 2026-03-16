<?php

namespace App\Services\PipelineTemplates;

use App\Plugin\HookRegistry;

/** Validates pipeline template definitions against the Numen schema v1. */
class TemplateSchemaValidator
{
    private const CORE_STAGE_TYPES = [
        'ai_generate', 'ai_transform', 'ai_review',
        'ai_illustrate', 'auto_publish', 'human_gate',
    ];

    private const VARIABLE_TYPES = [
        'string', 'text', 'number', 'boolean',
        'select', 'multiselect', 'url', 'email', 'color',
    ];

    private const SUPPORTED_VERSIONS = ['1.0'];

    public function __construct(private readonly HookRegistry $hookRegistry) {}

    /** @param array<string, mixed> $definition */
    public function validate(array $definition): ValidationResult
    {
        $errors = [];
        $warnings = [];
        // Accept both 'version' and 'schema_version' field names
        $schemaVersion = $definition['version'] ?? $definition['schema_version'] ?? null;
        if ($schemaVersion === null) {
            $errors[] = 'Missing required field: version';
        } elseif (! in_array($schemaVersion, self::SUPPORTED_VERSIONS, true)) {
            $errors[] = "Unsupported schema version: \"{$schemaVersion}\"";
        }
        if (! isset($definition['stages'])) {
            $errors[] = 'Missing required field: stages';
        } elseif (! is_array($definition['stages'])) {
            $errors[] = 'Field "stages" must be an array';
        } elseif (empty($definition['stages'])) {
            $errors[] = 'Field "stages" must contain at least one stage';
        } else {
            $errors = array_merge($errors, $this->validateStages($definition['stages']));
        }
        if (isset($definition['personas'])) {
            if (! is_array($definition['personas'])) {
                $errors[] = 'Field "personas" must be an array';
            } else {
                [$personaErrors, $personaWarnings] = $this->validatePersonas($definition['personas']);
                $errors = array_merge($errors, $personaErrors);
                $warnings = array_merge($warnings, $personaWarnings);
            }
        }
        if (isset($definition['settings'])) {
            if (! is_array($definition['settings'])) {
                $errors[] = 'Field "settings" must be an object/array';
            } else {
                $errors = array_merge($errors, $this->validateSettings($definition['settings']));
            }
        }
        if (isset($definition['variables'])) {
            if (! is_array($definition['variables'])) {
                $errors[] = 'Field "variables" must be an array';
            } else {
                $errors = array_merge($errors, $this->validateVariables($definition['variables']));
            }
        }
        if (empty($errors) && isset($definition['personas'])) {
            $refs = $this->collectPersonaRefs($definition['personas']);
            $errors = array_merge($errors, $this->validatePersonaRefs($definition['stages'] ?? [], $refs));
        }

        return empty($errors) ? ValidationResult::valid($warnings) : ValidationResult::invalid($errors, $warnings);
    }

    /**
     * @param  array<int, mixed>  $stages
     * @return array<string>
     */
    private function validateStages(array $stages): array
    {
        $errors = [];
        $allowed = $this->getAllowedStageTypes();
        foreach ($stages as $i => $stage) {
            $p = "stages[$i]";
            if (! is_array($stage)) {
                $errors[] = "{$p}: Each stage must be an object";

                continue;
            }
            if (! isset($stage['type']) || ! is_string($stage['type']) || $stage['type'] === '') {
                $errors[] = "{$p}: Missing required field \"type\"";
            } elseif (! in_array($stage['type'], $allowed, true)) {
                $errors[] = "{$p}: Unknown stage type \"{$stage['type']}\". Allowed: ".implode(', ', $allowed);
            }
            if (! isset($stage['name']) || ! is_string($stage['name']) || $stage['name'] === '') {
                $errors[] = "{$p}: Missing required field \"name\"";
            }
            if (isset($stage['config']) && ! is_array($stage['config'])) {
                $errors[] = "{$p}: Field \"config\" must be an object/array";
            }
            if (isset($stage['persona_ref']) && ! is_string($stage['persona_ref'])) {
                $errors[] = "{$p}: Field \"persona_ref\" must be a string";
            }
            if (isset($stage['provider']) && ! is_string($stage['provider'])) {
                $errors[] = "{$p}: Field \"provider\" must be a string";
            }
            if (isset($stage['enabled']) && ! is_bool($stage['enabled'])) {
                $errors[] = "{$p}: Field \"enabled\" must be a boolean";
            }
        }

        return $errors;
    }

    /**
     * @param  array<int, mixed>  $personas
     * @return array{0: array<string>, 1: array<string>}
     */
    private function validatePersonas(array $personas): array
    {
        $errors = [];
        $warnings = [];
        $refs = [];
        foreach ($personas as $i => $persona) {
            $p = "personas[$i]";
            if (! is_array($persona)) {
                $errors[] = "{$p}: Each persona must be an object";

                continue;
            }
            foreach (['ref', 'name', 'system_prompt', 'llm_provider', 'llm_model'] as $field) {
                if (! isset($persona[$field]) || ! is_string($persona[$field]) || $persona[$field] === '') {
                    $errors[] = "{$p}: Missing required field \"{$field}\"";
                }
            }
            if (isset($persona['voice_guidelines']) && ! is_string($persona['voice_guidelines'])) {
                $errors[] = "{$p}: Field \"voice_guidelines\" must be a string";
            }
            if (isset($persona['ref'])) {
                if (in_array($persona['ref'], $refs, true)) {
                    $errors[] = "{$p}: Duplicate persona ref \"{$persona['ref']}\"";
                } else {
                    $refs[] = $persona['ref'];
                }
            }
        }

        return [$errors, $warnings];
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<string>
     */
    private function validateSettings(array $settings): array
    {
        $errors = [];
        if (isset($settings['auto_publish']) && ! is_bool($settings['auto_publish'])) {
            $errors[] = 'settings.auto_publish must be a boolean';
        }
        if (isset($settings['review_required']) && ! is_bool($settings['review_required'])) {
            $errors[] = 'settings.review_required must be a boolean';
        }
        if (isset($settings['max_retries']) && ! is_int($settings['max_retries'])) {
            $errors[] = 'settings.max_retries must be an integer';
        }
        if (isset($settings['timeout_seconds']) && ! is_int($settings['timeout_seconds'])) {
            $errors[] = 'settings.timeout_seconds must be an integer';
        }

        return $errors;
    }

    /**
     * @param  array<int, mixed>  $variables
     * @return array<string>
     */
    private function validateVariables(array $variables): array
    {
        $errors = [];
        $keys = [];
        foreach ($variables as $i => $variable) {
            $p = "variables[$i]";
            if (! is_array($variable)) {
                $errors[] = "{$p}: Each variable must be an object";

                continue;
            }
            if (! isset($variable['key']) || ! is_string($variable['key']) || $variable['key'] === '') {
                $errors[] = "{$p}: Missing required field \"key\"";
            } else {
                if (! preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $variable['key'])) {
                    $errors[] = "{$p}: Field \"key\" must be a valid identifier";
                }
                if (in_array($variable['key'], $keys, true)) {
                    $errors[] = "{$p}: Duplicate variable key \"{$variable['key']}\"";
                } else {
                    $keys[] = $variable['key'];
                }
            }
            if (! isset($variable['type']) || ! is_string($variable['type'])) {
                $errors[] = "{$p}: Missing required field \"type\"";
            } elseif (! in_array($variable['type'], self::VARIABLE_TYPES, true)) {
                $errors[] = "{$p}: Invalid variable type \"{$variable['type']}\"";
            }
            if (! isset($variable['label']) || ! is_string($variable['label']) || $variable['label'] === '') {
                $errors[] = "{$p}: Missing required field \"label\"";
            }
            if (isset($variable['required']) && ! is_bool($variable['required'])) {
                $errors[] = "{$p}: Field \"required\" must be a boolean";
            }
            if (isset($variable['type']) && in_array($variable['type'], ['select', 'multiselect'], true)) {
                if (! isset($variable['options']) || ! is_array($variable['options']) || empty($variable['options'])) {
                    $errors[] = "{$p}: Variable of type \"{$variable['type']}\" must include a non-empty \"options\" array";
                }
            }
        }

        return $errors;
    }

    /**
     * @param  array<int, mixed>  $stages
     * @param  array<string>  $personaRefs
     * @return array<string>
     */
    private function validatePersonaRefs(array $stages, array $personaRefs): array
    {
        $errors = [];
        foreach ($stages as $i => $stage) {
            if (! is_array($stage)) {
                continue;
            }
            if (isset($stage['persona_ref']) && is_string($stage['persona_ref'])) {
                if (! in_array($stage['persona_ref'], $personaRefs, true)) {
                    $errors[] = "stages[$i]: persona_ref \"{$stage['persona_ref']}\" does not reference any defined persona";
                }
            }
        }

        return $errors;
    }

    /**
     * @param  array<int, mixed>  $personas
     * @return array<string>
     */
    private function collectPersonaRefs(array $personas): array
    {
        $refs = [];
        foreach ($personas as $persona) {
            if (is_array($persona) && isset($persona['ref']) && is_string($persona['ref'])) {
                $refs[] = $persona['ref'];
            }
        }

        return $refs;
    }

    /** @return array<string> */
    private function getAllowedStageTypes(): array
    {
        return array_unique(array_merge(
            self::CORE_STAGE_TYPES,
            $this->hookRegistry->getRegisteredPipelineStageTypes(),
        ));
    }
}
