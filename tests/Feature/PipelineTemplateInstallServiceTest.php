<?php

namespace Tests\Feature;

use App\Models\ContentPipeline;
use App\Models\Persona;
use App\Models\PipelineTemplate;
use App\Models\PipelineTemplateInstall;
use App\Models\PipelineTemplateVersion;
use App\Models\Space;
use App\Services\PipelineTemplates\PersonaResolver;
use App\Services\PipelineTemplates\PipelineTemplateInstallService;
use App\Services\PipelineTemplates\VariableResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PipelineTemplateInstallServiceTest extends TestCase
{
    use RefreshDatabase;

    private PipelineTemplateInstallService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new PipelineTemplateInstallService(
            new VariableResolver,
            new PersonaResolver,
        );
    }

    /** @return array<string, mixed> */
    private function minimalDefinition(): array
    {
        return [
            'version' => '1.0',
            'personas' => [
                [
                    'persona_ref' => 'writer',
                    'name' => 'Writer',
                    'role' => 'creator',
                    'system_prompt' => 'You are a writer.',
                    'capabilities' => ['content_generation'],
                    'model_config' => ['model' => 'claude-sonnet-4-6', 'temperature' => 0.7, 'max_tokens' => 4096],
                ],
            ],
            'stages' => [
                ['name' => 'draft', 'type' => 'ai_generate', 'persona_ref' => 'writer'],
                ['name' => 'publish', 'type' => 'auto_publish'],
            ],
            'settings' => ['name' => 'Test Pipeline'],
            'variables' => [],
        ];
    }

    private function makeVersion(Space $space, array $definition = []): PipelineTemplateVersion
    {
        $template = PipelineTemplate::factory()->create(['space_id' => $space->id]);

        return PipelineTemplateVersion::factory()->create([
            'template_id' => $template->id,
            'definition' => $definition ?: $this->minimalDefinition(),
            'is_latest' => true,
            'published_at' => now(),
        ]);
    }

    public function test_install_creates_pipeline_and_install_record(): void
    {
        $space = Space::factory()->create();
        $version = $this->makeVersion($space);

        $install = $this->service->install($version, $space);

        $this->assertInstanceOf(PipelineTemplateInstall::class, $install);
        $this->assertEquals($space->id, $install->space_id);
        $this->assertEquals($version->id, $install->version_id);
        $this->assertNotNull($install->pipeline_id);

        $pipeline = ContentPipeline::find($install->pipeline_id);
        $this->assertNotNull($pipeline);
        $this->assertEquals('Test Pipeline', $pipeline->name);
        $this->assertEquals($space->id, $pipeline->space_id);
    }

    public function test_install_creates_personas_from_definition(): void
    {
        $space = Space::factory()->create();
        $version = $this->makeVersion($space);

        $this->service->install($version, $space);

        $this->assertDatabaseHas('personas', [
            'space_id' => $space->id,
            'name' => 'Writer',
        ]);
    }

    public function test_install_reuses_existing_persona_by_name(): void
    {
        $space = Space::factory()->create();
        $existingPersona = Persona::factory()->create([
            'space_id' => $space->id,
            'name' => 'Writer',
        ]);

        $version = $this->makeVersion($space);
        $this->service->install($version, $space);

        $this->assertEquals(1, $space->personas()->where('name', 'Writer')->count());
        $this->assertEquals($existingPersona->id, $space->personas()->where('name', 'Writer')->first()->id);
    }

    public function test_install_injects_persona_id_into_stage(): void
    {
        $space = Space::factory()->create();
        $version = $this->makeVersion($space);

        $install = $this->service->install($version, $space);
        $pipeline = ContentPipeline::find($install->pipeline_id);

        $this->assertNotNull($pipeline);
        $stages = $pipeline->stages;
        $draftStage = collect($stages)->firstWhere('name', 'draft');

        $this->assertNotNull($draftStage);
        $this->assertArrayHasKey('persona_id', $draftStage);
    }

    public function test_install_stores_config_overrides(): void
    {
        $space = Space::factory()->create();
        $version = $this->makeVersion($space);
        $overrides = ['custom_setting' => 'foo'];

        $install = $this->service->install($version, $space, [], $overrides);

        $this->assertEquals($overrides, $install->config_overrides);
    }

    public function test_install_null_config_overrides_when_empty(): void
    {
        $space = Space::factory()->create();
        $version = $this->makeVersion($space);

        $install = $this->service->install($version, $space);

        $this->assertNull($install->config_overrides);
    }

    public function test_uninstall_soft_deletes_pipeline_and_removes_install(): void
    {
        $space = Space::factory()->create();
        $version = $this->makeVersion($space);
        $install = $this->service->install($version, $space);
        $pipelineId = $install->pipeline_id;

        $this->service->uninstall($install);

        $this->assertSoftDeleted('content_pipelines', ['id' => $pipelineId]);
        $this->assertDatabaseMissing('pipeline_template_installs', ['id' => $install->id]);
    }

    public function test_uninstall_handles_missing_pipeline_gracefully(): void
    {
        $space = Space::factory()->create();
        $template = PipelineTemplate::factory()->create(['space_id' => $space->id]);
        $version = PipelineTemplateVersion::factory()->create(['template_id' => $template->id]);

        $install = PipelineTemplateInstall::factory()->create([
            'template_id' => $template->id,
            'version_id' => $version->id,
            'space_id' => $space->id,
            'pipeline_id' => null,
        ]);

        $this->service->uninstall($install);

        $this->assertDatabaseMissing('pipeline_template_installs', ['id' => $install->id]);
    }

    public function test_update_creates_new_pipeline(): void
    {
        $space = Space::factory()->create();
        $template = PipelineTemplate::factory()->create(['space_id' => $space->id]);
        $v1 = PipelineTemplateVersion::factory()->create([
            'template_id' => $template->id,
            'definition' => $this->minimalDefinition(),
        ]);
        $v2 = PipelineTemplateVersion::factory()->create([
            'template_id' => $template->id,
            'definition' => array_merge($this->minimalDefinition(), ['settings' => ['name' => 'Updated Pipeline']]),
        ]);

        $install = $this->service->install($v1, $space);
        $newInstall = $this->service->update($install, $v2);

        $this->assertInstanceOf(PipelineTemplateInstall::class, $newInstall);
        $this->assertNotEquals($install->id, $newInstall->id);

        $newPipeline = ContentPipeline::find($newInstall->pipeline_id);
        $this->assertNotNull($newPipeline);
        $this->assertEquals('Updated Pipeline', $newPipeline->name);
    }

    public function test_update_preserves_config_overrides(): void
    {
        $space = Space::factory()->create();
        $template = PipelineTemplate::factory()->create(['space_id' => $space->id]);
        $v1 = PipelineTemplateVersion::factory()->create([
            'template_id' => $template->id,
            'definition' => $this->minimalDefinition(),
        ]);
        $v2 = PipelineTemplateVersion::factory()->create([
            'template_id' => $template->id,
            'definition' => $this->minimalDefinition(),
        ]);

        $overrides = ['custom' => 'preserved'];
        $install = $this->service->install($v1, $space, [], $overrides);
        $newInstall = $this->service->update($install, $v2);

        $this->assertEquals($overrides, $newInstall->config_overrides);
    }

    public function test_update_soft_deletes_old_pipeline(): void
    {
        $space = Space::factory()->create();
        $template = PipelineTemplate::factory()->create(['space_id' => $space->id]);
        $v1 = PipelineTemplateVersion::factory()->create([
            'template_id' => $template->id,
            'definition' => $this->minimalDefinition(),
        ]);
        $v2 = PipelineTemplateVersion::factory()->create([
            'template_id' => $template->id,
            'definition' => $this->minimalDefinition(),
        ]);

        $install = $this->service->install($v1, $space);
        $oldPipelineId = $install->pipeline_id;
        $this->service->update($install, $v2);

        $this->assertSoftDeleted('content_pipelines', ['id' => $oldPipelineId]);
    }
}
