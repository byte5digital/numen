<?php

namespace Tests\Feature;

use App\Models\Content;
use App\Models\ContentType;
use App\Models\ContentVersion;
use App\Models\Space;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContentApiTest extends TestCase
{
    use RefreshDatabase;

    private Space $space;

    private ContentType $blogType;

    protected function setUp(): void
    {
        parent::setUp();

        $this->space = Space::create([
            'name' => 'Test Space',
            'slug' => 'test-space',
        ]);

        $this->blogType = ContentType::create([
            'space_id' => $this->space->id,
            'name' => 'Blog Post',
            'slug' => 'blog_post',
            'schema' => ['fields' => []],
        ]);
    }

    public function test_content_index_returns_empty_when_no_published_content(): void
    {
        $response = $this->getJson('/api/v1/content');

        $response->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_content_index_returns_published_content(): void
    {
        $content = $this->createPublishedContent('Test Article', 'test-article');

        $response = $this->getJson('/api/v1/content');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Test Article')
            ->assertJsonPath('data.0.slug', 'test-article');
    }

    public function test_content_index_excludes_draft_content(): void
    {
        $this->createPublishedContent('Published', 'published');
        $this->createDraftContent('Draft', 'draft');

        $response = $this->getJson('/api/v1/content');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Published');
    }

    public function test_content_show_by_slug(): void
    {
        $this->createPublishedContent('My Article', 'my-article');

        $response = $this->getJson('/api/v1/content/my-article');

        $response->assertOk()
            ->assertJsonPath('data.title', 'My Article')
            ->assertJsonPath('data.slug', 'my-article')
            ->assertJsonPath('data.type', 'blog_post');
    }

    public function test_content_show_returns_404_for_missing_slug(): void
    {
        $response = $this->getJson('/api/v1/content/nonexistent');

        $response->assertNotFound();
    }

    public function test_content_filtered_by_type(): void
    {
        $faqType = ContentType::create([
            'space_id' => $this->space->id,
            'name' => 'FAQ',
            'slug' => 'faq',
            'schema' => ['fields' => []],
        ]);

        $this->createPublishedContent('Blog Post', 'blog-post');
        $this->createPublishedContent('FAQ Item', 'faq-item', $faqType);

        $response = $this->getJson('/api/v1/content?type=blog_post');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Blog Post');
    }

    public function test_content_filtered_by_locale(): void
    {
        $this->createPublishedContent('English Article', 'en-article', null, 'en');
        $this->createPublishedContent('German Article', 'de-article', null, 'de');

        $response = $this->getJson('/api/v1/content?locale=de');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'German Article');
    }

    public function test_content_by_type_endpoint(): void
    {
        $this->createPublishedContent('Blog One', 'blog-one');
        $this->createPublishedContent('Blog Two', 'blog-two');

        $response = $this->getJson('/api/v1/content/type/blog_post');

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_content_includes_seo_data(): void
    {
        $content = $this->createPublishedContent('SEO Article', 'seo-article');
        $version = $content->currentVersion;
        $version->update([
            'seo_data' => [
                'meta_description' => 'Test meta description',
                'seo_title' => 'SEO Optimized Title',
            ],
            'seo_score' => 85.50,
        ]);

        $response = $this->getJson('/api/v1/content/seo-article');

        $response->assertOk()
            ->assertJsonPath('data.seo.meta_description', 'Test meta description')
            ->assertJsonPath('data.meta.seo_score', '85.50');
    }

    public function test_content_includes_version_metadata(): void
    {
        $this->createPublishedContent('AI Article', 'ai-article');

        $response = $this->getJson('/api/v1/content/ai-article');

        $response->assertOk()
            ->assertJsonPath('data.meta.version', 1)
            ->assertJsonPath('data.meta.generated_by', 'ai')
            ->assertJsonPath('data.author.type', 'ai_agent');
    }

    // --- Helpers ---

    private function createPublishedContent(
        string $title,
        string $slug,
        ?ContentType $type = null,
        string $locale = 'en',
    ): Content {
        $content = Content::create([
            'space_id' => $this->space->id,
            'content_type_id' => ($type ?? $this->blogType)->id,
            'slug' => $slug,
            'status' => 'published',
            'locale' => $locale,
            'published_at' => now(),
        ]);

        $version = ContentVersion::create([
            'content_id' => $content->id,
            'version_number' => 1,
            'title' => $title,
            'excerpt' => "Excerpt for {$title}",
            'body' => "Full body content for {$title}",
            'body_format' => 'markdown',
            'author_type' => 'ai_agent',
            'author_id' => 'content_creator',
        ]);

        $content->update(['current_version_id' => $version->id]);

        return $content->fresh();
    }

    private function createDraftContent(string $title, string $slug): Content
    {
        $content = Content::create([
            'space_id' => $this->space->id,
            'content_type_id' => $this->blogType->id,
            'slug' => $slug,
            'status' => 'draft',
            'locale' => 'en',
        ]);

        $version = ContentVersion::create([
            'content_id' => $content->id,
            'version_number' => 1,
            'title' => $title,
            'excerpt' => 'Draft excerpt',
            'body' => 'Draft body',
            'body_format' => 'markdown',
            'author_type' => 'ai_agent',
            'author_id' => 'system',
        ]);

        $content->update(['current_version_id' => $version->id]);

        return $content;
    }
}
