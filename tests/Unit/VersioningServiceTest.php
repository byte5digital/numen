<?php

namespace Tests\Unit;

use App\Events\Content\ContentPublished;
use App\Models\Content;
use App\Models\ContentDraft;
use App\Models\ContentType;
use App\Models\ContentVersion;
use App\Models\ScheduledPublish;
use App\Models\Space;
use App\Models\User;
use App\Services\Versioning\DiffEngine;
use App\Services\Versioning\VersionDiff;
use App\Services\Versioning\VersioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class VersioningServiceTest extends TestCase
{
    use RefreshDatabase;

    private VersioningService $service;

    private Content $content;

    private Space $space;

    private ContentType $type;

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

        $this->user = User::factory()->create();
    }

    // ─── createDraft ─────────────────────────────────────────────────────────

    public function test_create_draft_with_no_existing_version(): void
    {
        $draft = $this->service->createDraft($this->content);

        $this->assertInstanceOf(ContentVersion::class, $draft);
        $this->assertEquals(1, $draft->version_number);
        $this->assertEquals('draft', $draft->status);
        $this->assertEquals('', $draft->title);
        $this->assertEquals('markdown', $draft->body_format);
        $this->assertNull($draft->parent_version_id);
    }

    public function test_create_draft_copies_fields_from_current_version(): void
    {
        $v1 = $this->makeVersion(1, 'published', 'Original Title', 'Hello World');
        $this->content->update(['current_version_id' => $v1->id]);

        $draft = $this->service->createDraft($this->content);

        $this->assertEquals('Original Title', $draft->title);
        $this->assertEquals('Hello World', $draft->body);
        $this->assertEquals($v1->id, $draft->parent_version_id);
    }

    public function test_create_draft_increments_version_number(): void
    {
        $v1 = $this->makeVersion(1, 'published', 'V1');
        $v2 = $this->makeVersion(2, 'draft', 'V2');
        $this->content->update(['current_version_id' => $v1->id]);

        $draft = $this->service->createDraft($this->content);

        $this->assertEquals(3, $draft->version_number);
    }

    public function test_create_draft_sets_draft_version_id_on_content(): void
    {
        $v1 = $this->makeVersion(1, 'published', 'V1');
        $this->content->update(['current_version_id' => $v1->id]);

        $draft = $this->service->createDraft($this->content);

        $this->content->refresh();
        $this->assertEquals($draft->id, $this->content->draft_version_id);
    }

    public function test_create_draft_clones_blocks_from_base_version(): void
    {
        $v1 = $this->makeVersion(1, 'published', 'V1');
        $v1->blocks()->createMany([
            ['type' => 'text', 'sort_order' => 0, 'data' => ['text' => 'Hello']],
            ['type' => 'image', 'sort_order' => 1, 'data' => ['url' => 'http://example.com/img.jpg']],
        ]);
        $this->content->update(['current_version_id' => $v1->id]);

        $draft = $this->service->createDraft($this->content);

        $this->assertCount(2, $draft->blocks);
        $this->assertEquals('text', $draft->blocks[0]->type);
        $this->assertEquals('image', $draft->blocks[1]->type);
    }

    public function test_create_draft_uses_authenticated_user_as_author(): void
    {
        Event::fake();
        Auth::login($this->user);

        $draft = $this->service->createDraft($this->content);

        $this->assertEquals($this->user->id, $draft->author_id);
        $this->assertEquals('human', $draft->author_type);
    }

    public function test_create_draft_stores_empty_author_id_when_not_authenticated(): void
    {
        // Fix 9: 'system' fallback removed — unauthenticated callers store empty string.
        // API endpoints are always authenticated; direct service calls (e.g. jobs) must
        // set author context explicitly.
        Auth::logout();

        $draft = $this->service->createDraft($this->content);

        $this->assertEmpty($draft->author_id);
    }

    // ─── branch ──────────────────────────────────────────────────────────────

    public function test_branch_creates_draft_from_specified_version(): void
    {
        $v1 = $this->makeVersion(1, 'published', 'V1', 'V1 body');
        $v2 = $this->makeVersion(2, 'published', 'V2', 'V2 body');
        $this->content->update(['current_version_id' => $v2->id]);

        $branch = $this->service->branch($this->content, $v1);

        $this->assertEquals('V1 body', $branch->body);
        $this->assertEquals($v1->id, $branch->parent_version_id);
        $this->assertStringContainsString('v1', $branch->change_reason ?? '');
    }

    public function test_branch_sets_label_when_provided(): void
    {
        $v1 = $this->makeVersion(1, 'published', 'V1');
        $this->content->update(['current_version_id' => $v1->id]);

        $branch = $this->service->branch($this->content, $v1, 'experimental-feature');

        $this->assertEquals('experimental-feature', $branch->label);
    }

    public function test_branch_without_label_has_null_label(): void
    {
        $v1 = $this->makeVersion(1, 'published', 'V1');
        $this->content->update(['current_version_id' => $v1->id]);

        $branch = $this->service->branch($this->content, $v1);

        $this->assertNull($branch->label);
    }

    // ─── autoSave ────────────────────────────────────────────────────────────

    public function test_auto_save_creates_draft_record(): void
    {
        $draft = $this->service->autoSave($this->content, $this->user, [
            'title' => 'Draft Title',
            'body' => 'Draft body content',
        ]);

        $this->assertInstanceOf(ContentDraft::class, $draft);
        $this->assertEquals('Draft Title', $draft->title);
        $this->assertEquals($this->content->id, $draft->content_id);
        $this->assertEquals($this->user->id, $draft->user_id);
    }

    public function test_auto_save_updates_existing_draft(): void
    {
        $this->service->autoSave($this->content, $this->user, ['title' => 'First save', 'body' => 'Body']);
        $this->service->autoSave($this->content, $this->user, ['title' => 'Second save', 'body' => 'Body']);

        $this->assertEquals(1, ContentDraft::count());
        $draft = ContentDraft::first();
        $this->assertEquals('Second save', $draft->title);
    }

    public function test_auto_save_increments_save_count(): void
    {
        $this->service->autoSave($this->content, $this->user, ['title' => 'T', 'body' => 'B']);
        $this->service->autoSave($this->content, $this->user, ['title' => 'T', 'body' => 'B']);
        $this->service->autoSave($this->content, $this->user, ['title' => 'T', 'body' => 'B']);

        $draft = ContentDraft::where('content_id', $this->content->id)
            ->where('user_id', $this->user->id)
            ->first();

        $this->assertEquals(3, $draft->save_count);
    }

    public function test_auto_save_updates_last_saved_at(): void
    {
        $before = now()->subSecond();

        $draft = $this->service->autoSave($this->content, $this->user, ['title' => 'T', 'body' => 'B']);

        $this->assertTrue($draft->last_saved_at->isAfter($before));
    }

    public function test_auto_save_is_scoped_per_user(): void
    {
        $otherUser = User::factory()->create();

        $this->service->autoSave($this->content, $this->user, ['title' => 'User 1', 'body' => 'B']);
        $this->service->autoSave($this->content, $otherUser, ['title' => 'User 2', 'body' => 'B']);

        $this->assertEquals(2, ContentDraft::where('content_id', $this->content->id)->count());
    }

    // ─── saveVersion ─────────────────────────────────────────────────────────

    public function test_save_version_sets_label_and_hash(): void
    {
        $draft = $this->makeVersion(1, 'draft', 'My Title', 'My Body');

        $saved = $this->service->saveVersion($draft, 'v1.0 Launch Copy', 'Initial release');

        $this->assertEquals('v1.0 Launch Copy', $saved->label);
        $this->assertEquals('Initial release', $saved->change_reason);
        $this->assertNotNull($saved->content_hash);
    }

    public function test_save_version_computes_deterministic_hash(): void
    {
        $draft = $this->makeVersion(1, 'draft', 'Title', 'Body content');

        $this->service->saveVersion($draft, 'v1.0');

        $draft->refresh();
        $expectedHash = $draft->computeHash();
        $this->assertEquals($expectedHash, $draft->content_hash);
    }

    public function test_save_version_clears_auto_save_buffer_for_authenticated_user(): void
    {
        // Fix 7: saveVersion only clears the CALLING user's draft, not all users' drafts.
        ContentDraft::create([
            'content_id' => $this->content->id,
            'user_id' => $this->user->id,
            'title' => 'Draft',
            'body' => 'Draft body',
            'last_saved_at' => now(),
        ]);

        $draft = $this->makeVersion(1, 'draft', 'Title');

        // Simulate authenticated user context
        Auth::login($this->user);
        $this->service->saveVersion($draft, 'v1.0');

        $this->assertEquals(0, ContentDraft::where('content_id', $this->content->id)
            ->where('user_id', $this->user->id)
            ->count());
    }

    // ─── publish ─────────────────────────────────────────────────────────────

    public function test_publish_marks_version_as_published(): void
    {
        Event::fake();
        $version = $this->makeVersion(1, 'draft', 'Title');

        $this->service->publish($this->content, $version);

        $version->refresh();
        $this->assertEquals('published', $version->status);
    }

    public function test_publish_updates_content_current_version(): void
    {
        Event::fake();
        $version = $this->makeVersion(1, 'draft', 'Title');

        $this->service->publish($this->content, $version);

        $this->content->refresh();
        $this->assertEquals($version->id, $this->content->current_version_id);
        $this->assertEquals('published', $this->content->status);
        $this->assertNotNull($this->content->published_at);
    }

    public function test_publish_archives_previously_published_version(): void
    {
        Event::fake();
        $v1 = $this->makeVersion(1, 'published', 'V1');
        $this->content->update(['current_version_id' => $v1->id]);

        $v2 = $this->makeVersion(2, 'draft', 'V2');
        $this->service->publish($this->content, $v2);

        $v1->refresh();
        $this->assertEquals('archived', $v1->status);
    }

    public function test_publish_clears_draft_version_id_when_publishing_draft(): void
    {
        Event::fake();
        $draft = $this->makeVersion(1, 'draft', 'Title');
        $this->content->update(['draft_version_id' => $draft->id]);

        $this->service->publish($this->content, $draft);

        $this->content->refresh();
        $this->assertNull($this->content->draft_version_id);
    }

    public function test_publish_fires_content_published_event(): void
    {
        Event::fake();
        $version = $this->makeVersion(1, 'draft', 'Title');

        $this->service->publish($this->content, $version);

        Event::assertDispatched(ContentPublished::class, function ($event) {
            return $event->content->id === $this->content->id;
        });
    }

    // ─── schedule ────────────────────────────────────────────────────────────

    public function test_schedule_creates_scheduled_publish_record(): void
    {
        Queue::fake();
        $version = $this->makeVersion(1, 'draft', 'Title');
        $publishAt = now()->addHour();

        $schedule = $this->service->schedule($this->content, $version, $publishAt, 'Holiday sale');

        // Refresh to pick up DB defaults (status defaults to 'pending' at DB level)
        $schedule->refresh();
        $this->assertInstanceOf(ScheduledPublish::class, $schedule);
        $this->assertEquals($this->content->id, $schedule->content_id);
        $this->assertEquals($version->id, $schedule->version_id);
        $this->assertEquals('pending', $schedule->status);
        $this->assertEquals('Holiday sale', $schedule->notes);
    }

    public function test_schedule_sets_version_and_content_status(): void
    {
        Queue::fake();
        $version = $this->makeVersion(1, 'draft', 'Title');
        $publishAt = now()->addDay();

        $this->service->schedule($this->content, $version, $publishAt);

        $version->refresh();
        $this->content->refresh();

        $this->assertEquals('scheduled', $version->status);
        $this->assertEquals('scheduled', $this->content->status);
        $this->assertNotNull($this->content->scheduled_publish_at);
    }

    public function test_schedule_cancels_existing_pending_schedules(): void
    {
        Queue::fake();
        $version1 = $this->makeVersion(1, 'draft', 'V1');
        $version2 = $this->makeVersion(2, 'draft', 'V2');

        ScheduledPublish::create([
            'content_id' => $this->content->id,
            'version_id' => $version1->id,
            'scheduled_by' => $this->user->id,
            'publish_at' => now()->addHour(),
            'status' => 'pending',
        ]);

        $this->service->schedule($this->content, $version2, now()->addHours(2));

        $cancelled = ScheduledPublish::where('version_id', $version1->id)->first();
        $this->assertEquals('cancelled', $cancelled->status);
    }

    public function test_schedule_dispatches_delayed_job(): void
    {
        Queue::fake();
        $version = $this->makeVersion(1, 'draft', 'Title');
        $publishAt = now()->addHours(3);

        $this->service->schedule($this->content, $version, $publishAt);

        Queue::assertPushed(\App\Jobs\PublishScheduledContent::class);
    }

    // ─── rollback ────────────────────────────────────────────────────────────

    public function test_rollback_creates_new_version_from_historical(): void
    {
        Event::fake();
        $v1 = $this->makeVersion(1, 'published', 'V1', 'V1 body');
        $this->content->update(['current_version_id' => $v1->id]);

        $newVersion = $this->service->rollback($this->content, $v1);

        $this->assertEquals('V1', $newVersion->title);
        $this->assertEquals('V1 body', $newVersion->body);
        $this->assertEquals($v1->id, $newVersion->parent_version_id);
    }

    public function test_rollback_change_reason_mentions_source_version(): void
    {
        Event::fake();
        $v1 = $this->makeVersion(1, 'published', 'V1');
        $this->content->update(['current_version_id' => $v1->id]);

        $newVersion = $this->service->rollback($this->content, $v1);

        $this->assertStringContainsString('Rollback', $newVersion->change_reason ?? '');
        $this->assertStringContainsString('v1', $newVersion->change_reason ?? '');
    }

    public function test_rollback_creates_draft_for_review(): void
    {
        // Fix 5: rollback is now two-step — creates a draft for editor review.
        // The editor must explicitly call publish() after reviewing.
        Event::fake();
        $v1 = $this->makeVersion(1, 'published', 'V1');
        $this->content->update(['current_version_id' => $v1->id]);

        $newVersion = $this->service->rollback($this->content, $v1);

        // New version is a DRAFT, not auto-published
        $this->assertEquals('draft', $newVersion->status);
        // Content's draft_version_id points to the new rollback draft
        $this->content->refresh();
        $this->assertEquals($newVersion->id, $this->content->draft_version_id);
        // The live version is unchanged
        $this->assertEquals($v1->id, $this->content->current_version_id);
    }

    public function test_rollback_clones_blocks(): void
    {
        Event::fake();
        $v1 = $this->makeVersion(1, 'published', 'V1');
        $v1->blocks()->create(['type' => 'text', 'sort_order' => 0, 'data' => ['text' => 'Hello']]);
        $this->content->update(['current_version_id' => $v1->id]);

        $newVersion = $this->service->rollback($this->content, $v1);

        $this->assertCount(1, $newVersion->blocks);
        $this->assertEquals('text', $newVersion->blocks->first()->type);
    }

    public function test_rollback_includes_label_in_change_reason(): void
    {
        Event::fake();
        $v1 = $this->makeVersion(1, 'published', 'V1');
        $v1->update(['label' => 'Launch Version']);
        $this->content->update(['current_version_id' => $v1->id]);

        $newVersion = $this->service->rollback($this->content, $v1);

        $this->assertStringContainsString('Launch Version', $newVersion->change_reason ?? '');
    }

    // ─── diff ────────────────────────────────────────────────────────────────

    public function test_diff_detects_title_change(): void
    {
        $v1 = $this->makeVersion(1, 'published', 'Original Title', 'body');
        $v2 = $this->makeVersion(2, 'draft', 'Updated Title', 'body');

        $diff = $this->service->diff($v1, $v2);

        $this->assertInstanceOf(VersionDiff::class, $diff);
        $this->assertTrue($diff->hasChanges());
        $this->assertArrayHasKey('title', $diff->fieldDiffs);
        $this->assertEquals('Original Title', $diff->fieldDiffs['title']['old']);
        $this->assertEquals('Updated Title', $diff->fieldDiffs['title']['new']);
    }

    public function test_diff_detects_body_change(): void
    {
        $v1 = $this->makeVersion(1, 'published', 'Title', "Line 1\nLine 2\nLine 3");
        $v2 = $this->makeVersion(2, 'draft', 'Title', "Line 1\nLine Modified\nLine 3");

        $diff = $this->service->diff($v1, $v2);

        $this->assertTrue($diff->hasChanges());
        $this->assertArrayHasKey('body', $diff->fieldDiffs);
    }

    public function test_diff_with_no_changes_returns_empty_diffs(): void
    {
        $v1 = $this->makeVersion(1, 'published', 'Same Title', 'Same body');
        $v2 = $this->makeVersion(2, 'draft', 'Same Title', 'Same body');

        $diff = $this->service->diff($v1, $v2);

        $this->assertFalse($diff->hasChanges());
        $this->assertEquals('No changes', $diff->summary());
    }

    public function test_diff_detects_seo_data_changes(): void
    {
        $v1 = $this->makeVersion(1, 'published', 'Title', 'body');
        $v1->update(['seo_data' => ['meta_description' => 'Old description', 'title' => 'Old SEO']]);

        $v2 = $this->makeVersion(2, 'draft', 'Title', 'body');
        $v2->update(['seo_data' => ['meta_description' => 'New description', 'title' => 'Old SEO']]);

        $diff = $this->service->diff($v1, $v2);

        $this->assertTrue($diff->hasChanges());
        $this->assertArrayHasKey('meta_description', $diff->seoDiffs);
        $this->assertArrayNotHasKey('title', $diff->seoDiffs);
    }

    public function test_diff_summary_describes_changes(): void
    {
        $v1 = $this->makeVersion(1, 'published', 'Old Title', 'body');
        $v2 = $this->makeVersion(2, 'draft', 'New Title', 'body');

        $diff = $this->service->diff($v1, $v2);

        $this->assertStringContainsString('title changed', $diff->summary());
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
