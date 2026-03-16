<?php

namespace Database\Factories;

use App\Models\PipelineTemplate;
use App\Models\PipelineTemplateInstall;
use App\Models\PipelineTemplateVersion;
use App\Models\Space;
use Illuminate\Database\Eloquent\Factories\Factory;

class PipelineTemplateInstallFactory extends Factory
{
    protected $model = PipelineTemplateInstall::class;

    public function definition(): array
    {
        return [
            'template_id' => PipelineTemplate::factory(),
            'version_id' => PipelineTemplateVersion::factory(),
            'space_id' => Space::factory(),
            'pipeline_id' => null,
            'installed_at' => now(),
            'config_overrides' => null,
        ];
    }

    public function withConfigOverrides(array $overrides = []): static
    {
        return $this->state(['config_overrides' => $overrides ?: ['persona_id' => 'custom']]);
    }
}
