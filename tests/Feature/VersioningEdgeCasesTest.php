<?php

namespace Tests\Feature;

use App\Models\Content;
use App\Models\ContentDraft;
use App\Models\ContentType;
use App\Models\ContentVersion;
use App\Models\ScheduledPublish;
use App\Models\Space;
use App\Models\User;
use App\Services\Versioning\DiffEngine;
use App\Services\Versioning\VersioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class VersioningEdgeCasesTest extends TestCase
{
    use RefreshDatabase;

    private VersioningService $service;

    private Space $space;

    private ContentType $type;

    private Content $content;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new VersioningService(new DiffEngine);

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
        $this->user = User::factory()->create(['role' => 'editor', 'space_id' => $this->space->id]);
    }

    // ─── Rollback to deleted/orphaned version ─────────────────────────────────

    public function test_rollback_to_archived_version_creates_draft(): void
    {
        $v1 = $this->makeVersion(1, 'archived', 'V1 Content', 'V1 body');
        $v2 = $this->makeVersion(2, 'published', 'V2 Content', 'V2 body');
        $this->content->update(['current_version_id' => $v2->id]);

        // Rollback creates a draft — editor must explicitly publish (two-step safety)
        $newVersion = $this->service->rollback($this->content, $v1);

        $this->assertEquals('V1 Content', $newVersion->title);
        $this->assertEquals('draft', $newVersion->status); // draft, not published
        $this->assertEquals(3, $newVersion->version_number);
    }

    public function test_rollback_to_version_with_null_label_does_not_include_label_in_reason(): void
    {
        Event::fake();
        $v1 = $this->makeVersion(1, 'published', 'V1');
        $this->assertNull($v1->label);
        $this->content->update(['current_version_id' => $v1->id]);

        $newVersion = $this->service->rollback($this->content, $v1);

        // Should contain rollback info but not crash with null label
        $this->assertStringContainsString('Rollback', $newVersion->change_reason ?? '');
    }

    // ─── Concurrent drafts ────────────────────────────────────────────────────

    public function test_multiple_users_can_autosave_concurrently(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $user3 = User::factory()->create();

        $this->service->autoSave($this->content, $user1, ['title' => 'User 1 draft', 'body' => 'Body 1']);
        $this->service->autoSave($this->content, $user2, ['title' => 'User 2 draft', 'body' => 'Body 2']);
        $this->service->autoSave($this->content, $user3, ['title' => 'User 3 draft', 'body' => 'Body 3']);

        $this->assertEquals(3, ContentDraft::where('content_id', $this->content->id)->count());
    }

    public function test_create_draft_replaces_existing_draft_version_id(): void
    {
        $v1 = $this->makeVersion(1, 'published', 'V1');
        $this->content->update(['current_version_id' => $v1->id]);

        $draft1 = $this->service->createDraft($this->content);
        $this->content->refresh();
        $this->assertEquals($draft1->id, $this->content->draft_version_id);

        // Create another draft (replaces the pointer)
        $draft2 = $this->service->createDraft($this->content);
        $this->content->refresh();
        $this->assertEquals($draft2->id, $this->content->draft_version_id);
    }

    public function test_save_version_only_clears_current_users_autosave(): void
    {
        // Fix 7: saveVersion should only clear the CALLING user's draft, not other users' drafts
        $user2 = User::factory()->create(['role' => 'editor', 'space_id' => $this->space->id]);

        ContentDraft::create([
            'content_id' => $this->content->id,
            'user_id' => $this->user->id,
            'title' => 'User 1 Draft',
            'body' => 'Body',
            'last_saved_at' => now(),
        ]);
        ContentDraft::create([
            'content_id' => $this->content->id,
            'user_id' => $user2->id,
            'title' => 'User 2 Draft',
            'body' => 'Body',
            'last_saved_at' => now(),
        ]);

        $draft = $this->makeVersion(1, 'draft', 'Title');

        // Call saveVersion via the label API endpoint (so Auth::id() is set to $this->user)
        $this->actingAs($this->user)
            ->postJson("/api/v1/content/{$this->content->id}/versions/{$draft->id}/label", [
                'label' => 'v1.0',
            ])
            ->assertOk();

        // Only the calling user's draft should be cleared
        $this->assertEquals(0, ContentDraft::where('content_id', $this->content->id)
            ->where('user_id', $this->user->id)
            ->count());

        // Other users' drafts must remain untouched
        $this->assertEquals(1, ContentDraft::where('content_id', $this->content->id)
            ->where('user_id', $user2->id)
            ->count());
    }

    // ─── Scheduling edge cases ────────────────────────────────────────────────

    public function test_schedule_in_the_past_fails_via_api(): void
    {
        Queue::fake();
        $version = $this->makeVersion(1, 'draft', 'Title');

        $this->actingAs($this->user)
            ->postJson("/api/v1/content/{$this->content->id}/versions/{$version->id}/schedule", [
                'publish_at' => now()->subMinutes(5)->toIso8601String(),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['publish_at']);
    }

    public function test_scheduling_cancels_previous_pending_schedule(): void
    {
        Queue::fake();

        $v1 = $this->makeVersion(1, 'draft', 'V1');
        $v2 = $this->makeVersion(2, 'draft', 'V2');

        // Schedule V1 first
        $schedule1 = $this->service->schedule($this->content, $v1, now()->addHour());
        $this->assertEquals('pending', $schedule1->status);

        // Schedule V2 — should cancel V1
        $this->service->schedule($this->content, $v2, now()->addHours(2));

        $schedule1->refresh();
        $this->assertEquals('cancelled', $schedule1->status);
    }

    public function test_multiple_reschedules_only_one_pending_at_a_time(): void
    {
        Queue::fake();

        $v1 = $this->makeVersion(1, 'draft', 'V1');
        $v2 = $this->makeVersion(2, 'draft', 'V2');
        $v3 = $this->makeVersion(3, 'draft', 'V3');

        $this->service->schedule($this->content, $v1, now()->addHour());
        $this->service->schedule($this->content, $v2, now()->addHours(2));
        $this->service->schedule($this->content, $v3, now()->addHours(3));

        $pendingCount = ScheduledPublish::where('content_id', $this->content->id)
            ->where('status', 'pending')
            ->count();

        $this->assertEquals(1, $pendingCount);
    }

    // ─── Diff edge cases ─────────────────────────────────────────────────────

    public function test_diff_same_version_with_itself_has_no_changes(): void
    {
        $version = $this->makeVersion(1, 'published', 'Same', 'Same body');

        $diff = $this->service->diff($version, $version);

        $this->assertFalse($diff->hasChanges());
    }

    public function test_diff_with_null_seo_data_on_both_sides(): void
    {
        $v1 = $this->makeVersion(1, 'published', 'Title', 'Body');
        $v2 = $this->makeVersion(2, 'draft', 'Title', 'Body');
        // Both have null seo_data

        $diff = $this->service->diff($v1, $v2);

        $this->assertEmpty($diff->seoDiffs);
    }

    public function test_diff_excerpt_change_detected(): void
    {
        $v1 = $this->makeVersion(1, 'published', 'Title', 'Body');
        $v1->update(['excerpt' => 'Old excerpt']);

        $v2 = $this->makeVersion(2, 'draft', 'Title', 'Body');
        $v2->update(['excerpt' => 'New excerpt']);

        $diff = $this->service->diff($v1, $v2);

        $this->assertTrue($diff->hasChanges());
        $this->assertArrayHasKey('excerpt', $diff->fieldDiffs);
    }

    public function test_diff_null_to_value_excerpt_detected(): void
    {
        $v1 = $this->makeVersion(1, 'published', 'Title', 'Body');
        // v1 has null excerpt

        $v2 = $this->makeVersion(2, 'draft', 'Title', 'Body');
        $v2->update(['excerpt' => 'Now has an excerpt']);

        $diff = $this->service->diff($v1, $v2);

        $this->assertTrue($diff->hasChanges());
    }

    // ─── Publish edge cases ───────────────────────────────────────────────────

    public function test_publish_when_no_prior_published_version(): void
    {
        Event::fake();
        $version = $this->makeVersion(1, 'draft', 'First ever');

        // Should not throw, no prior published version to archive
        $this->service->publish($this->content, $version);

        $version->refresh();
        $this->assertEquals('published', $version->status);
    }

    public function test_publish_multiple_times_only_one_published(): void
    {
        Event::fake();

        $v1 = $this->makeVersion(1, 'draft', 'V1');
        $v2 = $this->makeVersion(2, 'draft', 'V2');
        $v3 = $this->makeVersion(3, 'draft', 'V3');

        $this->service->publish($this->content, $v1);
        $this->service->publish($this->content, $v2);
        $this->service->publish($this->content, $v3);

        $publishedCount = ContentVersion::where('content_id', $this->content->id)
            ->where('status', 'published')
            ->count();

        $this->assertEquals(1, $publishedCount);
    }

    // ─── API: cannot edit non-draft version ──────────────────────────────────

    public function test_cannot_update_archived_version(): void
    {
        $archived = $this->makeVersion(1, 'archived', 'Archived');

        $this->actingAs($this->user)
            ->patchJson("/api/v1/content/{$this->content->id}/versions/{$archived->id}", [
                'title' => 'Attempted Edit',
            ])
            ->assertStatus(422);
    }

    public function test_cannot_update_scheduled_version(): void
    {
        $scheduled = $this->makeVersion(1, 'scheduled', 'Scheduled');
        $scheduled->update(['scheduled_at' => now()->addDay()]);

        $this->actingAs($this->user)
            ->patchJson("/api/v1/content/{$this->content->id}/versions/{$scheduled->id}", [
                'title' => 'Attempted Edit',
            ])
            ->assertStatus(422);
    }

    // ─── Content hash uniqueness ──────────────────────────────────────────────

    public function test_identical_versions_have_same_hash(): void
    {
        $v1 = $this->makeVersion(1, 'published', 'Title', 'Body');
        $v2 = $this->makeVersion(2, 'draft', 'Title', 'Body');

        $this->assertEquals($v1->computeHash(), $v2->computeHash());
    }

    public function test_hash_changes_when_seo_data_changes(): void
    {
        $v1 = $this->makeVersion(1, 'published', 'Title', 'Body');
        $v1->update(['seo_data' => ['meta_description' => 'Desc A']]);

        $v2 = $this->makeVersion(2, 'draft', 'Title', 'Body');
        $v2->update(['seo_data' => ['meta_description' => 'Desc B']]);

        $this->assertNotEquals($v1->computeHash(), $v2->computeHash());
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
