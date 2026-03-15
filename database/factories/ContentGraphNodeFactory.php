<?php

namespace Database\Factories;

use App\Models\Content;
use App\Models\ContentGraphNode;
use App\Models\Space;
use Illuminate\Database\Eloquent\Factories\Factory;

class ContentGraphNodeFactory extends Factory
{
    protected $model = ContentGraphNode::class;

    public function definition(): array
    {
        return [
            'content_id' => Content::factory(),
            'space_id' => Space::factory(),
            'locale' => 'en',
            'entity_labels' => $this->faker->randomElements(
                ['AI', 'machine learning', 'content strategy', 'SEO', 'marketing', 'automation'],
                $this->faker->numberBetween(2, 5)
            ),
            'cluster_id' => null,
            'node_metadata' => [
                'title' => $this->faker->sentence(4),
                'slug' => $this->faker->slug(3),
                'content_type' => 'blog_post',
                'published_at' => null,
                'entities' => [],
            ],
            'indexed_at' => now(),
        ];
    }

    public function inCluster(string $clusterId): static
    {
        return $this->state(['cluster_id' => $clusterId]);
    }

    public function withEntities(array $entities): static
    {
        return $this->state(['entity_labels' => $entities]);
    }
}
