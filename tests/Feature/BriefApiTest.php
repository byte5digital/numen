<?php

namespace Tests\Feature;

use App\Models\ContentBrief;
use App\Models\ContentPipeline;
use App\Models\Role;
use App\Models\Space;
use App\Models\User;
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

        $this->space = Space::factory()->create();
        $this->pipeline = ContentPipeline::factory()->create([
            'space_id' => $this->space->id,
            'is_active' => true,
        ]);

        // Create an admin user with wildcard permissions and global role (no space restriction)
        // This satisfies space isolation checks (global role = access to all spaces)
        $this->user = $this->adminUser();
    }

    // --- Authentication ---

    public function test_unauthenticated_cannot_create_brief(): void
    {
        $response = $this->postJson('/api/v1/briefs', [
            'space_id' => $this->space->id,
            'title' => 'Test Brief',
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
            'space_id' => $this->space->id,
            'title' => 'My New Article',
            'description' => 'Write about modern Laravel practices.',
            'content_type_slug' => 'blog_post',
            'target_keywords' => ['laravel', 'php'],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', 'processing');

        $this->assertDatabaseHas('content_briefs', [
            'title' => 'My New Article',
            'status' => 'processing',
        ]);
    }

    public function test_brief_creation_triggers_pipeline(): void
    {
        Bus::fake();
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/v1/briefs', [
            'space_id' => $this->space->id,
            'title' => 'Pipeline Test Article',
            'content_type_slug' => 'blog_post',
        ]);

        $response->assertCreated();

        $briefId = $response->json('data.brief_id');
        $runId = $response->json('data.pipeline_run_id');

        $this->assertDatabaseHas('pipeline_runs', [
            'id' => $runId,
            'status' => 'running',
        ]);

        $this->assertDatabaseHas('content_briefs', [
            'id' => $briefId,
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
            'space_id' => 'nonexistent-space-id',
            'title' => 'Test',
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
            'space_id' => $this->space->id,
            'title' => 'Test Article',
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

        $response = $this->getJson('/api/v1/briefs?space_id='.$this->space->id);

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

        $response = $this->getJson('/api/v1/briefs/'.$brief->id);

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

    // --- Security regression tests ---

    /**
     * A user with no permissions cannot create a brief (regression: CRITICAL #1).
     */
    public function test_user_without_permission_cannot_create_brief(): void
    {
        $unprivilegedUser = $this->userWithRole(['content.read']);
        Sanctum::actingAs($unprivilegedUser);

        $response = $this->postJson('/api/v1/briefs', [
            'space_id' => $this->space->id,
            'title' => 'Unauthorized Brief',
            'content_type_slug' => 'blog_post',
        ]);

        $response->assertForbidden();
    }

    /**
     * A user without content.read cannot list briefs (regression: CRITICAL #1).
     */
    public function test_user_without_permission_cannot_list_briefs(): void
    {
        $unprivilegedUser = $this->userWithRole(['users.manage']);
        Sanctum::actingAs($unprivilegedUser);

        $response = $this->getJson('/api/v1/briefs');

        $response->assertForbidden();
    }

    /**
     * A user without content.read cannot view a brief (regression: CRITICAL #1).
     */
    public function test_user_without_permission_cannot_show_brief(): void
    {
        $unprivilegedUser = $this->userWithRole(['users.manage']);
        $brief = ContentBrief::factory()->create(['space_id' => $this->space->id]);
        Sanctum::actingAs($unprivilegedUser);

        $response = $this->getJson('/api/v1/briefs/'.$brief->id);

        $response->assertForbidden();
    }

    /**
     * Space isolation: user scoped to Space A cannot create briefs in Space B (regression: HIGH #5).
     */
    public function test_space_scoped_user_cannot_create_brief_in_foreign_space(): void
    {
        $spaceA = Space::factory()->create();
        $spaceB = Space::factory()->create();
        ContentPipeline::factory()->create(['space_id' => $spaceB->id, 'is_active' => true]);

        // User has content.create, but only in spaceA (space-scoped role)
        $user = User::factory()->create();
        $role = Role::create([
            'name' => 'Space A Author',
            'slug' => 'space-a-author-' . uniqid(),
            'permissions' => ['content.create', 'content.read'],
            'is_system' => false,
        ]);
        $user->roles()->attach($role->id, ['space_id' => $spaceA->id]);
        $user->load('roles');

        Sanctum::actingAs($user);

        // Attempt to create a brief in Space B — should be denied
        $response = $this->postJson('/api/v1/briefs', [
            'space_id' => $spaceB->id,
            'title' => 'Unauthorized Cross-Space Brief',
            'content_type_slug' => 'blog_post',
        ]);

        $response->assertForbidden();
    }

    /**
     * Space isolation: user scoped to Space A can only see Space A briefs when listing (regression: CRITICAL #1).
     */
    public function test_space_scoped_user_only_sees_own_space_briefs(): void
    {
        $spaceA = Space::factory()->create();
        $spaceB = Space::factory()->create();

        ContentBrief::factory()->count(2)->create(['space_id' => $spaceA->id]);
        ContentBrief::factory()->count(3)->create(['space_id' => $spaceB->id]);

        // User has content.read in spaceA only
        $user = User::factory()->create();
        $role = Role::create([
            'name' => 'Space A Reader',
            'slug' => 'space-a-reader-' . uniqid(),
            'permissions' => ['content.read'],
            'is_system' => false,
        ]);
        $user->roles()->attach($role->id, ['space_id' => $spaceA->id]);
        $user->load('roles');

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/briefs');

        $response->assertOk()
            ->assertJsonPath('data.total', 2);  // Only Space A briefs
    }

    /**
     * Store creates an audit log entry (regression: CRITICAL #1 audit requirement).
     */
    public function test_brief_creation_creates_audit_log(): void
    {
        Bus::fake();
        Sanctum::actingAs($this->user);

        $this->postJson('/api/v1/briefs', [
            'space_id' => $this->space->id,
            'title' => 'Audited Brief',
            'content_type_slug' => 'blog_post',
        ])->assertCreated();

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'brief.create',
            'user_id' => $this->user->id,
        ]);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function adminUser(): User
    {
        return $this->userWithRole(['*']);
    }

    private function userWithRole(array $permissions): User
    {
        $user = User::factory()->create();

        $role = Role::create([
            'name'        => 'Test Role',
            'slug'        => 'test-role-' . uniqid(),
            'permissions' => $permissions,
            'is_system'   => false,
        ]);

        // Global role (space_id = null) — grants access to all spaces
        $user->roles()->attach($role->id, ['space_id' => null]);
        $user->load('roles');

        return $user;
    }
}
