<?php

namespace App\Services\PipelineTemplates;

use InvalidArgumentException;

/**
 * Fluent builder for pipeline template definitions.
 * Produces a validated definition array via build().
 */
class TemplateDefinitionBuilder
{
    private string $version = '1.0';

    /** @var array<int, array<string, mixed>> */
    private array $stages = [];

    /** @var array<int, array<string, mixed>> */
    private array $personas = [];

    /** @var array<string, mixed> */
    private array $settings = [];

    /** @var array<int, array<string, mixed>> */
    private array $variables = [];

    public function __construct(private readonly TemplateSchemaValidator $validator) {}

    public function version(string $version): static
    {
        $this->version = $version;

        return $this;
    }

    /**
     * Add a pipeline stage.
     *
     * @param  array<string, mixed>  $config
     */
    public function addStage(
        string $type,
        string $name,
        array $config = [],
        ?string $personaRef = null,
        ?string $provider = null,
        bool $enabled = true,
    ): static {
        $stage = ['type' => $type, 'name' => $name, 'config' => $config, 'enabled' => $enabled];
        if ($personaRef !== null) {
            $stage['persona_ref'] = $personaRef;
        }
        if ($provider !== null) {
            $stage['provider'] = $provider;
        }
        $this->stages[] = $stage;

        return $this;
    }

    /**
     * Add a persona.
     */
    public function addPersona(
        string $ref,
        string $name,
        string $systemPrompt,
        string $llmProvider,
        string $llmModel,
        string $voiceGuidelines = '',
    ): static {
        $persona = [
            'ref' => $ref,
            'name' => $name,
            'system_prompt' => $systemPrompt,
            'llm_provider' => $llmProvider,
            'llm_model' => $llmModel,
        ];
        if ($voiceGuidelines !== '') {
            $persona['voice_guidelines'] = $voiceGuidelines;
        }
        $this->personas[] = $persona;

        return $this;
    }

    /**
     * Add a variable (install-time user input).
     *
     * @param  array<string>  $options  Required for select/multiselect types.
     */
    public function addVariable(
        string $key,
        string $type,
        string $label,
        mixed $default = null,
        bool $required = false,
        array $options = [],
    ): static {
        $variable = ['key' => $key, 'type' => $type, 'label' => $label, 'required' => $required];
        if ($default !== null) {
            $variable['default'] = $default;
        }
        if ($options !== []) {
            $variable['options'] = $options;
        }
        $this->variables[] = $variable;

        return $this;
    }

    /**
     * Set (merge) pipeline settings.
     *
     * @param  array<string, mixed>  $settings
     */
    public function setSettings(array $settings): static
    {
        $this->settings = array_merge($this->settings, $settings);

        return $this;
    }

    /**
     * Build and validate the definition. Throws on invalid schema.
     *
     * @return array<string, mixed>
     *
     * @throws InvalidArgumentException
     */
    public function build(): array
    {
        $definition = [
            'version' => $this->version,
            'stages' => $this->stages,
            'personas' => $this->personas,
            'settings' => $this->settings,
        ];
        if ($this->variables !== []) {
            $definition['variables'] = $this->variables;
        }

        $result = $this->validator->validate($definition);

        if (! $result->isValid()) {
            throw new InvalidArgumentException(
                'Invalid template definition: '.implode('; ', $result->errors()),
            );
        }

        return $definition;
    }

    /**
     * Build without validation — returns definition and ValidationResult.
     *
     * @return array{definition: array<string, mixed>, result: ValidationResult}
     */
    public function buildWithValidation(): array
    {
        $definition = [
            'version' => $this->version,
            'stages' => $this->stages,
            'personas' => $this->personas,
            'settings' => $this->settings,
        ];
        if ($this->variables !== []) {
            $definition['variables'] = $this->variables;
        }

        return [
            'definition' => $definition,
            'result' => $this->validator->validate($definition),
        ];
    }
}
