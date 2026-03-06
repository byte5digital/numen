<?php

namespace Tests\Feature;

use App\Models\ContentBrief;
use App\Models\ContentPipeline;
use App\Models\PipelineRun;
use App\Models\Space;
use App\Models\User;
use App\Pipelines\PipelineExecutor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BriefApiTest extends TestCase
{
    use RefreshDatabase;

    private Space $space;
    private ContentPipeline $pipeline;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->space    = Space::factory()->create();
        $this->pipeline = ContentPipeline::factory()->create([
            'space_id'  => $this->space->id,
            'is_active' => true,
        ]);
        $this->user = User::factory()->create();
    }

    // --- Authentication ---

    public function test_unauthenticated_cannot_create_brief(): void
    {
        $response = $this->postJson('/api/v1/briefs', [
            'space_id'          => $this->space->id,
            'title'             => 'Test Brief',
            'content_type_slug' => 'blog_post',
        ]);

        $response->assertUnauthorized();
    }

    public function test_unauthenticated_cannot_list_briefs(): void
    {
        $response = $this->getJson('/api/v1/briefs');

        $response->assertUnauthorized();
    }

    // --- Brief creation ---

    public function test_authenticated_user_can_create_brief(): void
    {
        Bus::fake();
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/v1/briefs', [
            'space_id'          => $this->space->id,
            'title'             => 'My New Article',
            'description'       => 'Write about modern Laravel practices.',
            'content_type_slug' => 'blog_post',
            'target_keywords'   => ['laravel', 'php'],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', 'processing');

        $this->assertDatabaseHas('content_briefs', [
            'title'  => 'My New Article',
            'status' => 'processing',
        ]);
    }

    public function test_brief_creation_triggers_pipeline(): void
    {
        Bus::fake();
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/v1/briefs', [
            'space_id'          => $this->space->id,
            'title'             => 'Pipeline Test Article',
            'content_type_slug' => 'blog_post',
        ]);

        $response->assertCreated();

        $briefId = $response->json('data.brief_id');
        $runId   = $response->json('data.pipeline_run_id');

        $this->assertDatabaseHas('pipeline_runs', [
            'id'     => $runId,
            'status' => 'running',
        ]);

        $this->assertDatabaseHas('content_briefs', [
            'id'     => $briefId,
            'status' => 'processing',
        ]);
    }

    public function test_brief_creation_validates_required_fields(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/v1/briefs', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['space_id', 'title', 'content_type_slug']);
    }

    public function test_brief_creation_validates_space_exists(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/v1/briefs', [
            'space_id'          => 'nonexistent-space-id',
            'title'             => 'Test',
            'content_type_slug' => 'blog_post',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['space_id']);
    }

    public function test_brief_creation_fails_when_no_active_pipeline(): void
    {
        Sanctum::actingAs($this->user);

        $this->pipeline->update(['is_active' => false]);

        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        $this->withoutExceptionHandling()->postJson('/api/v1/briefs', [
            'space_id'          => $this->space->id,
            'title'             => 'Test Article',
            'content_type_slug' => 'blog_post',
        ]);
    }

    // --- Brief listing ---

    public function test_authenticated_user_can_list_briefs(): void
    {
        Sanctum::actingAs($this->user);

        ContentBrief::factory()->count(3)->create(['space_id' => $this->space->id]);

        $response = $this->getJson('/api/v1/briefs');

        $response->assertOk()
            ->assertJsonPath('data.total', 3);
    }

    public function test_briefs_can_be_filtered_by_space(): void
    {
        Sanctum::actingAs($this->user);

        $otherSpace = Space::factory()->create();
        ContentBrief::factory()->count(2)->create(['space_id' => $this->space->id]);
        ContentBrief::factory()->count(3)->create(['space_id' => $otherSpace->id]);

        $response = $this->getJson('/api/v1/briefs?space_id=' . $this->space->id);

        $response->assertOk()
            ->assertJsonPath('data.total', 2);
    }

    public function test_briefs_can_be_filtered_by_status(): void
    {
        Sanctum::actingAs($this->user);

        ContentBrief::factory()->count(2)->create(['space_id' => $this->space->id, 'status' => 'pending']);
        ContentBrief::factory()->count(1)->create(['space_id' => $this->space->id, 'status' => 'completed']);

        $response = $this->getJson('/api/v1/briefs?status=completed');

        $response->assertOk()
            ->assertJsonPath('data.total', 1);
    }

    // --- Brief show ---

    public function test_can_show_brief_by_id(): void
    {
        Sanctum::actingAs($this->user);

        $brief = ContentBrief::factory()->create(['space_id' => $this->space->id]);

        $response = $this->getJson('/api/v1/briefs/' . $brief->id);

        $response->assertOk()
            ->assertJsonPath('data.id', $brief->id)
            ->assertJsonPath('data.title', $brief->title);
    }

    public function test_show_returns_404_for_missing_brief(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/v1/briefs/nonexistent-id');

        $response->assertNotFound();
    }
}
