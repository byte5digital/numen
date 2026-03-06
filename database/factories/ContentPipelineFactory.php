<?php

namespace Database\Factories;

use App\Models\ContentPipeline;
use App\Models\Space;
use Illuminate\Database\Eloquent\Factories\Factory;

class ContentPipelineFactory extends Factory
{
    protected $model = ContentPipeline::class;

    public function definition(): array
    {
        return [
            'space_id' => Space::factory(),
            'name' => $this->faker->words(3, true).' Pipeline',
            'is_active' => true,
            'stages' => [
                ['name' => 'generate', 'type' => 'ai_generate', 'persona_role' => 'creator'],
                ['name' => 'seo',      'type' => 'ai_transform', 'persona_role' => 'seo_expert'],
                ['name' => 'publish',  'type' => 'auto_publish'],
            ],
            'trigger_config' => [],
        ];
    }

    public function withHumanGate(): static
    {
        return $this->state([
            'stages' => [
                ['name' => 'generate', 'type' => 'ai_generate', 'persona_role' => 'creator'],
                ['name' => 'review',   'type' => 'human_gate'],
                ['name' => 'publish',  'type' => 'auto_publish'],
            ],
        ]);
    }
}
