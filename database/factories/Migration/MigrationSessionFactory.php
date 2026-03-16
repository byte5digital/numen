<?php

namespace Database\Factories\Migration;

use App\Models\Migration\MigrationSession;
use App\Models\Space;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class MigrationSessionFactory extends Factory
{
    protected $model = MigrationSession::class;

    public function definition(): array
    {
        $cmsList = ['wordpress', 'contentful', 'drupal', 'ghost', 'strapi', 'sanity'];

        return [
            'space_id' => Space::factory(),
            'created_by' => User::factory(),
            'name' => $this->faker->sentence(3),
            'source_cms' => $this->faker->randomElement($cmsList),
            'source_url' => $this->faker->url(),
            'source_version' => $this->faker->optional()->numerify('#.#.#'),
            'credentials' => null,
            'status' => 'pending',
            'total_items' => 0,
            'processed_items' => 0,
            'failed_items' => 0,
            'skipped_items' => 0,
            'options' => null,
            'error_message' => null,
            'schema_snapshot' => null,
            'started_at' => null,
            'completed_at' => null,
        ];
    }

    public function running(): static
    {
        return $this->state([
            'status' => 'running',
            'started_at' => now(),
            'total_items' => $this->faker->numberBetween(100, 10000),
        ]);
    }

    public function completed(): static
    {
        return $this->state(function (array $attributes) {
            $total = $this->faker->numberBetween(50, 5000);

            return [
                'status' => 'completed',
                'started_at' => now()->subHour(),
                'completed_at' => now(),
                'total_items' => $total,
                'processed_items' => $total,
                'failed_items' => 0,
                'skipped_items' => 0,
            ];
        });
    }
}
