<?php

namespace Database\Factories\Performance;

use App\Models\Performance\ContentAbTest;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<ContentAbTest> */
class ContentAbTestFactory extends Factory
{
    protected $model = ContentAbTest::class;

    public function definition(): array
    {
        $status = $this->faker->randomElement(['draft', 'running', 'completed', 'stopped']);

        return [
            'space_id' => strtoupper(Str::ulid()),
            'name' => 'A/B Test: '.$this->faker->words(3, true),
            'hypothesis' => $this->faker->sentence(10),
            'status' => $status,
            'metric' => $this->faker->randomElement(['conversion_rate', 'engagement_score', 'time_on_page']),
            'traffic_split' => $this->faker->randomFloat(4, 0.3, 0.7),
            'min_sample_size' => $this->faker->numberBetween(50, 500),
            'significance_threshold' => $this->faker->randomElement([0.9000, 0.9500, 0.9900]),
            'started_at' => in_array($status, ['running', 'completed', 'stopped'])
                ? $this->faker->dateTimeBetween('-30 days', '-1 day')
                : null,
            'ended_at' => in_array($status, ['completed', 'stopped'])
                ? $this->faker->dateTimeBetween('-1 day', 'now')
                : null,
            'winner_variant_id' => $status === 'completed' ? strtoupper(Str::ulid()) : null,
            'conclusion' => $status === 'completed'
                ? ['summary' => $this->faker->sentence(), 'lift' => 0.12]
                : null,
        ];
    }
}
