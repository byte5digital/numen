<?php

namespace App\Services\PipelineTemplates;

use App\Models\ContentPipeline;
use App\Models\PipelineTemplateInstall;
use App\Models\PipelineTemplateVersion;
use App\Models\Space;
use Illuminate\Support\Facades\DB;

class PipelineTemplateInstallService
{
    public function __construct(
        private readonly VariableResolver $variableResolver,
        private readonly PersonaResolver $personaResolver,
    ) {}

    /**
     * @param  array<string, mixed>  $variableValues
     * @param  array<string, mixed>  $configOverrides
     */
    public function install(
        PipelineTemplateVersion $version,
        Space $space,
        array $variableValues = [],
        array $configOverrides = [],
    ): PipelineTemplateInstall {
        return DB::transaction(function () use ($version, $space, $variableValues, $configOverrides): PipelineTemplateInstall {
            $definition = $this->variableResolver->resolve($version->definition, $variableValues);
            $personaDefs = $definition['personas'] ?? [];
            $personas = $this->personaResolver->resolvePersonas($personaDefs, $space);
            $stages = $this->buildStages($definition['stages'] ?? [], $personas);
            $pipelineName = $definition['settings']['name']
                ?? $version->template->name
                ?? 'Imported Pipeline';

            $pipeline = ContentPipeline::create([
                'space_id' => $space->id,
                'name' => $pipelineName,
                'stages' => $stages,
                'trigger_config' => $definition['settings']['trigger_config'] ?? [],
                'is_active' => true,
            ]);

            return PipelineTemplateInstall::create([
                'template_id' => $version->template_id,
                'version_id' => $version->id,
                'space_id' => $space->id,
                'pipeline_id' => $pipeline->id,
                'installed_at' => now(),
                'config_overrides' => empty($configOverrides) ? null : $configOverrides,
            ]);
        });
    }

    public function uninstall(PipelineTemplateInstall $install): void
    {
        DB::transaction(function () use ($install): void {
            if ($install->pipeline_id !== null) {
                /** @var ContentPipeline|null $pipeline */
                $pipeline = ContentPipeline::find($install->pipeline_id);
                $pipeline?->delete();
            }
            $install->delete();
        });
    }

    public function update(
        PipelineTemplateInstall $install,
        PipelineTemplateVersion $newVersion,
    ): PipelineTemplateInstall {
        return DB::transaction(function () use ($install, $newVersion): PipelineTemplateInstall {
            $configOverrides = $install->config_overrides ?? [];

            if ($install->pipeline_id !== null) {
                /** @var ContentPipeline|null $pipeline */
                $pipeline = ContentPipeline::find($install->pipeline_id);
                $pipeline?->delete();
            }

            $newInstall = $this->install($newVersion, $install->space, [], $configOverrides);
            $install->delete();

            return $newInstall;
        });
    }

    /**
     * @param  array<int, array<string, mixed>>  $stages
     * @param  array<string, \App\Models\Persona>  $personas
     * @return array<int, array<string, mixed>>
     */
    private function buildStages(array $stages, array $personas): array
    {
        return array_map(function (array $stage) use ($personas): array {
            $ref = $stage['persona_ref'] ?? null;
            if ($ref !== null && isset($personas[$ref])) {
                $stage['persona_id'] = $personas[$ref]->id;
            }

            return $stage;
        }, $stages);
    }
}
