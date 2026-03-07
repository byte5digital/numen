<?php

namespace Tests\Feature;

use App\Events\Content\ContentPublished;
use App\Models\Content;
use App\Models\ContentType;
use App\Models\ContentVersion;
use App\Models\ScheduledPublish;
use App\Models\Space;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class VersionApiTest extends TestCase
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

    public function test_unauthenticated_user_cannot_access_version_endpoints(): void
    {
        $this->getJson("/api/v1/content/{$this->content->id}/versions")
            ->assertUnauthorized();
    }

    // ─── index ────────────────────────────────────────────────────────────────

    public function test_versions_index_returns_paginated_list(): void
    {
        $this->makeVersion(1, 'published', 'V1');
        $this->makeVersion(2, 'draft', 'V2');

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/content/{$this->content->id}/versions");

        $response->assertOk()
            ->assertJsonPath('total', 2)
            ->assertJsonCount(2, 'data');
    }

    public function test_versions_index_includes_version_number_and_status(): void
    {
        $this->makeVersion(1, 'published', 'V1');

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/content/{$this->content->id}/versions");

        $response->assertOk()
            ->assertJsonPath('data.0.version_number', 1)
            ->assertJsonPath('data.0.status', 'published');
    }

    // ─── show ─────────────────────────────────────────────────────────────────

    public function test_show_returns_version_with_blocks(): void
    {
        $version = $this->makeVersion(1, 'published', 'V1', 'Body text');
        $version->blocks()->create(['type' => 'text', 'sort_order' => 0, 'data' => ['text' => 'Hello']]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/content/{$this->content->id}/versions/{$version->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $version->id)
            ->assertJsonPath('data.title', 'V1')
            ->assertJsonCount(1, 'data.blocks');
    }

    public function test_show_returns_404_for_version_from_different_content(): void
    {
        $otherContent = Content::create([
            'space_id' => $this->space->id,
            'content_type_id' => $this->type->id,
            'slug' => 'other-post',
            'status' => 'draft',
            'locale' => 'en',
        ]);
        $otherVersion = $otherContent->versions()->create([
            'version_number' => 1,
            'title' => 'Other',
            'body' => 'Other body',
            'body_format' => 'markdown',
            'author_type' => 'human',
            'author_id' => $this->user->id,
            'status' => 'draft',
        ]);

        $this->actingAs($this->user)
            ->getJson("/api/v1/content/{$this->content->id}/versions/{$otherVersion->id}")
            ->assertNotFound();
    }

    // ─── createDraft ─────────────────────────────────────────────────────────

    public function test_create_draft_returns_201(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/content/{$this->content->id}/versions/draft");

        $response->assertCreated()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.version_number', 1);
    }

    public function test_create_draft_branches_from_current_version(): void
    {
        $v1 = $this->makeVersion(1, 'published', 'Current Title', 'Current body');
        $this->content->update(['current_version_id' => $v1->id]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/content/{$this->content->id}/versions/draft");

        $response->assertCreated()
            ->assertJsonPath('data.title', 'Current Title')
            ->assertJsonPath('data.version_number', 2);
    }

    // ─── update ───────────────────────────────────────────────────────────────

    public function test_update_draft_version(): void
    {
        $draft = $this->makeVersion(1, 'draft', 'Original Title');

        $response = $this->actingAs($this->user)
            ->patchJson("/api/v1/content/{$this->content->id}/versions/{$draft->id}", [
                'title' => 'Updated Title',
                'body' => 'Updated body',
                'change_reason' => 'Improved content',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.title', 'Updated Title')
            ->assertJsonPath('data.change_reason', 'Improved content');
    }

    public function test_cannot_update_published_version(): void
    {
        $published = $this->makeVersion(1, 'published', 'Published Version');

        $this->actingAs($this->user)
            ->patchJson("/api/v1/content/{$this->content->id}/versions/{$published->id}", [
                'title' => 'Attempted Edit',
            ])
            ->assertStatus(422);
    }

    public function test_update_validates_body_format(): void
    {
        $draft = $this->makeVersion(1, 'draft', 'Title');

        $this->actingAs($this->user)
            ->patchJson("/api/v1/content/{$this->content->id}/versions/{$draft->id}", [
                'body_format' => 'invalid_format',
            ])
            ->assertUnprocessable();
    }

    // ─── label ────────────────────────────────────────────────────────────────

    public function test_label_sets_version_label(): void
    {
        $draft = $this->makeVersion(1, 'draft', 'Title');

        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/content/{$this->content->id}/versions/{$draft->id}/label", [
                'label' => 'v1.0 Launch Copy',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.label', 'v1.0 Launch Copy');
    }

    public function test_label_requires_label_field(): void
    {
        $draft = $this->makeVersion(1, 'draft', 'Title');

        $this->actingAs($this->user)
            ->postJson("/api/v1/content/{$this->content->id}/versions/{$draft->id}/label", [])
            ->assertUnprocessable();
    }

    // ─── publish ──────────────────────────────────────────────────────────────

    public function test_publish_version_returns_ok(): void
    {
        Event::fake();
        $version = $this->makeVersion(1, 'draft', 'To Publish');

        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/content/{$this->content->id}/versions/{$version->id}/publish");

        $response->assertOk()
            ->assertJsonPath('message', 'Published');
    }

    public function test_publish_updates_content_status(): void
    {
        Event::fake();
        $version = $this->makeVersion(1, 'draft', 'To Publish');

        $this->actingAs($this->user)
            ->postJson("/api/v1/content/{$this->content->id}/versions/{$version->id}/publish");

        $this->content->refresh();
        $this->assertEquals('published', $this->content->status);
        $this->assertEquals($version->id, $this->content->current_version_id);
    }

    public function test_publish_fires_event(): void
    {
        Event::fake();
        $version = $this->makeVersion(1, 'draft', 'Title');

        $this->actingAs($this->user)
            ->postJson("/api/v1/content/{$this->content->id}/versions/{$version->id}/publish");

        Event::assertDispatched(ContentPublished::class);
    }

    // ─── schedule ────────────────────────────────────────────────────────────

    public function test_schedule_creates_scheduled_publish(): void
    {
        Queue::fake();
        $version = $this->makeVersion(1, 'draft', 'Title');
        $publishAt = now()->addHour()->toIso8601String();

        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/content/{$this->content->id}/versions/{$version->id}/schedule", [
                'publish_at' => $publishAt,
                'notes' => 'Holiday campaign',
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.notes', 'Holiday campaign');
    }

    public function test_schedule_rejects_past_date(): void
    {
        Queue::fake();
        $version = $this->makeVersion(1, 'draft', 'Title');

        $this->actingAs($this->user)
            ->postJson("/api/v1/content/{$this->content->id}/versions/{$version->id}/schedule", [
                'publish_at' => now()->subHour()->toIso8601String(),
            ])
            ->assertUnprocessable();
    }

    public function test_schedule_requires_publish_at(): void
    {
        $version = $this->makeVersion(1, 'draft', 'Title');

        $this->actingAs($this->user)
            ->postJson("/api/v1/content/{$this->content->id}/versions/{$version->id}/schedule", [])
            ->assertUnprocessable();
    }

    // ─── cancelSchedule ──────────────────────────────────────────────────────

    public function test_cancel_schedule_cancels_pending_schedules(): void
    {
        Queue::fake();
        $version = $this->makeVersion(1, 'scheduled', 'Title');
        ScheduledPublish::create([
            'content_id' => $this->content->id,
            'version_id' => $version->id,
            'scheduled_by' => $this->user->id,
            'publish_at' => now()->addHour(),
            'status' => 'pending',
        ]);

        $this->actingAs($this->user)
            ->deleteJson("/api/v1/content/{$this->content->id}/versions/{$version->id}/schedule")
            ->assertOk()
            ->assertJsonPath('message', 'Schedule cancelled');

        $this->assertEquals(0, ScheduledPublish::where('status', 'pending')->count());
    }

    public function test_cancel_schedule_resets_version_to_draft(): void
    {
        Queue::fake();
        $version = $this->makeVersion(1, 'scheduled', 'Title');
        $version->update(['scheduled_at' => now()->addHour()]);
        ScheduledPublish::create([
            'content_id' => $this->content->id,
            'version_id' => $version->id,
            'scheduled_by' => $this->user->id,
            'publish_at' => now()->addHour(),
            'status' => 'pending',
        ]);
        $this->content->update(['status' => 'scheduled', 'scheduled_publish_at' => now()->addHour()]);

        $this->actingAs($this->user)
            ->deleteJson("/api/v1/content/{$this->content->id}/versions/{$version->id}/schedule");

        $version->refresh();
        $this->content->refresh();

        $this->assertEquals('draft', $version->status);
        $this->assertNull($version->scheduled_at);
        $this->assertEquals('draft', $this->content->status);
    }

    // ─── rollback ────────────────────────────────────────────────────────────

    public function test_rollback_creates_new_published_version(): void
    {
        Event::fake();
        $v1 = $this->makeVersion(1, 'published', 'Original V1', 'Original body');
        $this->content->update(['current_version_id' => $v1->id]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/content/{$this->content->id}/versions/{$v1->id}/rollback");

        $response->assertCreated()
            ->assertJsonPath('data.title', 'Original V1')
            ->assertJsonPath('data.status', 'published');
    }

    public function test_rollback_increments_version_number(): void
    {
        Event::fake();
        $v1 = $this->makeVersion(1, 'published', 'V1');
        $v2 = $this->makeVersion(2, 'archived', 'V2');
        $this->content->update(['current_version_id' => $v2->id]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/content/{$this->content->id}/versions/{$v1->id}/rollback");

        $response->assertCreated()
            ->assertJsonPath('data.version_number', 3);
    }

    public function test_rollback_returns_404_for_wrong_content(): void
    {
        Event::fake();
        $otherContent = Content::create([
            'space_id' => $this->space->id,
            'content_type_id' => $this->type->id,
            'slug' => 'other-content',
            'status' => 'draft',
            'locale' => 'en',
        ]);
        $otherVersion = $otherContent->versions()->create([
            'version_number' => 1,
            'title' => 'Other',
            'body' => 'Other body',
            'body_format' => 'markdown',
            'author_type' => 'human',
            'author_id' => $this->user->id,
            'status' => 'published',
        ]);

        $this->actingAs($this->user)
            ->postJson("/api/v1/content/{$this->content->id}/versions/{$otherVersion->id}/rollback")
            ->assertNotFound();
    }

    // ─── branch ───────────────────────────────────────────────────────────────

    public function test_branch_creates_draft_from_version(): void
    {
        $v1 = $this->makeVersion(1, 'published', 'V1 Title', 'V1 body');
        $this->content->update(['current_version_id' => $v1->id]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/content/{$this->content->id}/versions/{$v1->id}/branch", [
                'label' => 'experimental',
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.title', 'V1 Title')
            ->assertJsonPath('data.label', 'experimental')
            ->assertJsonPath('data.status', 'draft');
    }

    public function test_branch_without_label(): void
    {
        $v1 = $this->makeVersion(1, 'published', 'V1 Title');
        $this->content->update(['current_version_id' => $v1->id]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/content/{$this->content->id}/versions/{$v1->id}/branch");

        $response->assertCreated()
            ->assertJsonPath('data.label', null);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function makeVersion(
        int $number,
        string $status,
        string $title = 'Title',
        string $body = 'Body',
    ): ContentVersion {
        return $this->content->versions()->create([
            'version_number' => $number,
            'title' => $title,
            'body' => $body,
            'body_format' => 'markdown',
            'author_type' => 'human',
            'author_id' => $this->user->id,
            'status' => $status,
        ]);
    }
}
