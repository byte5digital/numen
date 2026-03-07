<?php

namespace Tests\Feature;

use App\Models\Space;
use App\Models\TaxonomyTerm;
use App\Models\User;
use App\Models\Vocabulary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaxonomyAdminTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Space $space;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
        $this->space = Space::factory()->create(['slug' => 'test-space']);
    }

    // ─── GET /admin/taxonomy ──────────────────────────────────────────────────

    public function test_taxonomy_index_redirects_guests(): void
    {
        $response = $this->get('/admin/taxonomy');

        $response->assertRedirect('/login');
    }

    public function test_taxonomy_index_loads_for_admin(): void
    {
        $this->actingAs($this->admin);

        Vocabulary::factory()->count(2)->create(['space_id' => $this->space->id]);

        $response = $this->get('/admin/taxonomy');

        $response->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Taxonomy/Index')
                ->has('vocabularies')
            );
    }

    public function test_taxonomy_index_passes_space_id_to_view(): void
    {
        $this->actingAs($this->admin);

        $response = $this->get('/admin/taxonomy');

        $response->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Taxonomy/Index')
                ->has('spaceId')
            );
    }

    public function test_taxonomy_index_shows_empty_vocabularies_when_no_space(): void
    {
        // No space exists, so the controller should handle it gracefully
        Space::query()->delete();

        $this->actingAs($this->admin);

        $response = $this->get('/admin/taxonomy');

        $response->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Taxonomy/Index')
                ->where('vocabularies', [])
            );
    }

    // ─── GET /admin/taxonomy/{id} ─────────────────────────────────────────────

    public function test_taxonomy_show_redirects_guests(): void
    {
        $vocab = Vocabulary::factory()->create(['space_id' => $this->space->id]);

        $response = $this->get("/admin/taxonomy/{$vocab->id}");

        $response->assertRedirect('/login');
    }

    public function test_taxonomy_show_loads_for_admin(): void
    {
        $this->actingAs($this->admin);

        $vocab = Vocabulary::factory()->create(['space_id' => $this->space->id]);
        TaxonomyTerm::factory()->count(3)->create(['vocabulary_id' => $vocab->id]);

        $response = $this->get("/admin/taxonomy/{$vocab->id}");

        $response->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Taxonomy/Show')
                ->has('vocabulary')
                ->has('tree')
            );
    }

    public function test_taxonomy_show_returns_404_for_unknown_id(): void
    {
        $this->actingAs($this->admin);

        $response = $this->get('/admin/taxonomy/nonexistent-id');

        $response->assertNotFound();
    }

    public function test_taxonomy_show_vocabulary_has_terms_count(): void
    {
        $this->actingAs($this->admin);

        $vocab = Vocabulary::factory()->create(['space_id' => $this->space->id]);
        TaxonomyTerm::factory()->count(5)->create(['vocabulary_id' => $vocab->id]);

        $response = $this->get("/admin/taxonomy/{$vocab->id}");

        $response->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Taxonomy/Show')
                ->where('vocabulary.terms_count', 5)
            );
    }

    // ─── POST /admin/taxonomy ─────────────────────────────────────────────────

    public function test_store_vocabulary_requires_auth(): void
    {
        $response = $this->post('/admin/taxonomy', [
            'space_id' => $this->space->id,
            'name' => 'Tags',
        ]);

        $response->assertRedirect('/login');
    }

    public function test_admin_can_create_vocabulary_and_redirects(): void
    {
        $this->actingAs($this->admin);

        $response = $this->post('/admin/taxonomy', [
            'space_id' => $this->space->id,
            'name' => 'Tags',
            'hierarchy' => false,
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('vocabularies', [
            'space_id' => $this->space->id,
            'name' => 'Tags',
        ]);
    }

    public function test_store_vocabulary_validates_required_fields(): void
    {
        $this->actingAs($this->admin);

        $response = $this->post('/admin/taxonomy', []);

        $response->assertSessionHasErrors(['space_id', 'name']);
    }

    // ─── PATCH /admin/taxonomy/{id} ───────────────────────────────────────────

    public function test_admin_can_update_vocabulary(): void
    {
        $this->actingAs($this->admin);
        $vocab = Vocabulary::factory()->create(['space_id' => $this->space->id]);

        $response = $this->patch("/admin/taxonomy/{$vocab->id}", [
            'name' => 'Updated Tags',
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('vocabularies', [
            'id' => $vocab->id,
            'name' => 'Updated Tags',
        ]);
    }

    // ─── DELETE /admin/taxonomy/{id} ──────────────────────────────────────────

    public function test_admin_can_delete_vocabulary(): void
    {
        $this->actingAs($this->admin);
        $vocab = Vocabulary::factory()->create(['space_id' => $this->space->id]);

        $response = $this->delete("/admin/taxonomy/{$vocab->id}");

        $response->assertRedirect('/admin/taxonomy');
        $this->assertDatabaseMissing('vocabularies', ['id' => $vocab->id]);
    }

    // ─── POST /admin/taxonomy/{vocabId}/terms ─────────────────────────────────

    public function test_admin_can_create_term(): void
    {
        $this->actingAs($this->admin);
        $vocab = Vocabulary::factory()->create(['space_id' => $this->space->id]);

        $response = $this->post("/admin/taxonomy/{$vocab->id}/terms", [
            'name' => 'Laravel',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('taxonomy_terms', [
            'vocabulary_id' => $vocab->id,
            'name' => 'Laravel',
        ]);
    }

    public function test_store_term_validates_name_required(): void
    {
        $this->actingAs($this->admin);
        $vocab = Vocabulary::factory()->create(['space_id' => $this->space->id]);

        $response = $this->post("/admin/taxonomy/{$vocab->id}/terms", []);

        $response->assertSessionHasErrors(['name']);
    }

    // ─── PATCH /admin/taxonomy/terms/{termId} ─────────────────────────────────

    public function test_admin_can_update_term(): void
    {
        $this->actingAs($this->admin);
        $vocab = Vocabulary::factory()->create(['space_id' => $this->space->id]);
        $term = TaxonomyTerm::factory()->create(['vocabulary_id' => $vocab->id]);

        $response = $this->patch("/admin/taxonomy/terms/{$term->id}", [
            'name' => 'Updated Term',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('taxonomy_terms', [
            'id' => $term->id,
            'name' => 'Updated Term',
        ]);
    }

    // ─── DELETE /admin/taxonomy/terms/{termId} ────────────────────────────────

    public function test_admin_can_delete_term(): void
    {
        $this->actingAs($this->admin);
        $vocab = Vocabulary::factory()->create(['space_id' => $this->space->id]);
        $term = TaxonomyTerm::factory()->create(['vocabulary_id' => $vocab->id]);

        $response = $this->delete("/admin/taxonomy/terms/{$term->id}");

        $response->assertRedirect();
        $this->assertDatabaseMissing('taxonomy_terms', ['id' => $term->id]);
    }

    // ─── POST /admin/taxonomy/terms/{termId}/move ─────────────────────────────

    public function test_admin_can_move_term_via_ajax(): void
    {
        $this->actingAs($this->admin);
        $vocab = Vocabulary::factory()->create(['space_id' => $this->space->id]);
        $newParent = TaxonomyTerm::factory()->create(['vocabulary_id' => $vocab->id]);
        $term = TaxonomyTerm::factory()->create(['vocabulary_id' => $vocab->id]);

        $response = $this->postJson("/admin/taxonomy/terms/{$term->id}/move", [
            'parent_id' => $newParent->id,
        ]);

        $response->assertOk();
        $this->assertEquals($newParent->id, $term->fresh()->parent_id);
    }

    // ─── POST /admin/taxonomy/terms/reorder ───────────────────────────────────

    public function test_admin_can_reorder_terms_via_ajax(): void
    {
        $this->actingAs($this->admin);
        $vocab = Vocabulary::factory()->create(['space_id' => $this->space->id]);
        $term1 = TaxonomyTerm::factory()->create(['vocabulary_id' => $vocab->id, 'sort_order' => 0]);
        $term2 = TaxonomyTerm::factory()->create(['vocabulary_id' => $vocab->id, 'sort_order' => 1]);

        $response = $this->postJson('/admin/taxonomy/terms/reorder', [
            'ordering' => [
                $term1->id => 1,
                $term2->id => 0,
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.reordered', true);
    }
}
