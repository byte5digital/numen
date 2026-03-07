<?php

namespace Tests\Feature;

use App\Models\Content;
use App\Models\ContentType;
use App\Models\ContentVersion;
use App\Models\Space;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DiffApiTest extends TestCase
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

    // ─── Authentication ───────────────────────────────────────────────────────

    public function test_diff_requires_authentication(): void
    {
        $this->getJson("/api/v1/content/{$this->content->id}/diff")
            ->assertUnauthorized();
    }

    // ─── compare ─────────────────────────────────────────────────────────────

    public function test_diff_returns_structured_comparison(): void
    {
        $v1 = $this->makeVersion(1, 'published', 'Original Title', 'Original body content');
        $v2 = $this->makeVersion(2, 'draft', 'Updated Title', 'Updated body content');

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/content/{$this->content->id}/diff?version_a={$v1->id}&version_b={$v2->id}");

        $response->assertOk()
            ->assertJsonPath('data.has_changes', true)
            ->assertJsonStructure([
                'data' => [
                    'version_a',
                    'version_b',
                    'has_changes',
                    'summary',
                    'fields',
                    'blocks',
                    'seo',
                ],
            ]);
    }

    public function test_diff_detects_title_change(): void
    {
        $v1 = $this->makeVersion(1, 'published', 'Old Title', 'Same body');
        $v2 = $this->makeVersion(2, 'draft', 'New Title', 'Same body');

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/content/{$this->content->id}/diff?version_a={$v1->id}&version_b={$v2->id}");

        $response->assertOk()
            ->assertJsonPath('data.has_changes', true);

        $fields = $response->json('data.fields');
        $this->assertArrayHasKey('title', $fields);
    }

    public function test_diff_with_no_changes_reports_false(): void
    {
        $v1 = $this->makeVersion(1, 'published', 'Same Title', 'Same body');
        $v2 = $this->makeVersion(2, 'draft', 'Same Title', 'Same body');

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/content/{$this->content->id}/diff?version_a={$v1->id}&version_b={$v2->id}");

        $response->assertOk()
            ->assertJsonPath('data.has_changes', false)
            ->assertJsonPath('data.summary', 'No changes');
    }

    public function test_diff_requires_version_a(): void
    {
        $v2 = $this->makeVersion(2, 'draft', 'Title', 'Body');

        $this->actingAs($this->user)
            ->getJson("/api/v1/content/{$this->content->id}/diff?version_b={$v2->id}")
            ->assertUnprocessable();
    }

    public function test_diff_requires_version_b(): void
    {
        $v1 = $this->makeVersion(1, 'published', 'Title', 'Body');

        $this->actingAs($this->user)
            ->getJson("/api/v1/content/{$this->content->id}/diff?version_a={$v1->id}")
            ->assertUnprocessable();
    }

    public function test_diff_rejects_nonexistent_version_ids(): void
    {
        $this->actingAs($this->user)
            ->getJson("/api/v1/content/{$this->content->id}/diff?version_a=nonexistent&version_b=alsonotreal")
            ->assertUnprocessable();
    }

    public function test_diff_rejects_versions_from_different_content(): void
    {
        $otherContent = Content::create([
            'space_id' => $this->space->id,
            'content_type_id' => $this->type->id,
            'slug' => 'other-content',
            'status' => 'draft',
            'locale' => 'en',
        ]);

        $v1 = $this->makeVersion(1, 'published', 'V1', 'Body');

        $foreignVersion = $otherContent->versions()->create([
            'version_number' => 1,
            'title' => 'Foreign',
            'body' => 'Foreign body',
            'body_format' => 'markdown',
            'author_type' => 'human',
            'author_id' => $this->user->id,
            'status' => 'published',
        ]);

        $this->actingAs($this->user)
            ->getJson("/api/v1/content/{$this->content->id}/diff?version_a={$v1->id}&version_b={$foreignVersion->id}")
            ->assertStatus(422);
    }

    public function test_diff_includes_seo_changes(): void
    {
        $v1 = $this->makeVersion(1, 'published', 'Title', 'Body');
        $v1->update(['seo_data' => ['meta_description' => 'Old description']]);

        $v2 = $this->makeVersion(2, 'draft', 'Title', 'Body');
        $v2->update(['seo_data' => ['meta_description' => 'New description']]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/content/{$this->content->id}/diff?version_a={$v1->id}&version_b={$v2->id}");

        $response->assertOk()
            ->assertJsonPath('data.has_changes', true);

        $seo = $response->json('data.seo');
        $this->assertArrayHasKey('meta_description', $seo);
    }

    public function test_diff_detects_block_changes(): void
    {
        $v1 = $this->makeVersion(1, 'published', 'Title', 'Body');
        $v1->blocks()->create(['type' => 'text', 'sort_order' => 0, 'data' => ['text' => 'Old text']]);

        $v2 = $this->makeVersion(2, 'draft', 'Title', 'Body');
        $v2->blocks()->create(['type' => 'text', 'sort_order' => 0, 'data' => ['text' => 'New text']]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/content/{$this->content->id}/diff?version_a={$v1->id}&version_b={$v2->id}");

        $response->assertOk()
            ->assertJsonPath('data.has_changes', true);

        $blocks = $response->json('data.blocks');
        $this->assertNotEmpty($blocks);
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
