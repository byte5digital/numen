<?php

namespace Database\Factories;

use App\Models\ContentBrief;
use App\Models\Space;
use Illuminate\Database\Eloquent\Factories\Factory;

class ContentBriefFactory extends Factory
{
    protected $model = ContentBrief::class;

    public function definition(): array
    {
        return [
            'space_id' => Space::factory(),
            'title' => $this->faker->sentence(6),
            'description' => $this->faker->paragraph(),
            'content_type_slug' => 'blog_post',
            'target_locale' => 'en',
            'source' => 'manual',
            'status' => 'pending',
            'priority' => 'normal',
            'target_keywords' => $this->faker->words(3),
            'requirements' => [],
            'reference_urls' => [],
        ];
    }

    public function processing(): static
    {
        return $this->state(['status' => 'processing']);
    }

    public function completed(): static
    {
        return $this->state(['status' => 'completed']);
    }
}
