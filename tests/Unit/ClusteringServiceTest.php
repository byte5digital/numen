<?php

namespace Tests\Unit;

use App\Models\ContentGraphNode;
use App\Models\Space;
use App\Services\Graph\ClusteringService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClusteringServiceTest extends TestCase
{
    use RefreshDatabase;

    private ClusteringService $service;

    private Space $space;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new ClusteringService;
        $this->space = Space::factory()->create();
    }

    public function test_clusters_nodes_by_entity_similarity(): void
    {
        // Two nodes with high entity overlap → should be merged into 1 cluster
        $node1 = ContentGraphNode::factory()->create([
            'space_id' => $this->space->id,
            'entity_labels' => ['AI', 'machine learning', 'deep learning', 'neural networks'],
            'cluster_id' => null,
        ]);
        $node2 = ContentGraphNode::factory()->create([
            'space_id' => $this->space->id,
            'entity_labels' => ['AI', 'machine learning', 'deep learning', 'transformers'],
            'cluster_id' => null,
        ]);
        // Third node with completely different entities → separate cluster
        $node3 = ContentGraphNode::factory()->create([
            'space_id' => $this->space->id,
            'entity_labels' => ['cooking', 'recipes', 'food', 'nutrition'],
            'cluster_id' => null,
        ]);

        $clusterCount = $this->service->computeClusters($this->space->id);

        // node1 + node2 should merge (high Jaccard), node3 stays separate
        $this->assertGreaterThanOrEqual(2, $clusterCount);

        $freshNode1 = $node1->fresh();
        $freshNode2 = $node2->fresh();
        $freshNode3 = $node3->fresh();

        $this->assertNotNull($freshNode1?->cluster_id);
        $this->assertNotNull($freshNode2?->cluster_id);
        $this->assertNotNull($freshNode3?->cluster_id);

        // node1 and node2 should be in same cluster
        $this->assertEquals($freshNode1?->cluster_id, $freshNode2?->cluster_id);
        // node3 should be in a different cluster
        $this->assertNotEquals($freshNode1?->cluster_id, $freshNode3?->cluster_id);
    }

    public function test_handles_single_node(): void
    {
        ContentGraphNode::factory()->create([
            'space_id' => $this->space->id,
            'entity_labels' => ['solo'],
            'cluster_id' => null,
        ]);

        $clusterCount = $this->service->computeClusters($this->space->id);

        $this->assertEquals(1, $clusterCount);
    }
}
