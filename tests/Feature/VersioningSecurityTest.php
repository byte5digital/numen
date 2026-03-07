<?php

namespace Tests\Feature;

use App\Models\Content;
use App\Models\ContentDraft;
use App\Models\ContentType;
use App\Models\ContentVersion;
use App\Models\Space;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Security regression tests for the Content Versioning & Drafts feature.
 *
 * Covers: IDOR, cross-content access, soft-lock enforcement,
 * input size limits, and cross-space isolation.
 */
class VersioningSecurityTest extends TestCase
{
    use RefreshDatabase;

    private Space $spaceA;

    private Space $spaceB;

    private ContentType $typeA;

    private ContentType $typeB;

    private Content $contentA;

    private Content $contentB;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->spaceA = Space::create(['name' => 'Space A', 'slug' => 'space-a']);
        $this->spaceB = Space::create(['name' => 'Space B', 'slug' => 'space-b']);

        $this->typeA = ContentType::create([
            'space_id' => $this->spaceA->id,
            'name' => 'Blog',
            'slug' => 'blog',
            'schema' => [],
        ]);
        $this->typeB = ContentType::create([
            'space_id' => $this->spaceB->id,
            'name' => 'Blog',
            'slug' => 'blog-b',
            'schema' => [],
        ]);

        $this->contentA = Content::create([
            'space_id' => $this->spaceA->id,
            'content_type_id' => $this->typeA->id,
            'slug' => 'content-a',
            'status' => 'draft',
            'locale' => 'en',
        ]);
        $this->contentB = Content::create([
            'space_id' => $this->spaceB->id,
            'content_type_id' => $this->typeB->id,
            'slug' => 'content-b',
            'status' => 'draft',
            'locale' => 'en',
        ]);

        // Create editor user scoped to Space A
        $this->user = User::factory()->create(['role' => 'editor', 'space_id' => $this->spaceA->id]);
    }

    // ─── IDOR: Version belongs to different Content ────────────────────────────

    /**
     * A user should NOT be able to view a version from content-B via content-A's endpoint.
     */
    public function test_cannot_access_version_from_different_content_via_show(): void
    {
        $versionB = $this->makeVersion($this->contentB, 1, 'published', 'Content B');

        $this->actingAs($this->user)
            ->getJson("/api/v1/content/{$this->contentA->id}/versions/{$versionB->id}")
            ->assertNotFound();
    }

    /**
     * A user should NOT be able to update a version via the wrong content endpoint.
     */
    public function test_cannot_update_version_via_wrong_content(): void
    {
        $versionB = $this->makeVersion($this->contentB, 1, 'draft', 'Content B Draft');

        $this->actingAs($this->user)
            ->patchJson("/api/v1/content/{$this->contentA->id}/versions/{$versionB->id}", [
                'title' => 'Hijacked Title',
            ])
            ->assertNotFound();
    }

    /**
     * A user should NOT be able to publish a version via the wrong content endpoint.
     */
    public function test_cannot_publish_version_from_different_content(): void
    {
        Event::fake();
        $versionB = $this->makeVersion($this->contentB, 1, 'draft', 'Content B Draft');

        $this->actingAs($this->user)
            ->postJson("/api/v1/content/{$this->contentA->id}/versions/{$versionB->id}/publish")
            ->assertNotFound();
    }

    /**
     * A user should NOT be able to rollback using a version from a different content item.
     */
    public function test_cannot_rollback_to_version_from_different_content(): void
    {
        Event::fake();
        $versionB = $this->makeVersion($this->contentB, 1, 'published', 'Content B');

        $this->actingAs($this->user)
            ->postJson("/api/v1/content/{$this->contentA->id}/versions/{$versionB->id}/rollback")
            ->assertNotFound();
    }

    /**
     * A user should NOT be able to branch off a version belonging to a different content.
     */
    public function test_cannot_branch_from_version_of_different_content(): void
    {
        $versionB = $this->makeVersion($this->contentB, 1, 'published', 'Content B');

        $this->actingAs($this->user)
            ->postJson("/api/v1/content/{$this->contentA->id}/versions/{$versionB->id}/branch", [
                'label' => 'hijacked-branch',
            ])
            ->assertNotFound();
    }

    /**
     * A user should NOT be able to schedule a version from a different content.
     */
    public function test_cannot_schedule_version_from_different_content(): void
    {
        Queue::fake();
        $versionB = $this->makeVersion($this->contentB, 1, 'draft', 'Content B Draft');

        $this->actingAs($this->user)
            ->postJson("/api/v1/content/{$this->contentA->id}/versions/{$versionB->id}/schedule", [
                'publish_at' => now()->addHour()->toIso8601String(),
            ])
            ->assertNotFound();
    }

    /**
     * A user should NOT be able to cancel a schedule for a version from a different content.
     */
    public function test_cannot_cancel_schedule_for_version_from_different_content(): void
    {
        $versionB = $this->makeVersion($this->contentB, 1, 'scheduled', 'Content B');

        $this->actingAs($this->user)
            ->deleteJson("/api/v1/content/{$this->contentA->id}/versions/{$versionB->id}/schedule")
            ->assertNotFound();
    }

    // ─── IDOR: Diff endpoint cross-content ────────────────────────────────────

    /**
     * Diff should reject version IDs that don't belong to the requested content.
     */
    public function test_diff_rejects_version_from_different_content(): void
    {
        $versionA = $this->makeVersion($this->contentA, 1, 'published', 'Content A v1');
        $versionB = $this->makeVersion($this->contentB, 1, 'published', 'Content B v1');

        $this->actingAs($this->user)
            ->getJson("/api/v1/content/{$this->contentA->id}/diff?version_a={$versionA->id}&version_b={$versionB->id}")
            ->assertStatus(422)
            ->assertJsonFragment(['Versions must belong to this content item.']);
    }

    // ─── IDOR: AutoSave base_version_id ───────────────────────────────────────

    /**
     * AutoSave base_version_id must belong to the same content (not another content's version).
     */
    public function test_autosave_rejects_base_version_id_from_different_content(): void
    {
        $versionB = $this->makeVersion($this->contentB, 1, 'published', 'Content B');

        $this->actingAs($this->user)
            ->postJson("/api/v1/content/{$this->contentA->id}/autosave", [
                'title' => 'My Draft',
                'body' => 'My body',
                'base_version_id' => $versionB->id, // belongs to contentB!
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['base_version_id']);
    }

    /**
     * AutoSave base_version_id that belongs to the same content should be accepted.
     */
    public function test_autosave_accepts_base_version_id_from_same_content(): void
    {
        $versionA = $this->makeVersion($this->contentA, 1, 'published', 'Content A v1');

        $this->actingAs($this->user)
            ->postJson("/api/v1/content/{$this->contentA->id}/autosave", [
                'title' => 'My Draft',
                'body' => 'My body',
                'base_version_id' => $versionA->id,
            ])
            ->assertOk();

        $draft = ContentDraft::where('content_id', $this->contentA->id)
            ->where('user_id', $this->user->id)
            ->first();

        $this->assertNotNull($draft);
        $this->assertEquals($versionA->id, $draft->base_version_id);
    }

    // ─── Soft-lock enforcement ────────────────────────────────────────────────

    /**
     * If a version is locked by another user within the 15-minute window,
     * an attempt to update it should return 423 Locked.
     */
    public function test_update_rejected_when_version_locked_by_different_user(): void
    {
        $otherUser = User::factory()->create();
        $draft = $this->makeVersion($this->contentA, 1, 'draft', 'Locked Draft');

        // Lock by other user, recently
        $draft->update([
            'locked_by' => (string) $otherUser->getKey(),
            'locked_at' => now()->subMinutes(5),
        ]);

        $this->actingAs($this->user)
            ->patchJson("/api/v1/content/{$this->contentA->id}/versions/{$draft->id}", [
                'title' => 'Attempted Override',
            ])
            ->assertStatus(423);
    }

    /**
     * If the lock has expired (>15 minutes), the update should proceed.
     */
    public function test_update_allowed_when_lock_has_expired(): void
    {
        $otherUser = User::factory()->create();
        $draft = $this->makeVersion($this->contentA, 1, 'draft', 'Stale Lock Draft');

        // Lock is stale (20 minutes ago)
        $draft->update([
            'locked_by' => (string) $otherUser->getKey(),
            'locked_at' => now()->subMinutes(20),
        ]);

        $this->actingAs($this->user)
            ->patchJson("/api/v1/content/{$this->contentA->id}/versions/{$draft->id}", [
                'title' => 'Override Allowed',
            ])
            ->assertOk()
            ->assertJsonPath('data.title', 'Override Allowed');
    }

    /**
     * The lock holder can still edit their own locked version.
     */
    public function test_lock_holder_can_edit_their_own_locked_version(): void
    {
        $draft = $this->makeVersion($this->contentA, 1, 'draft', 'My Locked Draft');

        $draft->update([
            'locked_by' => (string) $this->user->getKey(),
            'locked_at' => now()->subMinutes(2),
        ]);

        $this->actingAs($this->user)
            ->patchJson("/api/v1/content/{$this->contentA->id}/versions/{$draft->id}", [
                'title' => 'My Own Update',
            ])
            ->assertOk();
    }

    // ─── Input size limits ────────────────────────────────────────────────────

    /**
     * Body content exceeding 1MB should be rejected to prevent storage exhaustion.
     */
    public function test_update_rejects_oversized_body(): void
    {
        $draft = $this->makeVersion($this->contentA, 1, 'draft', 'Draft');
        $hugeBody = str_repeat('a', 1_000_001); // 1MB + 1 byte

        $this->actingAs($this->user)
            ->patchJson("/api/v1/content/{$this->contentA->id}/versions/{$draft->id}", [
                'body' => $hugeBody,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['body']);
    }

    /**
     * AutoSave should also reject oversized body content.
     */
    public function test_autosave_rejects_oversized_body(): void
    {
        $hugeBody = str_repeat('a', 1_000_001);

        $this->actingAs($this->user)
            ->postJson("/api/v1/content/{$this->contentA->id}/autosave", [
                'title' => 'Title',
                'body' => $hugeBody,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['body']);
    }

    // ─── Unauthenticated access guard ─────────────────────────────────────────

    public function test_all_versioning_endpoints_require_authentication(): void
    {
        $version = $this->makeVersion($this->contentA, 1, 'draft', 'Draft');

        $this->getJson("/api/v1/content/{$this->contentA->id}/versions")->assertUnauthorized();
        $this->getJson("/api/v1/content/{$this->contentA->id}/versions/{$version->id}")->assertUnauthorized();
        $this->postJson("/api/v1/content/{$this->contentA->id}/versions/draft")->assertUnauthorized();
        $this->patchJson("/api/v1/content/{$this->contentA->id}/versions/{$version->id}", [])->assertUnauthorized();
        $this->postJson("/api/v1/content/{$this->contentA->id}/versions/{$version->id}/publish")->assertUnauthorized();
        $this->postJson("/api/v1/content/{$this->contentA->id}/versions/{$version->id}/rollback")->assertUnauthorized();
        $this->postJson("/api/v1/content/{$this->contentA->id}/versions/{$version->id}/branch", [])->assertUnauthorized();
        $this->getJson("/api/v1/content/{$this->contentA->id}/diff")->assertUnauthorized();
        $this->postJson("/api/v1/content/{$this->contentA->id}/autosave", [])->assertUnauthorized();
    }

    // ─── Scheduled publish past-date guard ────────────────────────────────────

    public function test_schedule_rejects_past_date(): void
    {
        Queue::fake();
        $version = $this->makeVersion($this->contentA, 1, 'draft', 'Draft');

        $this->actingAs($this->user)
            ->postJson("/api/v1/content/{$this->contentA->id}/versions/{$version->id}/schedule", [
                'publish_at' => now()->subMinutes(1)->toIso8601String(),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['publish_at']);
    }

    // ─── Label injection ──────────────────────────────────────────────────────

    /**
     * Version labels should be capped at 255 characters (no XSS or overflow attacks via label field).
     */
    public function test_label_rejects_oversized_label(): void
    {
        $draft = $this->makeVersion($this->contentA, 1, 'draft', 'Draft');
        $longLabel = str_repeat('x', 256); // over 255 char limit

        $this->actingAs($this->user)
            ->postJson("/api/v1/content/{$this->contentA->id}/versions/{$draft->id}/label", [
                'label' => $longLabel,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['label']);
    }

    /**
     * Branch label should also be capped at 255 characters.
     */
    public function test_branch_rejects_oversized_label(): void
    {
        $version = $this->makeVersion($this->contentA, 1, 'published', 'V1');
        $this->contentA->update(['current_version_id' => $version->id]);
        $longLabel = str_repeat('x', 256);

        $this->actingAs($this->user)
            ->postJson("/api/v1/content/{$this->contentA->id}/versions/{$version->id}/branch", [
                'label' => $longLabel,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['label']);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function makeVersion(
        Content $content,
        int $number,
        string $status,
        string $title = 'Title',
        string $body = 'Body',
    ): ContentVersion {
        return $content->versions()->create([
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
