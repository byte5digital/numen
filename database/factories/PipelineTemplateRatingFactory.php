<?php

namespace Database\Factories;

use App\Models\PipelineTemplate;
use App\Models\PipelineTemplateRating;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PipelineTemplateRatingFactory extends Factory
{
    protected $model = PipelineTemplateRating::class;

    public function definition(): array
    {
        return [
            'template_id' => PipelineTemplate::factory(),
            'user_id' => User::factory(),
            'rating' => $this->faker->numberBetween(1, 5),
            'review' => $this->faker->optional()->sentence(),
        ];
    }

    public function withRating(int $rating): static
    {
        return $this->state(['rating' => $rating]);
    }
}
