<?php

namespace Database\Factories;

use App\Models\Content;
use App\Models\ContentQualityScore;
use App\Models\Space;
use Illuminate\Database\Eloquent\Factories\Factory;

class ContentQualityScoreFactory extends Factory
{
    protected $model = ContentQualityScore::class;

    public function definition(): array
    {
        return [
            'space_id' => Space::factory(),
            'content_id' => Content::factory(),
            'content_version_id' => null,
            'overall_score' => $this->faker->randomFloat(2, 0, 100),
            'readability_score' => $this->faker->randomFloat(2, 0, 100),
            'seo_score' => $this->faker->randomFloat(2, 0, 100),
            'brand_score' => $this->faker->randomFloat(2, 0, 100),
            'factual_score' => $this->faker->randomFloat(2, 0, 100),
            'engagement_score' => $this->faker->randomFloat(2, 0, 100),
            'scoring_model' => 'gpt-4o',
            'scoring_duration_ms' => $this->faker->numberBetween(100, 5000),
            'scored_at' => now(),
        ];
    }

    public function passing(): static
    {
        return $this->state([
            'overall_score' => $this->faker->randomFloat(2, 70, 100),
        ]);
    }

    public function failing(): static
    {
        return $this->state([
            'overall_score' => $this->faker->randomFloat(2, 0, 69.99),
        ]);
    }
}
