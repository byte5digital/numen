<?php

namespace Tests\Unit;

use App\Models\Content;
use App\Models\ContentDraft;
use App\Models\ContentType;
use App\Models\Space;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContentDraftModelTest extends TestCase
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

    public function test_draft_belongs_to_content(): void
    {
        $draft = $this->makeDraft();

        $this->assertEquals($this->content->id, $draft->content->id);
    }

    public function test_draft_belongs_to_user(): void
    {
        $draft = $this->makeDraft();

        $this->assertEquals($this->user->id, $draft->user->id);
    }

    public function test_draft_belongs_to_base_version(): void
    {
        $version = $this->content->versions()->create([
            'version_number' => 1,
            'title' => 'V1',
            'body' => 'body',
            'body_format' => 'markdown',
            'author_type' => 'human',
            'author_id' => $this->user->id,
            'status' => 'published',
        ]);

        $draft = $this->makeDraft(['base_version_id' => $version->id]);

        $this->assertEquals($version->id, $draft->baseVersion->id);
    }

    public function test_draft_without_base_version_has_null_relation(): void
    {
        $draft = $this->makeDraft();

        $this->assertNull($draft->baseVersion);
    }

    public function test_unique_constraint_per_user_per_content(): void
    {
        $this->makeDraft();

        $this->expectException(\Illuminate\Database\QueryException::class);

        $this->makeDraft();
    }

    public function test_different_users_can_have_drafts_for_same_content(): void
    {
        $otherUser = User::factory()->create();

        $this->makeDraft();
        ContentDraft::create([
            'content_id' => $this->content->id,
            'user_id' => $otherUser->id,
            'title' => 'Other draft',
            'body' => 'Other body',
            'last_saved_at' => now(),
        ]);

        $this->assertEquals(2, ContentDraft::where('content_id', $this->content->id)->count());
    }

    public function test_structured_fields_cast_to_array(): void
    {
        $draft = $this->makeDraft(['structured_fields' => ['key' => 'value', 'num' => 42]]);

        $this->assertIsArray($draft->structured_fields);
        $this->assertEquals('value', $draft->structured_fields['key']);
    }

    public function test_blocks_snapshot_cast_to_array(): void
    {
        $blocks = [
            ['type' => 'text', 'sort_order' => 0, 'data' => ['text' => 'Hello']],
        ];

        $draft = $this->makeDraft(['blocks_snapshot' => $blocks]);

        $this->assertIsArray($draft->blocks_snapshot);
        $this->assertCount(1, $draft->blocks_snapshot);
        $this->assertEquals('text', $draft->blocks_snapshot[0]['type']);
    }

    public function test_save_count_defaults_to_zero(): void
    {
        $draft = $this->makeDraft();

        $this->assertEquals(0, $draft->save_count);
    }

    public function test_last_saved_at_is_cast_to_datetime(): void
    {
        $draft = $this->makeDraft();

        $this->assertInstanceOf(\Carbon\Carbon::class, $draft->last_saved_at);
    }

    public function test_cascade_delete_when_content_deleted(): void
    {
        $this->makeDraft();
        $this->assertEquals(1, ContentDraft::count());

        $this->content->forceDelete();

        $this->assertEquals(0, ContentDraft::count());
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeDraft(array $overrides = []): ContentDraft
    {
        return ContentDraft::create(array_merge([
            'content_id' => $this->content->id,
            'user_id' => $this->user->id,
            'title' => 'Draft Title',
            'body' => 'Draft body',
            'body_format' => 'markdown',
            'last_saved_at' => now(),
        ], $overrides));
    }
}
