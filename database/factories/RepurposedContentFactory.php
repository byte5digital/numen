<?php

namespace Database\Factories;

use App\Models\Content;
use App\Models\Space;
use App\Models\RepurposedContent;
use Illuminate\Database\Eloquent\Factories\Factory;

class RepurposedContentFactory extends Factory
{
    protected $model = RepurposedContent::class;

    public function definition(): array
    {
        return [
            'space_id' => Space::factory(),
            'source_content_id' => Content::factory(),
            'format_key' => $this->faker->word(),
            'status' => 'pending',
            'output' => null,
            'output_parts' => null,
            'error_message' => null,
        ];
    }

    public function pending(): static
    {
        return $this->state([
            'status' => 'pending',
            'output' => null,
        ]);
    }

    public function processing(): static
    {
        return $this->state([
            'status' => 'processing',
        ]);
    }

    public function completed(): static
    {
        return $this->state([
            'status' => 'completed',
            'output' => $this->faker->paragraph(),
        ]);
    }

    public function failed(): static
    {
        return $this->state([
            'status' => 'failed',
            'error_message' => $this->faker->sentence(),
        ]);
    }
}
