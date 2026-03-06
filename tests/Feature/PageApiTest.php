<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\PageComponent;
use App\Models\Space;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PageApiTest extends TestCase
{
    use RefreshDatabase;

    private Space $space;

    protected function setUp(): void
    {
        parent::setUp();
        $this->space = Space::factory()->create();
    }

    // --- Pages index ---

    public function test_pages_index_returns_empty_when_no_published_pages(): void
    {
        $response = $this->getJson('/api/v1/pages');

        $response->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_pages_index_returns_published_pages(): void
    {
        Page::factory()->published()->create([
            'space_id' => $this->space->id,
            'slug'     => 'home',
            'title'    => 'Home Page',
        ]);
        Page::factory()->published()->create([
            'space_id' => $this->space->id,
            'slug'     => 'about',
            'title'    => 'About Us',
        ]);

        $response = $this->getJson('/api/v1/pages');

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_pages_index_excludes_draft_pages(): void
    {
        Page::factory()->published()->create(['space_id' => $this->space->id, 'slug' => 'published']);
        Page::factory()->create(['space_id' => $this->space->id, 'slug' => 'draft', 'status' => 'draft']);

        $response = $this->getJson('/api/v1/pages');

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    }

    // --- Page show ---

    public function test_show_returns_published_page_by_slug(): void
    {
        Page::factory()->published()->create([
            'space_id' => $this->space->id,
            'slug'     => 'landing-page',
            'title'    => 'Landing Page',
            'meta'     => ['description' => 'Welcome to our site'],
        ]);

        $response = $this->getJson('/api/v1/pages/landing-page');

        $response->assertOk()
            ->assertJsonPath('data.slug', 'landing-page')
            ->assertJsonPath('data.title', 'Landing Page');
    }

    public function test_show_includes_page_components(): void
    {
        $page = Page::factory()->published()->create([
            'space_id' => $this->space->id,
            'slug'     => 'with-components',
        ]);

        PageComponent::create([
            'page_id'    => $page->id,
            'type'       => 'hero_banner',
            'sort_order' => 0,
            'data'       => ['headline' => 'Welcome!'],
        ]);

        PageComponent::create([
            'page_id'    => $page->id,
            'type'       => 'text_block',
            'sort_order' => 1,
            'data'       => ['content' => 'Body text here.'],
        ]);

        $response = $this->getJson('/api/v1/pages/with-components');

        $response->assertOk()
            ->assertJsonCount(2, 'data.components');
    }

    public function test_show_returns_404_for_missing_page(): void
    {
        $response = $this->getJson('/api/v1/pages/nonexistent-slug');

        $response->assertStatus(404)
            ->assertJsonPath('error', 'Page not found');
    }

    public function test_show_returns_404_for_draft_page(): void
    {
        Page::factory()->create([
            'space_id' => $this->space->id,
            'slug'     => 'draft-page',
            'status'   => 'draft',
        ]);

        $response = $this->getJson('/api/v1/pages/draft-page');

        $response->assertStatus(404);
    }

    public function test_page_index_returns_slug_and_title(): void
    {
        Page::factory()->published()->create([
            'space_id' => $this->space->id,
            'slug'     => 'test-page',
            'title'    => 'Test Page Title',
        ]);

        $response = $this->getJson('/api/v1/pages');

        $response->assertOk()
            ->assertJsonFragment(['slug' => 'test-page', 'title' => 'Test Page Title']);
    }
}
