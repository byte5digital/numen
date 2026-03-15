<?php

namespace Tests\Unit;

use App\Models\Content;
use App\Models\ContentGraphEdge;
use App\Models\ContentGraphNode;
use App\Models\Space;
use App\Services\Graph\GraphQueryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GraphQueryServiceTest extends TestCase
{
    use RefreshDatabase;

    private GraphQueryService $service;

    private Space $space;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new GraphQueryService;
        $this->space = Space::factory()->create();
    }

    public function test_related_content_returns_ordered_by_weight(): void
    {
        $sourceContent = Content::factory()->create(['space_id' => $this->space->id]);
        $target1 = Content::factory()->create(['space_id' => $this->space->id]);
        $target2 = Content::factory()->create(['space_id' => $this->space->id]);

        $sourceNode = ContentGraphNode::factory()->create([
            'content_id' => $sourceContent->id,
            'space_id' => $this->space->id,
        ]);
        $targetNode1 = ContentGraphNode::factory()->create([
            'content_id' => $target1->id,
            'space_id' => $this->space->id,
        ]);
        $targetNode2 = ContentGraphNode::factory()->create([
            'content_id' => $target2->id,
            'space_id' => $this->space->id,
        ]);

        ContentGraphEdge::factory()->create([
            'space_id' => $this->space->id,
            'source_id' => $sourceNode->id,
            'target_id' => $targetNode1->id,
            'edge_type' => 'SHARES_TOPIC',
            'weight' => 0.4,
        ]);
        ContentGraphEdge::factory()->create([
            'space_id' => $this->space->id,
            'source_id' => $sourceNode->id,
            'target_id' => $targetNode2->id,
            'edge_type' => 'CO_MENTIONS',
            'weight' => 0.9,
        ]);

        $result = $this->service->relatedContent($sourceContent->id, $this->space->id);

        $this->assertCount(2, $result);
        // First result should have higher weight
        $this->assertGreaterThan($result->get(1)['weight'], $result->get(0)['weight']);
    }

    public function test_shortest_path_finds_path(): void
    {
        $content1 = Content::factory()->create(['space_id' => $this->space->id]);
        $content2 = Content::factory()->create(['space_id' => $this->space->id]);
        $content3 = Content::factory()->create(['space_id' => $this->space->id]);

        $node1 = ContentGraphNode::factory()->create([
            'content_id' => $content1->id,
            'space_id' => $this->space->id,
        ]);
        $node2 = ContentGraphNode::factory()->create([
            'content_id' => $content2->id,
            'space_id' => $this->space->id,
        ]);
        $node3 = ContentGraphNode::factory()->create([
            'content_id' => $content3->id,
            'space_id' => $this->space->id,
        ]);

        // Path: node1 → node2 → node3
        ContentGraphEdge::factory()->create([
            'space_id' => $this->space->id,
            'source_id' => $node1->id,
            'target_id' => $node2->id,
            'edge_type' => 'SHARES_TOPIC',
            'weight' => 0.7,
        ]);
        ContentGraphEdge::factory()->create([
            'space_id' => $this->space->id,
            'source_id' => $node2->id,
            'target_id' => $node3->id,
            'edge_type' => 'SHARES_TOPIC',
            'weight' => 0.7,
        ]);

        $path = $this->service->shortestPath($content1->id, $content3->id, $this->space->id);

        $this->assertNotNull($path);
        $this->assertCount(3, $path);
        $this->assertEquals($node1->id, $path[0]);
        $this->assertEquals($node2->id, $path[1]);
        $this->assertEquals($node3->id, $path[2]);
    }

    public function test_shortest_path_returns_null_when_no_path(): void
    {
        $content1 = Content::factory()->create(['space_id' => $this->space->id]);
        $content2 = Content::factory()->create(['space_id' => $this->space->id]);

        ContentGraphNode::factory()->create([
            'content_id' => $content1->id,
            'space_id' => $this->space->id,
        ]);
        ContentGraphNode::factory()->create([
            'content_id' => $content2->id,
            'space_id' => $this->space->id,
        ]);

        // No edges between them
        $path = $this->service->shortestPath($content1->id, $content2->id, $this->space->id);

        $this->assertNull($path);
    }
}
