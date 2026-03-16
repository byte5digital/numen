<?php

namespace Database\Factories\Performance;

use App\Models\Performance\ContentPerformanceEvent;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<ContentPerformanceEvent> */
class ContentPerformanceEventFactory extends Factory
{
    protected $model = ContentPerformanceEvent::class;

    public function definition(): array
    {
        return [
            'space_id' => strtoupper(Str::ulid()),
            'content_id' => strtoupper(Str::ulid()),
            'content_version_id' => $this->faker->boolean(70) ? strtoupper(Str::ulid()) : null,
            'variant_id' => $this->faker->boolean(30) ? strtoupper(Str::ulid()) : null,
            'event_type' => $this->faker->randomElement(['view', 'click', 'scroll', 'conversion', 'bounce']),
            'source' => $this->faker->randomElement(['web', 'api', 'email', 'social', 'direct']),
            'value' => $this->faker->randomFloat(4, 0.1, 10),
            'metadata' => ['user_agent' => $this->faker->userAgent()],
            'session_id' => $this->faker->boolean(80) ? $this->faker->uuid() : null,
            'visitor_id' => $this->faker->boolean(80) ? $this->faker->uuid() : null,
            'occurred_at' => $this->faker->dateTimeBetween('-30 days', 'now'),
        ];
    }
}
