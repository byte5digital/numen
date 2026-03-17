<?php

namespace Tests\Feature;

use App\Models\Content;
use App\Models\Performance\ContentRefreshSuggestion;
use App\Models\Role;
use App\Models\Space;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ContentRefreshControllerTest extends TestCase
{
    use RefreshDatabase;

    private Space $space;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->space = Space::factory()->create();
        $this->user = $this->adminUser();
    }

    public function test_list_refresh_suggestions(): void
    {
        Sanctum::actingAs($this->user);

        ContentRefreshSuggestion::factory()->count(3)->create([
            'space_id' => $this->space->id,
        ]);

        $response = $this->getJson("/api/v1/spaces/{$this->space->id}/refresh-suggestions");

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_list_suggestions_filterable_by_status(): void
    {
        Sanctum::actingAs($this->user);

        ContentRefreshSuggestion::factory()->create([
            'space_id' => $this->space->id,
            'status' => 'pending',
        ]);
        ContentRefreshSuggestion::factory()->create([
            'space_id' => $this->space->id,
            'status' => 'dismissed',
        ]);

        $response = $this->getJson("/api/v1/spaces/{$this->space->id}/refresh-suggestions?status=pending");

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_show_suggestion_detail(): void
    {
        Sanctum::actingAs($this->user);

        $suggestion = ContentRefreshSuggestion::factory()->create([
            'space_id' => $this->space->id,
        ]);

        $response = $this->getJson("/api/v1/spaces/{$this->space->id}/refresh-suggestions/{$suggestion->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $suggestion->id);
    }

    public function test_show_returns_404_for_wrong_space(): void
    {
        Sanctum::actingAs($this->user);

        $otherSpace = Space::factory()->create();
        $suggestion = ContentRefreshSuggestion::factory()->create([
            'space_id' => $otherSpace->id,
        ]);

        $response = $this->getJson("/api/v1/spaces/{$this->space->id}/refresh-suggestions/{$suggestion->id}");

        $response->assertStatus(404);
    }

    public function test_accept_suggestion_generates_brief(): void
    {
        Sanctum::actingAs($this->user);

        $content = Content::factory()->create([
            'space_id' => $this->space->id,
            'status' => 'published',
        ]);

        $suggestion = ContentRefreshSuggestion::factory()->create([
            'space_id' => $this->space->id,
            'content_id' => $content->id,
            'status' => 'pending',
            'urgency_score' => 60.0,
            'suggestions' => [
                ['type' => 'update_content', 'priority' => 'high', 'detail' => 'Declining views'],
            ],
        ]);

        $response = $this->postJson("/api/v1/spaces/{$this->space->id}/refresh-suggestions/{$suggestion->id}/accept");

        $response->assertOk()
            ->assertJsonStructure(['data' => ['suggestion', 'brief']]);

        $suggestion->refresh();
        $this->assertEquals('in_progress', $suggestion->status);
        $this->assertNotNull($suggestion->brief_id);
    }

    public function test_accept_only_pending_suggestions(): void
    {
        Sanctum::actingAs($this->user);

        $content = Content::factory()->create(['space_id' => $this->space->id]);

        $suggestion = ContentRefreshSuggestion::factory()->create([
            'space_id' => $this->space->id,
            'content_id' => $content->id,
            'status' => 'dismissed',
        ]);

        $response = $this->postJson("/api/v1/spaces/{$this->space->id}/refresh-suggestions/{$suggestion->id}/accept");

        $response->assertStatus(422);
    }

    public function test_dismiss_suggestion(): void
    {
        Sanctum::actingAs($this->user);

        $suggestion = ContentRefreshSuggestion::factory()->create([
            'space_id' => $this->space->id,
            'status' => 'pending',
        ]);

        $response = $this->postJson("/api/v1/spaces/{$this->space->id}/refresh-suggestions/{$suggestion->id}/dismiss");

        $response->assertOk()
            ->assertJsonPath('data.status', 'dismissed');

        $suggestion->refresh();
        $this->assertEquals('dismissed', $suggestion->status);
        $this->assertNotNull($suggestion->acted_on_at);
    }

    public function test_dismiss_already_dismissed_returns_422(): void
    {
        Sanctum::actingAs($this->user);

        $suggestion = ContentRefreshSuggestion::factory()->create([
            'space_id' => $this->space->id,
            'status' => 'dismissed',
        ]);

        $response = $this->postJson("/api/v1/spaces/{$this->space->id}/refresh-suggestions/{$suggestion->id}/dismiss");

        $response->assertStatus(422);
    }

    public function test_generate_triggers_analysis(): void
    {
        Sanctum::actingAs($this->user);

        Content::factory()->create([
            'space_id' => $this->space->id,
            'status' => 'published',
            'updated_at' => now()->subDays(120),
        ]);

        $response = $this->postJson("/api/v1/spaces/{$this->space->id}/refresh-suggestions/generate");

        $response->assertOk()
            ->assertJsonStructure(['data', 'meta' => ['total']]);
    }

    public function test_unauthenticated_access_denied(): void
    {
        $response = $this->getJson("/api/v1/spaces/{$this->space->id}/refresh-suggestions");

        $response->assertUnauthorized();
    }

    private function adminUser(): User
    {
        $user = User::factory()->create();
        $role = Role::create([
            'name' => 'Admin',
            'slug' => 'admin-'.uniqid(),
            'permissions' => ['*'],
            'is_system' => false,
        ]);
        $user->roles()->attach($role->id, ['space_id' => null]);
        $user->load('roles');

        return $user;
    }
}
