<?php

namespace Database\Factories;

use App\Models\Space;
use App\Models\Vocabulary;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class VocabularyFactory extends Factory
{
    protected $model = Vocabulary::class;

    public function definition(): array
    {
        $name = $this->faker->unique()->words(2, true);

        return [
            'space_id' => Space::factory(),
            'name' => ucwords($name),
            'slug' => Str::slug($name),
            'description' => $this->faker->optional()->sentence(),
            'hierarchy' => true,
            'allow_multiple' => true,
            'settings' => null,
            'sort_order' => 0,
        ];
    }

    public function flat(): static
    {
        return $this->state(['hierarchy' => false]);
    }

    public function singleSelect(): static
    {
        return $this->state(['allow_multiple' => false]);
    }
}
