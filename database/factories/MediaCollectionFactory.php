<?php

namespace Database\Factories;

use App\Models\MediaCollection;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class MediaCollectionFactory extends Factory
{
    protected $model = MediaCollection::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => $this->faker->word(),
            'description' => $this->faker->text(100),
        ];
    }
}
