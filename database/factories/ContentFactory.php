<?php

namespace Database\Factories;

use App\Models\Content;
use App\Models\ContentType;
use App\Models\Space;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ContentFactory extends Factory
{
    protected $model = Content::class;

    public function definition(): array
    {
        $title = $this->faker->unique()->sentence(4);

        return [
            'space_id' => Space::factory(),
            'content_type_id' => ContentType::factory(),
            'slug' => Str::slug($title),
            'status' => 'draft',
            'locale' => 'en',
            'published_at' => null,
        ];
    }

    public function published(): static
    {
        return $this->state([
            'status' => 'published',
            'published_at' => now(),
        ]);
    }
}
