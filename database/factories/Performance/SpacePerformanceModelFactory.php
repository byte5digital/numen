<?php

namespace Database\Factories\Performance;

use App\Models\Performance\SpacePerformanceModel;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<SpacePerformanceModel> */
class SpacePerformanceModelFactory extends Factory
{
    protected $model = SpacePerformanceModel::class;

    public function definition(): array
    {
        return [
            'space_id' => strtoupper(Str::ulid()),
            'attribute_weights' => ['word_count' => 0.15, 'ai_quality_score' => 0.30, 'tone' => 0.20],
            'top_performers' => [strtoupper(Str::ulid()), strtoupper(Str::ulid())],
            'bottom_performers' => [strtoupper(Str::ulid()), strtoupper(Str::ulid())],
            'topic_scores' => ['ai' => 82.5, 'seo' => 74.3],
            'persona_scores' => ['technical' => 78.2, 'executive' => 65.4],
            'sample_size' => $this->faker->numberBetween(50, 5000),
            'model_confidence' => $this->faker->randomFloat(4, 0.5, 0.99),
            'model_version' => 'v1',
            'computed_at' => $this->faker->dateTimeBetween('-7 days', 'now'),
        ];
    }
}
