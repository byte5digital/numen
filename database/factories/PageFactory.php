<?php

namespace Database\Factories;

use App\Models\Page;
use App\Models\Space;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class PageFactory extends Factory
{
    protected $model = Page::class;

    public function definition(): array
    {
        $title = $this->faker->unique()->words(3, true);

        return [
            'space_id' => Space::factory(),
            'slug' => Str::slug($title),
            'title' => ucwords($title),
            'status' => 'draft',
            'meta' => [],
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
