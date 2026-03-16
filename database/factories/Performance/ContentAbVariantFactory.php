<?php

namespace Database\Factories\Performance;

use App\Models\Performance\ContentAbVariant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<ContentAbVariant> */
class ContentAbVariantFactory extends Factory
{
    protected $model = ContentAbVariant::class;

    public function definition(): array
    {
        return [
            'test_id' => strtoupper(Str::ulid()),
            'content_id' => strtoupper(Str::ulid()),
            'label' => $this->faker->randomElement(['Control', 'Variant A', 'Variant B', 'Challenger']),
            'is_control' => false,
            'generation_params' => ['tone' => 'casual', 'length' => 'medium'],
            'composite_score' => $this->faker->randomFloat(2, 0, 100),
            'view_count' => $this->faker->numberBetween(0, 5000),
            'conversion_rate' => $this->faker->randomFloat(4, 0, 0.2),
        ];
    }

    public function control(): static
    {
        return $this->state(['is_control' => true, 'label' => 'Control']);
    }
}
