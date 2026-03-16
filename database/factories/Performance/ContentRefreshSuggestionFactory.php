<?php

namespace Database\Factories\Performance;

use App\Models\Performance\ContentRefreshSuggestion;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<ContentRefreshSuggestion> */
class ContentRefreshSuggestionFactory extends Factory
{
    protected $model = ContentRefreshSuggestion::class;

    public function definition(): array
    {
        $status = $this->faker->randomElement(['pending', 'in_progress', 'completed', 'dismissed']);

        return [
            'space_id' => strtoupper(Str::ulid()),
            'content_id' => strtoupper(Str::ulid()),
            'status' => $status,
            'trigger_type' => $this->faker->randomElement([
                'performance_drop', 'staleness', 'competitor_update', 'keyword_opportunity', 'manual',
            ]),
            'performance_context' => [
                'current_score' => 35.5,
                'peak_score' => 80.2,
                'drop_percentage' => 44.7,
            ],
            'suggestions' => [
                ['type' => 'update_keywords', 'priority' => 'high', 'detail' => 'Add trending keywords'],
                ['type' => 'add_images', 'priority' => 'medium', 'detail' => 'Include more visuals'],
            ],
            'urgency_score' => $this->faker->randomFloat(2, 0, 100),
            'brief_id' => $this->faker->boolean(40) ? strtoupper(Str::ulid()) : null,
            'triggered_at' => $this->faker->dateTimeBetween('-30 days', 'now'),
            'acted_on_at' => $status === 'completed'
                ? $this->faker->dateTimeBetween('-7 days', 'now')
                : null,
        ];
    }
}
