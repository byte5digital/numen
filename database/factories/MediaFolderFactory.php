<?php

namespace Database\Factories;

use App\Models\MediaFolder;
use App\Models\Space;
use Illuminate\Database\Eloquent\Factories\Factory;

class MediaFolderFactory extends Factory
{
    protected $model = MediaFolder::class;

    public function definition(): array
    {
        return [
            'space_id' => Space::factory(),
            'name' => $this->faker->word(),
            'slug' => $this->faker->slug(),
            'parent_id' => null,
            'description' => $this->faker->text(100),
            'sort_order' => 0,
        ];
    }
}