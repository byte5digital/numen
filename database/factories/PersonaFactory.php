<?php

namespace Database\Factories;

use App\Models\Persona;
use App\Models\Space;
use Illuminate\Database\Eloquent\Factories\Factory;

class PersonaFactory extends Factory
{
    protected $model = Persona::class;

    public function definition(): array
    {
        return [
            'space_id'      => Space::factory(),
            'name'          => $this->faker->name(),
            'role'          => $this->faker->randomElement(['creator', 'seo_expert', 'editor']),
            'system_prompt' => 'You are a helpful AI assistant.',
            'capabilities'  => ['content_generation'],
            'model_config'  => [
                'model'       => 'claude-sonnet-4-6',
                'temperature' => 0.7,
                'max_tokens'  => 4096,
            ],
            'is_active' => true,
        ];
    }
}
