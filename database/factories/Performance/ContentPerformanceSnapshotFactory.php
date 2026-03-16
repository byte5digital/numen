<?php

namespace Database\Factories\Performance;

use App\Models\Performance\ContentPerformanceSnapshot;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<ContentPerformanceSnapshot> */
class ContentPerformanceSnapshotFactory extends Factory
{
    protected $model = ContentPerformanceSnapshot::class;

    public function definition(): array
    {
        $views = $this->faker->numberBetween(10, 10000);
        $conversions = (int) ($views * $this->faker->randomFloat(4, 0.001, 0.05));

        return [
            'space_id' => strtoupper(Str::ulid()),
            'content_id' => strtoupper(Str::ulid()),
            'content_version_id' => $this->faker->boolean(70) ? strtoupper(Str::ulid()) : null,
            'period_type' => $this->faker->randomElement(['daily', 'weekly', 'monthly']),
            'period_start' => $this->faker->dateTimeBetween('-90 days', 'now')->format('Y-m-d'),
            'views' => $views,
            'unique_visitors' => (int) ($views * $this->faker->randomFloat(2, 0.5, 0.9)),
            'avg_time_on_page_s' => $this->faker->randomFloat(2, 30, 600),
            'bounce_rate' => $this->faker->randomFloat(4, 0.1, 0.9),
            'avg_scroll_depth' => $this->faker->randomFloat(4, 0.1, 1.0),
            'engagement_events' => $this->faker->numberBetween(0, 500),
            'conversions' => $conversions,
            'conversion_rate' => $views > 0 ? round($conversions / $views, 4) : 0,
            'composite_score' => $this->faker->randomFloat(2, 0, 100),
        ];
    }
}
