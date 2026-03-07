<?php

namespace Tests\Feature;

use App\Models\Content;
use App\Models\ContentDraft;
use App\Models\ContentType;
use App\Models\Space;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AutoSaveApiTest extends TestCase
{
    use RefreshDatabase;

    private Space $space;

    private ContentType $type;

    private Content $content;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->space = Space::create(['name' => 'Test Space', 'slug' => 'test-space']);
        $this->type = ContentType::create([
            'space_id' => $this->space->id,
            'name' => 'Blog',
            'slug' => 'blog',
            'schema' => [],
        ]);
        $this->content = Content::create([
            'space_id' => $this->space->id,
            'content_type_id' => $this->type->id,
            'slug' => 'test-post',
            'status' => 'draft',
            'locale' => 'en',
        ]);
        $this->user = User::factory()->create();
    }

    // ─── Authentication guard ─────────────────────────────────────────────────

    public function test_autosave_endpoints_require_authentication(): void
    {
        $this->postJson("/api/v1/content/{$this->content->id}/autosave", [])->assertUnauthorized();
        $this->getJson("/api/v1/content/{$this->content->id}/autosave")->assertUnauthorized();
        $this->deleteJson("/api/v1/content/{$this->content->id}/autosave")->assertUnauthorized();
    }

    // ─── save (POST) ─────────────────────────────────────────────────────────

    public function test_autosave_creates_draft(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/content/{$this->content->id}/autosave", [
                'title' => 'Working Title',
                'body' => 'Draft body text',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.title', 'Working Title')
            ->assertJsonPath('data.content_id', $this->content->id)
            ->assertJsonPath('data.user_id', $this->user->id);
    }

    public function test_autosave_updates_existing_draft(): void
    {
        $this->actingAs($this->user)
            ->postJson("/api/v1/content/{$this->content->id}/autosave", [
                'title' => 'First Save',
                'body' => 'Body',
            ]);

        $this->actingAs($this->user)
            ->postJson("/api/v1/content/{$this->content->id}/autosave", [
                'title' => 'Second Save',
                'body' => 'Updated body',
            ]);

        $this->assertEquals(1, ContentDraft::where('content_id', $this->content->id)->count());
        $draft = ContentDraft::where('content_id', $this->content->id)->first();
        $this->assertEquals('Second Save', $draft->title);
    }

    public function test_autosave_validates_body_format(): void
    {
        $this->actingAs($this->user)
            ->postJson("/api/v1/content/{$this->content->id}/autosave", [
                'title' => 'Title',
                'body' => 'Body',
                'body_format' => 'invalid',
            ])
            ->assertUnprocessable();
    }

    public function test_autosave_accepts_blocks_snapshot(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/content/{$this->content->id}/autosave", [
                'title' => 'Title',
                'body' => 'Body',
                'blocks_snapshot' => [
                    ['type' => 'text', 'sort_order' => 0, 'data' => ['text' => 'Hello']],
                ],
            ]);

        $response->assertOk();
        $draft = ContentDraft::where('content_id', $this->content->id)->first();
        $this->assertNotNull($draft->blocks_snapshot);
        $this->assertCount(1, $draft->blocks_snapshot);
    }

    public function test_autosave_validates_base_version_id_exists(): void
    {
        $this->actingAs($this->user)
            ->postJson("/api/v1/content/{$this->content->id}/autosave", [
                'title' => 'Title',
                'body' => 'Body',
                'base_version_id' => 'nonexistent-version-id',
            ])
            ->assertUnprocessable();
    }

    public function test_autosave_accepts_valid_base_version_id(): void
    {
        $version = $this->content->versions()->create([
            'version_number' => 1,
            'title' => 'V1',
            'body' => 'V1 body',
            'body_format' => 'markdown',
            'author_type' => 'human',
            'author_id' => $this->user->id,
            'status' => 'published',
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/content/{$this->content->id}/autosave", [
                'title' => 'Title',
                'body' => 'Body',
                'base_version_id' => $version->id,
            ]);

        $response->assertOk();
        $draft = ContentDraft::where('content_id', $this->content->id)->first();
        $this->assertEquals($version->id, $draft->base_version_id);
    }

    public function test_autosave_is_scoped_per_user(): void
    {
        $otherUser = User::factory()->create();

        $this->actingAs($this->user)
            ->postJson("/api/v1/content/{$this->content->id}/autosave", [
                'title' => 'User 1 draft',
                'body' => 'Body',
            ]);

        $this->actingAs($otherUser)
            ->postJson("/api/v1/content/{$this->content->id}/autosave", [
                'title' => 'User 2 draft',
                'body' => 'Body',
            ]);

        $this->assertEquals(2, ContentDraft::where('content_id', $this->content->id)->count());
    }

    // ─── show (GET) ──────────────────────────────────────────────────────────

    public function test_show_returns_null_when_no_autosave(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/content/{$this->content->id}/autosave");

        $response->assertOk()
            ->assertJsonPath('data', null);
    }

    public function test_show_returns_existing_autosave(): void
    {
        ContentDraft::create([
            'content_id' => $this->content->id,
            'user_id' => $this->user->id,
            'title' => 'Saved Draft',
            'body' => 'Draft body',
            'last_saved_at' => now(),
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/content/{$this->content->id}/autosave");

        $response->assertOk()
            ->assertJsonPath('data.title', 'Saved Draft');
    }

    public function test_show_only_returns_current_users_autosave(): void
    {
        $otherUser = User::factory()->create();

        ContentDraft::create([
            'content_id' => $this->content->id,
            'user_id' => $otherUser->id,
            'title' => 'Other User Draft',
            'body' => 'Other body',
            'last_saved_at' => now(),
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/content/{$this->content->id}/autosave");

        $response->assertOk()
            ->assertJsonPath('data', null);
    }

    // ─── discard (DELETE) ────────────────────────────────────────────────────

    public function test_discard_removes_autosave_draft(): void
    {
        ContentDraft::create([
            'content_id' => $this->content->id,
            'user_id' => $this->user->id,
            'title' => 'To Delete',
            'body' => 'Body',
            'last_saved_at' => now(),
        ]);

        $this->actingAs($this->user)
            ->deleteJson("/api/v1/content/{$this->content->id}/autosave")
            ->assertOk()
            ->assertJsonPath('message', 'Auto-save discarded');

        $this->assertEquals(0, ContentDraft::where('content_id', $this->content->id)->count());
    }

    public function test_discard_only_removes_current_users_draft(): void
    {
        $otherUser = User::factory()->create();

        ContentDraft::create([
            'content_id' => $this->content->id,
            'user_id' => $otherUser->id,
            'title' => 'Other User',
            'body' => 'Body',
            'last_saved_at' => now(),
        ]);

        $this->actingAs($this->user)
            ->deleteJson("/api/v1/content/{$this->content->id}/autosave")
            ->assertOk();

        $this->assertEquals(1, ContentDraft::where('user_id', $otherUser->id)->count());
    }

    public function test_discard_is_idempotent_when_no_draft(): void
    {
        $this->actingAs($this->user)
            ->deleteJson("/api/v1/content/{$this->content->id}/autosave")
            ->assertOk();
    }
}
