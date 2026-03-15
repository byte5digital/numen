<?php

namespace Database\Factories;

use App\Models\FormatTemplate;
use App\Models\Space;
use Illuminate\Database\Eloquent\Factories\Factory;

class FormatTemplateFactory extends Factory
{
    protected $model = FormatTemplate::class;

    public function definition(): array
    {
        return [
            'space_id' => null,
            'format_key' => $this->faker->word(),
            'system_prompt' => $this->faker->sentence(),
            'user_prompt_template' => 'Convert to format: {{body}}',
        ];
    }

    public function forSpace(?Space $space = null): static
    {
        return $this->state(function (array $attributes) use ($space) {
            return [
                'space_id' => $space?->id,
            ];
        });
    }
}
