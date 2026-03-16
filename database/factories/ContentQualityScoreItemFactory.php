<?php

namespace Database\Factories;

use App\Models\ContentQualityScore;
use App\Models\ContentQualityScoreItem;
use Illuminate\Database\Eloquent\Factories\Factory;

class ContentQualityScoreItemFactory extends Factory
{
    protected $model = ContentQualityScoreItem::class;

    public function definition(): array
    {
        $dimensions = ['readability', 'seo', 'brand', 'factual', 'engagement'];

        return [
            'score_id' => ContentQualityScore::factory(),
            'dimension' => $this->faker->randomElement($dimensions),
            'category' => $this->faker->word(),
            'rule_key' => 'rule.'.$this->faker->word(),
            'label' => $this->faker->sentence(4),
            'severity' => $this->faker->randomElement(['info', 'warning', 'error']),
            'score_impact' => $this->faker->randomFloat(2, -20, 0),
            'message' => $this->faker->sentence(),
            'suggestion' => $this->faker->sentence(),
            'metadata' => null,
        ];
    }

    public function error(): static
    {
        return $this->state(['severity' => 'error']);
    }

    public function warning(): static
    {
        return $this->state(['severity' => 'warning']);
    }

    public function info(): static
    {
        return $this->state(['severity' => 'info']);
    }
}
