<?php

namespace Tests\Unit;

use App\Models\Content;
use App\Models\ContentType;
use App\Models\ContentVersion;
use App\Models\Space;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContentVersionModelTest extends TestCase
{
    use RefreshDatabase;

    private Content $content;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $space = Space::create(['name' => 'Space', 'slug' => 'space']);
        $type = ContentType::create(['space_id' => $space->id, 'name' => 'Blog', 'slug' => 'blog', 'schema' => []]);
        $this->content = Content::create([
            'space_id' => $space->id,
            'content_type_id' => $type->id,
            'slug' => 'test',
            'status' => 'draft',
            'locale' => 'en',
        ]);
        $this->user = User::factory()->create();
    }

    public function test_is_draft_returns_true_for_draft_status(): void
    {
        $version = $this->makeVersion('draft');
        $this->assertTrue($version->isDraft());
        $this->assertFalse($version->isPublished());
        $this->assertFalse($version->isScheduled());
    }

    public function test_is_published_returns_true_for_published_status(): void
    {
        $version = $this->makeVersion('published');
        $this->assertTrue($version->isPublished());
        $this->assertFalse($version->isDraft());
        $this->assertFalse($version->isScheduled());
    }

    public function test_is_scheduled_returns_true_for_scheduled_status(): void
    {
        $version = $this->makeVersion('scheduled');
        $this->assertTrue($version->isScheduled());
        $this->assertFalse($version->isDraft());
        $this->assertFalse($version->isPublished());
    }

    public function test_is_ai_generated_returns_true_for_ai_author_type(): void
    {
        $version = $this->content->versions()->create([
            'version_number' => 1,
            'title' => 'AI Title',
            'body' => 'AI body',
            'body_format' => 'markdown',
            'author_type' => 'ai_agent',
            'author_id' => 'pipeline-1',
            'status' => 'draft',
        ]);

        $this->assertTrue($version->isAiGenerated());
    }

    public function test_is_ai_generated_returns_false_for_human_author(): void
    {
        $version = $this->makeVersion('draft');
        $this->assertFalse($version->isAiGenerated());
    }

    public function test_compute_hash_is_deterministic(): void
    {
        $version = $this->makeVersion('draft', 'Consistent Title', 'Consistent body');

        $hash1 = $version->computeHash();
        $hash2 = $version->computeHash();

        $this->assertEquals($hash1, $hash2);
        $this->assertEquals(64, strlen($hash1)); // SHA-256 hex
    }

    public function test_compute_hash_changes_with_title(): void
    {
        $v1 = $this->makeVersion('draft', 'Title One', 'Same body');
        $v2 = $this->makeVersion('draft', 'Title Two', 'Same body', 2);

        $this->assertNotEquals($v1->computeHash(), $v2->computeHash());
    }

    public function test_compute_hash_changes_with_body(): void
    {
        $v1 = $this->makeVersion('draft', 'Same title', 'Body A');
        $v2 = $this->makeVersion('draft', 'Same title', 'Body B', 2);

        $this->assertNotEquals($v1->computeHash(), $v2->computeHash());
    }

    public function test_compute_hash_identical_content_produces_same_hash(): void
    {
        $v1 = $this->makeVersion('draft', 'Title', 'Body', 1);
        $v2 = $this->makeVersion('draft', 'Title', 'Body', 2);

        $this->assertEquals($v1->computeHash(), $v2->computeHash());
    }

    public function test_has_blocks_returns_false_when_no_blocks(): void
    {
        $version = $this->makeVersion('draft');
        $this->assertFalse($version->hasBlocks());
    }

    public function test_has_blocks_returns_true_when_blocks_exist(): void
    {
        $version = $this->makeVersion('draft');
        $version->blocks()->create(['type' => 'text', 'sort_order' => 0, 'data' => ['text' => 'Hello']]);

        $this->assertTrue($version->hasBlocks());
    }

    public function test_scope_drafts_filters_correctly(): void
    {
        $this->makeVersion('draft', 'Draft V', 'body', 1);
        $this->makeVersion('published', 'Pub V', 'body', 2);
        $this->makeVersion('archived', 'Arc V', 'body', 3);

        $drafts = ContentVersion::drafts()->get();
        $this->assertCount(1, $drafts);
        $this->assertEquals('Draft V', $drafts->first()->title);
    }

    public function test_scope_published_filters_correctly(): void
    {
        $this->makeVersion('draft', 'Draft V', 'body', 1);
        $this->makeVersion('published', 'Pub V', 'body', 2);

        $published = ContentVersion::published()->get();
        $this->assertCount(1, $published);
        $this->assertEquals('Pub V', $published->first()->title);
    }

    public function test_scope_scheduled_filters_correctly(): void
    {
        $this->makeVersion('draft', 'Draft V', 'body', 1);
        $scheduled = $this->makeVersion('scheduled', 'Sched V', 'body', 2);
        $scheduled->update(['scheduled_at' => now()->addDay()]);

        $results = ContentVersion::scheduled()->get();
        $this->assertCount(1, $results);
    }

    public function test_scope_labeled_filters_versions_with_labels(): void
    {
        $v1 = $this->makeVersion('published', 'V1', 'body', 1);
        $v1->update(['label' => 'Launch Version']);
        $this->makeVersion('draft', 'V2', 'body', 2);

        $labeled = ContentVersion::labeled()->get();
        $this->assertCount(1, $labeled);
        $this->assertEquals('Launch Version', $labeled->first()->label);
    }

    public function test_parent_version_relationship(): void
    {
        $parent = $this->makeVersion('published', 'Parent', 'body', 1);
        $child = $this->content->versions()->create([
            'version_number' => 2,
            'title' => 'Child',
            'body' => 'body',
            'body_format' => 'markdown',
            'author_type' => 'human',
            'author_id' => $this->user->id,
            'status' => 'draft',
            'parent_version_id' => $parent->id,
        ]);

        $this->assertEquals($parent->id, $child->parentVersion->id);
    }

    public function test_child_versions_relationship(): void
    {
        $parent = $this->makeVersion('published', 'Parent', 'body', 1);
        $child1 = $this->content->versions()->create([
            'version_number' => 2,
            'title' => 'Child 1',
            'body' => 'body',
            'body_format' => 'markdown',
            'author_type' => 'human',
            'author_id' => $this->user->id,
            'status' => 'draft',
            'parent_version_id' => $parent->id,
        ]);

        $this->assertCount(1, $parent->childVersions);
        $this->assertEquals($child1->id, $parent->childVersions->first()->id);
    }

    public function test_blocks_are_ordered_by_sort_order(): void
    {
        $version = $this->makeVersion('draft');
        $version->blocks()->create(['type' => 'image', 'sort_order' => 2, 'data' => []]);
        $version->blocks()->create(['type' => 'text', 'sort_order' => 0, 'data' => []]);
        $version->blocks()->create(['type' => 'video', 'sort_order' => 1, 'data' => []]);

        $version->load('blocks');
        $sortOrders = $version->blocks->pluck('sort_order')->toArray();
        $this->assertEquals([0, 1, 2], $sortOrders);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function makeVersion(
        string $status,
        string $title = 'Title',
        string $body = 'Body',
        int $number = 1,
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
