<?php

namespace Tests\Unit\PipelineTemplates;

use App\Models\PipelineRun;
use App\Plugin\Contracts\PipelineStageContract;
use App\Plugin\HookRegistry;
use App\Services\PipelineTemplates\TemplateDefinitionBuilder;
use App\Services\PipelineTemplates\TemplateSchemaValidator;
use App\Services\PipelineTemplates\ValidationResult;
use Tests\TestCase;

class TemplateSchemaValidatorTest extends TestCase
{
    private TemplateSchemaValidator $validator;

    private HookRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registry = new HookRegistry;
        $this->validator = new TemplateSchemaValidator($this->registry);
    }

    /** @return array<string, mixed> */
    private function validDefinition(): array
    {
        return [
            'version' => '1.0',
            'stages' => [[
                'type' => 'ai_generate',
                'name' => 'Generate Article',
                'config' => ['prompt' => 'Write about {brand_name}'],
                'persona_ref' => 'writer',
                'enabled' => true,
            ]],
            'personas' => [[
                'ref' => 'writer',
                'name' => 'Writer Persona',
                'system_prompt' => 'You are a skilled writer.',
                'voice_guidelines' => 'Professional and clear.',
                'llm_provider' => 'anthropic',
                'llm_model' => 'claude-3-5-sonnet-20241022',
            ]],
            'settings' => ['auto_publish' => false, 'review_required' => true],
            'variables' => [[
                'key' => 'brand_name',
                'type' => 'string',
                'label' => 'Brand Name',
                'required' => true,
            ]],
        ];
    }

    public function test_valid_schema_passes(): void
    {
        $result = $this->validator->validate($this->validDefinition());
        $this->assertTrue($result->isValid());
        $this->assertEmpty($result->errors());
    }

    public function test_valid_schema_without_variables_passes(): void
    {
        $def = $this->validDefinition();
        unset($def['variables']);
        $result = $this->validator->validate($def);
        $this->assertTrue($result->isValid());
    }

    public function test_missing_version_fails(): void
    {
        $def = $this->validDefinition();
        unset($def['version']);
        $result = $this->validator->validate($def);
        $this->assertFalse($result->isValid());
        $this->assertStringContainsString('version', $result->errors()[0]);
    }

    public function test_unsupported_version_fails(): void
    {
        $def = $this->validDefinition();
        $def['version'] = '99.0';
        $result = $this->validator->validate($def);
        $this->assertFalse($result->isValid());
        $this->assertStringContainsString('Unsupported schema version', $result->errors()[0]);
    }

    public function test_missing_stages_fails(): void
    {
        $def = $this->validDefinition();
        unset($def['stages']);
        $result = $this->validator->validate($def);
        $this->assertFalse($result->isValid());
        $this->assertContains('Missing required field: stages', $result->errors());
    }

    public function test_empty_stages_fails(): void
    {
        $def = $this->validDefinition();
        $def['stages'] = [];
        $result = $this->validator->validate($def);
        $this->assertFalse($result->isValid());
    }

    public function test_missing_personas_fails(): void
    {
        $def = $this->validDefinition();
        unset($def['personas']);
        $result = $this->validator->validate($def);
        $this->assertFalse($result->isValid());
        $this->assertContains('Missing required field: personas', $result->errors());
    }

    public function test_missing_settings_fails(): void
    {
        $def = $this->validDefinition();
        unset($def['settings']);
        $result = $this->validator->validate($def);
        $this->assertFalse($result->isValid());
        $this->assertContains('Missing required field: settings', $result->errors());
    }

    public function test_invalid_stage_type_fails(): void
    {
        $def = $this->validDefinition();
        $def['stages'][0]['type'] = 'does_not_exist';
        $result = $this->validator->validate($def);
        $this->assertFalse($result->isValid());
        $this->assertStringContainsString('Unknown stage type', $result->errors()[0]);
    }

    public function test_missing_stage_name_fails(): void
    {
        $def = $this->validDefinition();
        unset($def['stages'][0]['name']);
        $result = $this->validator->validate($def);
        $this->assertFalse($result->isValid());
        $this->assertStringContainsString('"name"', $result->errors()[0]);
    }

    public function test_missing_stage_config_fails(): void
    {
        $def = $this->validDefinition();
        unset($def['stages'][0]['config']);
        $result = $this->validator->validate($def);
        $this->assertFalse($result->isValid());
        $this->assertStringContainsString('"config"', $result->errors()[0]);
    }

    public function test_custom_stage_type_via_hook_passes(): void
    {
        $mockHandler = new class implements PipelineStageContract
        {
            public static function type(): string
            {
                return 'custom_plugin_stage';
            }

            public static function label(): string
            {
                return 'Custom Stage';
            }

            public static function configSchema(): array
            {
                return [];
            }

            public function handle(PipelineRun $run, array $stageConfig): array
            {
                return ['success' => true];
            }
        };
        $this->registry->registerPipelineStageClass('custom_plugin_stage', $mockHandler::class);
        $def = $this->validDefinition();
        $def['stages'][0]['type'] = 'custom_plugin_stage';
        $result = $this->validator->validate($def);
        $this->assertTrue($result->isValid(), implode('; ', $result->errors()));
    }

    public function test_missing_persona_field_fails(): void
    {
        $def = $this->validDefinition();
        unset($def['personas'][0]['llm_model']);
        $result = $this->validator->validate($def);
        $this->assertFalse($result->isValid());
        $this->assertStringContainsString('llm_model', $result->errors()[0]);
    }

    public function test_duplicate_persona_ref_fails(): void
    {
        $def = $this->validDefinition();
        $def['personas'][] = $def['personas'][0];
        $result = $this->validator->validate($def);
        $this->assertFalse($result->isValid());
        $this->assertStringContainsString('Duplicate persona ref', $result->errors()[0]);
    }

    public function test_stage_with_unknown_persona_ref_fails(): void
    {
        $def = $this->validDefinition();
        $def['stages'][0]['persona_ref'] = 'nonexistent_persona';
        $result = $this->validator->validate($def);
        $this->assertFalse($result->isValid());
        $this->assertStringContainsString('does not reference any defined persona', $result->errors()[0]);
    }

    public function test_invalid_variable_type_fails(): void
    {
        $def = $this->validDefinition();
        $def['variables'][0]['type'] = 'blob';
        $result = $this->validator->validate($def);
        $this->assertFalse($result->isValid());
        $this->assertStringContainsString('Invalid variable type', $result->errors()[0]);
    }

    public function test_select_variable_without_options_fails(): void
    {
        $def = $this->validDefinition();
        $def['variables'][] = [
            'key' => 'my_select', 'type' => 'select',
            'label' => 'Pick one', 'required' => false,
        ];
        $result = $this->validator->validate($def);
        $this->assertFalse($result->isValid());
        $this->assertStringContainsString('"options"', $result->errors()[0]);
    }

    public function test_select_variable_with_options_passes(): void
    {
        $def = $this->validDefinition();
        $def['variables'][] = [
            'key' => 'my_select', 'type' => 'select', 'label' => 'Pick one',
            'required' => false, 'options' => ['a', 'b', 'c'],
        ];
        $result = $this->validator->validate($def);
        $this->assertTrue($result->isValid(), implode('; ', $result->errors()));
    }

    public function test_duplicate_variable_key_fails(): void
    {
        $def = $this->validDefinition();
        $def['variables'][] = $def['variables'][0];
        $result = $this->validator->validate($def);
        $this->assertFalse($result->isValid());
        $this->assertStringContainsString('Duplicate variable key', $result->errors()[0]);
    }

    public function test_invalid_variable_key_identifier_fails(): void
    {
        $def = $this->validDefinition();
        $def['variables'][0]['key'] = '1invalid';
        $result = $this->validator->validate($def);
        $this->assertFalse($result->isValid());
        $this->assertStringContainsString('valid identifier', $result->errors()[0]);
    }

    public function test_non_bool_auto_publish_fails(): void
    {
        $def = $this->validDefinition();
        $def['settings']['auto_publish'] = 'yes';
        $result = $this->validator->validate($def);
        $this->assertFalse($result->isValid());
        $this->assertStringContainsString('auto_publish', $result->errors()[0]);
    }

    public function test_validation_result_valid_factory(): void
    {
        $result = ValidationResult::valid(['a warning']);
        $this->assertTrue($result->isValid());
        $this->assertEmpty($result->errors());
        $this->assertEquals(['a warning'], $result->warnings());
    }

    public function test_validation_result_invalid_factory(): void
    {
        $result = ValidationResult::invalid(['error one', 'error two']);
        $this->assertFalse($result->isValid());
        $this->assertCount(2, $result->errors());
    }

    public function test_builder_produces_valid_output(): void
    {
        $builder = new TemplateDefinitionBuilder($this->validator);
        $def = $builder
            ->addPersona('writer', 'Writer', 'You write content.', 'anthropic', 'claude-3-5-sonnet-20241022')
            ->addStage('ai_generate', 'Generate', ['prompt' => 'Write about {brand}'], 'writer')
            ->addVariable('brand', 'string', 'Brand Name', null, true)
            ->setSettings(['auto_publish' => false, 'review_required' => true])
            ->build();
        $this->assertEquals('1.0', $def['version']);
        $this->assertCount(1, $def['stages']);
        $this->assertCount(1, $def['personas']);
        $this->assertArrayHasKey('brand', array_column($def['variables'], 'key', 'key'));
    }

    public function test_builder_throws_on_invalid_definition(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $builder = new TemplateDefinitionBuilder($this->validator);
        $builder->build();
    }

    public function test_builder_with_validation_returns_result(): void
    {
        $builder = new TemplateDefinitionBuilder($this->validator);
        ['definition' => $def, 'result' => $result] = $builder->buildWithValidation();
        $this->assertInstanceOf(ValidationResult::class, $result);
        $this->assertArrayHasKey('version', $def);
    }
}
