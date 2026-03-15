<?php

namespace Tests\Feature;

use App\Models\Content;
use App\Models\ContentGraphEdge;
use App\Models\ContentGraphNode;
use App\Models\Role;
use App\Models\Space;
use App\Models\User;
use App\Services\Graph\GraphIndexingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GraphApiTest extends TestCase
{
    use RefreshDatabase;

    private Space $space;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->space = Space::factory()->create();
        $this->user = $this->userWithPermissions(['*']);
    }

    // -----------------------------------------------------------------------
    // GET /api/v1/graph/related/{contentId}
    // -----------------------------------------------------------------------

    public function test_user_can_get_related_content(): void
    {
        Sanctum::actingAs($this->user);

        // Create source node
        $sourceContent = Content::factory()->create(['space_id' => $this->space->id]);
        $targetContent = Content::factory()->create(['space_id' => $this->space->id]);

        $sourceNode = ContentGraphNode::factory()->create([
            'content_id' => $sourceContent->id,
            'space_id' => $this->space->id,
        ]);
        $targetNode = ContentGraphNode::factory()->create([
            'content_id' => $targetContent->id,
            'space_id' => $this->space->id,
        ]);

        ContentGraphEdge::factory()->create([
            'space_id' => $this->space->id,
            'source_id' => $sourceNode->id,
            'target_id' => $targetNode->id,
            'edge_type' => 'SHARES_TOPIC',
            'weight' => 0.8,
        ]);

        $response = $this->getJson(
            '/api/v1/graph/related/'.$sourceContent->id.'?space_id='.$this->space->id
        );

        $response->assertOk()
            ->assertJsonStructure(['data'])
            ->assertJsonCount(1, 'data');
    }

    // -----------------------------------------------------------------------
    // GET /api/v1/graph/clusters
    // -----------------------------------------------------------------------

    public function test_user_can_get_topic_clusters(): void
    {
        Sanctum::actingAs($this->user);

        $clusterId = (string) \Illuminate\Support\Str::ulid();

        ContentGraphNode::factory()->count(3)->create([
            'space_id' => $this->space->id,
            'cluster_id' => $clusterId,
        ]);

        $response = $this->getJson('/api/v1/graph/clusters?space_id='.$this->space->id);

        $response->assertOk()
            ->assertJsonStructure(['data'])
            ->assertJsonCount(1, 'data');
    }

    // -----------------------------------------------------------------------
    // GET /api/v1/graph/clusters/{clusterId}
    // -----------------------------------------------------------------------

    public function test_user_can_get_cluster_contents(): void
    {
        Sanctum::actingAs($this->user);

        $clusterId = (string) \Illuminate\Support\Str::ulid();

        ContentGraphNode::factory()->count(2)->create([
            'space_id' => $this->space->id,
            'cluster_id' => $clusterId,
        ]);

        $response = $this->getJson('/api/v1/graph/clusters/'.$clusterId);

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    }

    // -----------------------------------------------------------------------
    // GET /api/v1/graph/gaps
    // -----------------------------------------------------------------------

    public function test_user_can_get_content_gaps(): void
    {
        Sanctum::actingAs($this->user);

        $clusterId = (string) \Illuminate\Support\Str::ulid();

        // A cluster with only 1 node is a gap (< 3 nodes)
        ContentGraphNode::factory()->create([
            'space_id' => $this->space->id,
            'cluster_id' => $clusterId,
            'indexed_at' => now()->subDays(60), // old content
        ]);

        $response = $this->getJson('/api/v1/graph/gaps?space_id='.$this->space->id);

        $response->assertOk()
            ->assertJsonStructure(['data']);

        $data = $response->json('data');
        $this->assertNotEmpty($data);
    }

    // -----------------------------------------------------------------------
    // GET /api/v1/graph/path/{fromId}/{toId}
    // -----------------------------------------------------------------------

    public function test_user_can_get_shortest_path(): void
    {
        Sanctum::actingAs($this->user);

        $content1 = Content::factory()->create(['space_id' => $this->space->id]);
        $content2 = Content::factory()->create(['space_id' => $this->space->id]);

        $node1 = ContentGraphNode::factory()->create([
            'content_id' => $content1->id,
            'space_id' => $this->space->id,
        ]);
        $node2 = ContentGraphNode::factory()->create([
            'content_id' => $content2->id,
            'space_id' => $this->space->id,
        ]);

        ContentGraphEdge::factory()->create([
            'space_id' => $this->space->id,
            'source_id' => $node1->id,
            'target_id' => $node2->id,
            'edge_type' => 'SHARES_TOPIC',
            'weight' => 0.7,
        ]);

        $response = $this->getJson(
            '/api/v1/graph/path/'.$content1->id.'/'.$content2->id.'?space_id='.$this->space->id
        );

        $response->assertOk()
            ->assertJsonStructure(['data' => ['path', 'length']]);
    }

    // -----------------------------------------------------------------------
    // GET /api/v1/graph/node/{contentId}
    // -----------------------------------------------------------------------

    public function test_user_can_get_node_details(): void
    {
        Sanctum::actingAs($this->user);

        $content = Content::factory()->create(['space_id' => $this->space->id]);
        ContentGraphNode::factory()->create([
            'content_id' => $content->id,
            'space_id' => $this->space->id,
        ]);

        $response = $this->getJson(
            '/api/v1/graph/node/'.$content->id.'?space_id='.$this->space->id
        );

        $response->assertOk()
            ->assertJsonStructure([
                'data' => ['node_id', 'content_id', 'space_id', 'locale', 'cluster_id', 'entity_labels', 'node_metadata'],
            ]);
    }

    // -----------------------------------------------------------------------
    // POST /api/v1/graph/reindex/{contentId}
    // -----------------------------------------------------------------------

    public function test_admin_can_reindex_content(): void
    {
        $admin = $this->userWithPermissions(['search.admin']);
        Sanctum::actingAs($admin);

        // Mock GraphIndexingService so no real LLM calls
        $mockNode = ContentGraphNode::factory()->make([
            'content_id' => 'fake-id',
            'space_id' => $this->space->id,
            'indexed_at' => now(),
        ]);
        $mockNode->id = (string) \Illuminate\Support\Str::ulid();
        $mockNode->indexed_at = now();

        $mockIndexer = $this->createMock(GraphIndexingService::class);
        $mockIndexer->method('indexContent')->willReturn($mockNode);
        $this->app->instance(GraphIndexingService::class, $mockIndexer);

        $content = Content::factory()->create(['space_id' => $this->space->id]);

        $response = $this->postJson(
            '/api/v1/graph/reindex/'.$content->id,
            ['space_id' => $this->space->id]
        );

        $response->assertOk()
            ->assertJsonStructure(['data' => ['node_id', 'content_id', 'indexed_at']]);
    }

    public function test_non_admin_cannot_reindex(): void
    {
        $editor = $this->userWithPermissions(['content.write']);
        Sanctum::actingAs($editor);

        $content = Content::factory()->create(['space_id' => $this->space->id]);

        $response = $this->postJson(
            '/api/v1/graph/reindex/'.$content->id,
            ['space_id' => $this->space->id]
        );

        $response->assertForbidden();
    }

    public function test_unauthenticated_user_gets_401(): void
    {
        $content = Content::factory()->create(['space_id' => $this->space->id]);

        $response = $this->getJson(
            '/api/v1/graph/related/'.$content->id.'?space_id='.$this->space->id
        );

        $response->assertUnauthorized();
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function userWithPermissions(array $permissions): User
    {
        $user = User::factory()->create();

        $role = Role::create([
            'name' => 'Test Role',
            'slug' => 'test-role-'.uniqid(),
            'permissions' => $permissions,
            'is_system' => false,
        ]);

        $user->roles()->attach($role->id, ['space_id' => null]);
        $user->load('roles');

        return $user;
    }
}
