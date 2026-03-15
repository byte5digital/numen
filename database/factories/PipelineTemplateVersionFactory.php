<?php

namespace Database\Factories;

use App\Models\PipelineTemplate;
use App\Models\PipelineTemplateVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

class PipelineTemplateVersionFactory extends Factory
{
    protected $model = PipelineTemplateVersion::class;

    public function definition(): array
    {
        return [
            'template_id' => PipelineTemplate::factory(),
            'version' => $this->faker->semver(),
            'definition' => [
                'stages' => [
                    ['name' => 'generate', 'type' => 'ai_generate', 'persona_role' => 'creator'],
                    ['name' => 'review',   'type' => 'human_gate'],
                    ['name' => 'publish',  'type' => 'auto_publish'],
                ],
                'trigger' => 'manual',
            ],
            'changelog' => $this->faker->sentence(),
            'is_latest' => false,
            'published_at' => null,
        ];
    }

    public function latest(): static
    {
        return $this->state([
            'is_latest' => true,
            'published_at' => now(),
        ]);
    }

    public function published(): static
    {
        return $this->state(['published_at' => now()]);
    }
}
