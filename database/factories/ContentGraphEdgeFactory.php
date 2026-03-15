<?php

namespace Database\Factories;

use App\Models\ContentGraphEdge;
use App\Models\ContentGraphNode;
use App\Models\Space;
use Illuminate\Database\Eloquent\Factories\Factory;

class ContentGraphEdgeFactory extends Factory
{
    protected $model = ContentGraphEdge::class;

    public function definition(): array
    {
        $spaceId = Space::factory()->create()->id;

        return [
            'space_id' => $spaceId,
            'source_id' => ContentGraphNode::factory()->state(['space_id' => $spaceId]),
            'target_id' => ContentGraphNode::factory()->state(['space_id' => $spaceId]),
            'edge_type' => $this->faker->randomElement(['SHARES_TOPIC', 'CO_MENTIONS', 'CITES', 'SIMILAR_TO']),
            'weight' => $this->faker->randomFloat(2, 0.1, 1.0),
            'edge_metadata' => [],
        ];
    }

    public function sharesTopic(float $weight = 0.6): static
    {
        return $this->state([
            'edge_type' => 'SHARES_TOPIC',
            'weight' => $weight,
            'edge_metadata' => ['jaccard' => $weight],
        ]);
    }

    public function coMentions(float $weight = 0.5): static
    {
        return $this->state([
            'edge_type' => 'CO_MENTIONS',
            'weight' => $weight,
            'edge_metadata' => ['overlap' => $weight],
        ]);
    }

    public function cites(): static
    {
        return $this->state([
            'edge_type' => 'CITES',
            'weight' => 1.0,
            'edge_metadata' => [],
        ]);
    }
}
