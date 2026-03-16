<?php

namespace Database\Factories\Migration;

use App\Models\Migration\MigrationItem;
use App\Models\Migration\MigrationSession;
use Illuminate\Database\Eloquent\Factories\Factory;

class MigrationItemFactory extends Factory
{
    protected $model = MigrationItem::class;

    public function definition(): array
    {
        $session = MigrationSession::factory()->create();

        return [
            'migration_session_id' => $session->id,
            'space_id' => $session->space_id,
            'source_type_key' => $this->faker->randomElement(['post', 'page', 'product']),
            'source_id' => (string) $this->faker->unique()->numberBetween(1, 999999),
            'source_hash' => $this->faker->optional()->sha256(),
            'numen_content_id' => null,
            'numen_media_ids' => null,
            'status' => 'pending',
            'error_message' => null,
            'attempt' => 0,
            'source_payload' => null,
        ];
    }

    public function imported(): static
    {
        return $this->state([
            'status' => 'imported',
            'attempt' => 1,
        ]);
    }

    public function failed(): static
    {
        return $this->state([
            'status' => 'failed',
            'error_message' => $this->faker->sentence(),
            'attempt' => $this->faker->numberBetween(1, 3),
        ]);
    }
}
