<?php

namespace Database\Factories;

use App\Models\Space;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class SpaceFactory extends Factory
{
    protected $model = Space::class;

    public function definition(): array
    {
        $name = $this->faker->unique()->words(2, true);
        return [
            'name'       => ucwords($name),
            'slug'       => Str::slug($name),
            'settings'   => [],
            'api_config' => [],
        ];
    }
}
