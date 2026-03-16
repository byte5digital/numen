<?php

namespace Tests\Feature\Admin;

use App\Models\Space;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SpaceAdminTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
        // Bind a default space so resolve-space middleware passes
        $space = Space::factory()->create();
        app()->instance('current_space', $space);
    }

    public function test_index_lists_spaces(): void
    {
        Space::factory()->count(3)->create();

        $response = $this->actingAs($this->admin)->get('/admin/spaces');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Admin/Spaces/Index'));
    }

    public function test_create_form_renders(): void
    {
        $response = $this->actingAs($this->admin)->get('/admin/spaces/create');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Admin/Spaces/Create'));
    }

    public function test_store_creates_space(): void
    {
        $response = $this->actingAs($this->admin)->post('/admin/spaces', [
            'name' => 'Test Space',
            'slug' => 'test-space',
            'description' => 'A test space',
            'default_locale' => 'en',
        ]);

        $response->assertRedirect('/admin/spaces');
        $this->assertDatabaseHas('spaces', [
            'name' => 'Test Space',
            'slug' => 'test-space',
            'default_locale' => 'en',
        ]);
    }

    public function test_store_validates_unique_slug(): void
    {
        Space::factory()->create(['slug' => 'existing-slug']);

        $response = $this->actingAs($this->admin)->post('/admin/spaces', [
            'name' => 'Another Space',
            'slug' => 'existing-slug',
            'default_locale' => 'en',
        ]);

        $response->assertSessionHasErrors('slug');
    }

    public function test_edit_form_renders(): void
    {
        $space = Space::factory()->create();

        $response = $this->actingAs($this->admin)->get("/admin/spaces/{$space->id}/edit");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Admin/Spaces/Edit'));
    }

    public function test_update_modifies_space(): void
    {
        $space = Space::factory()->create();

        $response = $this->actingAs($this->admin)->put("/admin/spaces/{$space->id}", [
            'name' => 'Updated Name',
            'slug' => 'updated-slug',
            'default_locale' => 'de',
        ]);

        $response->assertRedirect('/admin/spaces');
        $this->assertDatabaseHas('spaces', [
            'id' => $space->id,
            'name' => 'Updated Name',
            'slug' => 'updated-slug',
            'default_locale' => 'de',
        ]);
    }

    public function test_update_allows_same_slug_on_self(): void
    {
        $space = Space::factory()->create(['slug' => 'my-slug']);

        $response = $this->actingAs($this->admin)->put("/admin/spaces/{$space->id}", [
            'name' => $space->name,
            'slug' => 'my-slug',
            'default_locale' => 'en',
        ]);

        $response->assertRedirect('/admin/spaces');
    }

    public function test_destroy_deletes_space(): void
    {
        // Create two spaces so we can delete one
        $space1 = Space::factory()->create();
        $space2 = Space::factory()->create();

        $response = $this->actingAs($this->admin)->delete("/admin/spaces/{$space2->id}");

        $response->assertRedirect('/admin/spaces');
        $this->assertDatabaseMissing('spaces', ['id' => $space2->id]);
    }

    public function test_cannot_delete_last_space(): void
    {
        // Clear all factories, make exactly one space
        Space::query()->delete();
        $space = Space::factory()->create();
        app()->instance('current_space', $space);

        $response = $this->actingAs($this->admin)->delete("/admin/spaces/{$space->id}");

        $response->assertRedirect('/admin/spaces');
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('spaces', ['id' => $space->id]);
    }

    public function test_space_switcher_updates_session(): void
    {
        $space = Space::factory()->create();

        $response = $this->actingAs($this->admin)->post('/admin/spaces/switch', [
            'space_id' => $space->id,
        ]);

        $response->assertRedirect();
        $this->assertEquals($space->id, session('current_space_id'));
    }

    public function test_space_switcher_rejects_invalid_space(): void
    {
        $response = $this->actingAs($this->admin)->post('/admin/spaces/switch', [
            'space_id' => 'nonexistent-id',
        ]);

        $response->assertSessionHasErrors('space_id');
    }
}
