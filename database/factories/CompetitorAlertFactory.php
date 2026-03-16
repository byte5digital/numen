<?php

namespace Database\Factories;

use App\Models\CompetitorAlert;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class CompetitorAlertFactory extends Factory
{
    protected $model = CompetitorAlert::class;

    public function definition(): array
    {
        return [
            'space_id' => Str::ulid()->toBase32(),
            'name' => $this->faker->words(3, true),
            'type' => $this->faker->randomElement(['new_competitor_content', 'high_similarity', 'topic_overlap']),
            'conditions' => [],
            'is_active' => true,
            'notify_channels' => ['email'],
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
