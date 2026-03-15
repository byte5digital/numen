<?php

namespace Database\Factories;

use App\Models\RepurposingBatch;
use App\Models\Space;
use Illuminate\Database\Eloquent\Factories\Factory;

class RepurposingBatchFactory extends Factory
{
    protected $model = RepurposingBatch::class;

    public function definition(): array
    {
        return [
            'space_id' => Space::factory(),
            'format_key' => $this->faker->word(),
            'status' => 'processing',
            'total_items' => $this->faker->numberBetween(1, 50),
            'completed_items' => 0,
            'failed_items' => 0,
        ];
    }

    public function forSpace(Space $space): static
    {
        return $this->state([
            'space_id' => $space->id,
        ]);
    }
}
