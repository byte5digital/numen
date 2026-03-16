<?php

namespace Tests\Unit\PipelineTemplates;

use App\Services\PipelineTemplates\VariableResolver;
use InvalidArgumentException;
use Tests\TestCase;

class VariableResolverTest extends TestCase
{
    private VariableResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new VariableResolver;
    }

    public function test_replaces_string_placeholders(): void
    {
        $definition = [
            'variables' => [
                ['name' => 'topic', 'type' => 'string', 'required' => true],
            ],
            'stages' => [
                ['name' => 'draft', 'config' => ['prompt' => 'Write about {{topic}}.']],
            ],
        ];

        $result = $this->resolver->resolve($definition, ['topic' => 'AI']);

        $this->assertEquals('Write about AI.', $result['stages'][0]['config']['prompt']);
    }

    public function test_coerces_number_type(): void
    {
        $definition = [
            'variables' => [
                ['name' => 'max_tokens', 'type' => 'number', 'required' => true],
            ],
            'stages' => [],
        ];

        $result = $this->resolver->resolve($definition, ['max_tokens' => '512']);

        $this->assertIsNumeric(512);
    }

    public function test_coerces_boolean_type(): void
    {
        $definition = [
            'variables' => [
                ['name' => 'auto_publish', 'type' => 'boolean', 'required' => true],
            ],
            'stages' => [],
        ];

        $result = $this->resolver->resolve($definition, ['auto_publish' => 'true']);

        $this->assertNotNull($result);
    }

    public function test_throws_on_missing_required_variable(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Missing required template variables.*topic/');

        $definition = [
            'variables' => [
                ['name' => 'topic', 'type' => 'string', 'required' => true],
            ],
            'stages' => [],
        ];

        $this->resolver->resolve($definition, []);
    }

    public function test_uses_default_value_when_not_provided(): void
    {
        $definition = [
            'variables' => [
                ['name' => 'tone', 'type' => 'string', 'required' => false, 'default' => 'professional'],
            ],
            'stages' => [
                ['name' => 'draft', 'config' => ['tone' => '{{tone}}']],
            ],
        ];

        $result = $this->resolver->resolve($definition, []);

        $this->assertEquals('professional', $result['stages'][0]['config']['tone']);
    }

    public function test_leaves_unknown_placeholder_intact(): void
    {
        $definition = [
            'variables' => [],
            'stages' => [
                ['name' => 'draft', 'config' => ['prompt' => 'Hello {{unknown}}.']],
            ],
        ];

        $result = $this->resolver->resolve($definition, []);

        $this->assertEquals('Hello {{unknown}}.', $result['stages'][0]['config']['prompt']);
    }

    public function test_resolves_nested_arrays(): void
    {
        $definition = [
            'variables' => [
                ['name' => 'brand', 'type' => 'string', 'required' => true],
            ],
            'stages' => [
                ['name' => 'draft', 'meta' => ['title' => '{{brand}} Blog Post']],
            ],
        ];

        $result = $this->resolver->resolve($definition, ['brand' => 'Numen']);

        $this->assertEquals('Numen Blog Post', $result['stages'][0]['meta']['title']);
    }

    public function test_optional_variable_without_default_is_not_required(): void
    {
        $definition = [
            'variables' => [
                ['name' => 'optional_tag', 'type' => 'string', 'required' => false],
            ],
            'stages' => [],
        ];

        $result = $this->resolver->resolve($definition, []);
        $this->assertIsArray($result);
    }
}
