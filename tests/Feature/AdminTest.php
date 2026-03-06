<?php

namespace Tests\Feature;

use App\Models\Content;
use App\Models\ContentType;
use App\Models\ContentVersion;
use App\Models\Space;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Space $space;

    private ContentType $blogType;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
        $this->space = Space::factory()->create();

        $this->blogType = ContentType::create([
            'space_id' => $this->space->id,
            'name' => 'Blog Post',
            'slug' => 'blog_post',
            'schema' => ['fields' => []],
        ]);
    }

    // --- Login / Logout flow ---

    public function test_login_page_is_accessible_to_guests(): void
    {
        $response = $this->get('/login');

        $response->assertOk();
    }

    public function test_valid_credentials_redirect_to_admin(): void
    {
        $response = $this->post('/login', [
            'email' => $this->admin->email,
            'password' => 'password',
        ]);

        $response->assertRedirect('/admin');
        $this->assertAuthenticatedAs($this->admin);
    }

    public function test_invalid_credentials_return_error(): void
    {
        $response = $this->post('/login', [
            'email' => $this->admin->email,
            'password' => 'wrong-password',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_logout_clears_session(): void
    {
        $this->actingAs($this->admin);

        $response = $this->post('/logout');

        $response->assertRedirect('/');
        $this->assertGuest();
    }

    // --- Dashboard ---

    public function test_dashboard_loads_for_admin(): void
    {
        $this->actingAs($this->admin);

        $response = $this->get('/admin');

        $response->assertOk();
    }

    public function test_dashboard_redirects_unauthenticated_to_login(): void
    {
        $response = $this->get('/admin');

        $response->assertRedirect('/login');
    }

    public function test_non_admin_user_cannot_access_dashboard(): void
    {
        $editor = User::factory()->create(['role' => 'editor']);
        $this->actingAs($editor);

        $response = $this->get('/admin');

        $response->assertForbidden();
    }

    // --- Content CRUD ---

    public function test_admin_content_index_loads(): void
    {
        $this->actingAs($this->admin);

        $this->createContent('Test Article', 'test-article');

        $response = $this->get('/admin/content');

        $response->assertOk();
    }

    public function test_admin_content_show_loads_for_existing_content(): void
    {
        $this->actingAs($this->admin);

        $content = $this->createContent('My Article', 'my-article');

        $response = $this->get('/admin/content/'.$content->id);

        $response->assertOk();
    }

    public function test_admin_content_show_returns_404_for_missing_content(): void
    {
        $this->actingAs($this->admin);

        $response = $this->get('/admin/content/nonexistent-id');

        $response->assertNotFound();
    }

    public function test_admin_can_update_content_status_to_published(): void
    {
        $this->actingAs($this->admin);

        $content = $this->createContent('Draft Article', 'draft-article');

        $response = $this->patch('/admin/content/'.$content->id.'/status', [
            'status' => 'published',
        ]);

        $response->assertRedirect();
        $this->assertEquals('published', $content->fresh()->status);
        $this->assertNotNull($content->fresh()->published_at);
    }

    public function test_admin_can_update_content_status_to_archived(): void
    {
        $this->actingAs($this->admin);

        $content = $this->createContent('Old Article', 'old-article');
        $content->update(['status' => 'published', 'published_at' => now()]);

        $response = $this->patch('/admin/content/'.$content->id.'/status', [
            'status' => 'archived',
        ]);

        $response->assertRedirect();
        $this->assertEquals('archived', $content->fresh()->status);
    }

    public function test_admin_content_status_update_validates_status(): void
    {
        $this->actingAs($this->admin);

        $content = $this->createContent('Article', 'article');

        $response = $this->patch('/admin/content/'.$content->id.'/status', [
            'status' => 'invalid-status',
        ]);

        $response->assertSessionHasErrors('status');
    }

    public function test_admin_can_delete_content(): void
    {
        $this->actingAs($this->admin);

        $content = $this->createContent('To Delete', 'to-delete');

        $response = $this->delete('/admin/content/'.$content->id);

        $response->assertRedirect(route('admin.content'));
        $this->assertSoftDeleted('contents', ['id' => $content->id]);
    }

    // --- Helpers ---

    private function createContent(string $title, string $slug): Content
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
            'excerpt' => "Excerpt for {$title}",
            'body' => "Body content for {$title}",
            'body_format' => 'markdown',
            'author_type' => 'ai_agent',
            'author_id' => 'content_creator',
        ]);

        $content->update(['current_version_id' => $version->id]);

        return $content->fresh();
    }
}
