<?php

namespace Database\Factories\Performance;

use App\Models\Performance\PerformanceCorrelation;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<PerformanceCorrelation> */
class PerformanceCorrelationFactory extends Factory
{
    protected $model = PerformanceCorrelation::class;

    public function definition(): array
    {
        $attributes = ['word_count', 'image_count', 'heading_count', 'ai_quality_score', 'tone', 'format_type'];
        $metrics = ['views', 'engagement_events', 'conversions', 'avg_scroll_depth', 'bounce_rate', 'composite_score'];

        return [
            'space_id' => strtoupper(Str::ulid()),
            'content_id' => strtoupper(Str::ulid()),
            'attribute_name' => $this->faker->randomElement($attributes),
            'metric_name' => $this->faker->randomElement($metrics),
            'correlation_coefficient' => $this->faker->randomFloat(4, -1, 1),
            'p_value' => $this->faker->randomFloat(4, 0, 1),
            'sample_size' => $this->faker->numberBetween(10, 500),
            'insight' => $this->faker->sentence(),
            'metadata' => ['method' => 'pearson'],
        ];
    }
}
