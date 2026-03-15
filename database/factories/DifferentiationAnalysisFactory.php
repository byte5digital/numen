<?php

namespace Database\Factories;

use App\Models\CompetitorContentItem;
use App\Models\DifferentiationAnalysis;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class DifferentiationAnalysisFactory extends Factory
{
    protected $model = DifferentiationAnalysis::class;

    public function definition(): array
    {
        return [
            'space_id' => Str::ulid()->toBase32(),
            'content_id' => null,
            'brief_id' => null,
            'competitor_content_id' => CompetitorContentItem::factory(),
            'similarity_score' => $this->faker->randomFloat(4, 0, 1),
            'differentiation_score' => $this->faker->randomFloat(4, 0, 1),
            'angles' => [],
            'gaps' => [],
            'recommendations' => [],
            'analyzed_at' => now(),
        ];
    }
}
