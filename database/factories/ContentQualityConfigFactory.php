<?php

namespace Database\Factories;

use App\Models\ContentQualityConfig;
use App\Models\Space;
use Illuminate\Database\Eloquent\Factories\Factory;

class ContentQualityConfigFactory extends Factory
{
    protected $model = ContentQualityConfig::class;

    public function definition(): array
    {
        return [
            'space_id' => Space::factory(),
            'dimension_weights' => [
                'readability' => 0.2,
                'seo' => 0.25,
                'brand' => 0.2,
                'factual' => 0.2,
                'engagement' => 0.15,
            ],
            'thresholds' => [
                'readability' => 60,
                'seo' => 60,
                'brand' => 60,
                'factual' => 60,
                'engagement' => 60,
            ],
            'enabled_dimensions' => ['readability', 'seo', 'brand', 'factual', 'engagement'],
            'auto_score_on_publish' => true,
            'pipeline_gate_enabled' => false,
            'pipeline_gate_min_score' => 70.00,
        ];
    }

    public function withGate(): static
    {
        return $this->state([
            'pipeline_gate_enabled' => true,
            'pipeline_gate_min_score' => 75.00,
        ]);
    }
}
