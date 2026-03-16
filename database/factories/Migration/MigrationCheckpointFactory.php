<?php

namespace Database\Factories\Migration;

use App\Models\Migration\MigrationCheckpoint;
use App\Models\Migration\MigrationSession;
use Illuminate\Database\Eloquent\Factories\Factory;

class MigrationCheckpointFactory extends Factory
{
    protected $model = MigrationCheckpoint::class;

    public function definition(): array
    {
        $session = MigrationSession::factory()->create();

        return [
            'migration_session_id' => $session->id,
            'space_id' => $session->space_id,
            'source_type_key' => $this->faker->randomElement(['post', 'page', 'product']),
            'last_cursor' => (string) $this->faker->numberBetween(1, 10000),
            'last_synced_at' => now(),
            'item_count' => $this->faker->numberBetween(0, 500),
        ];
    }
}
