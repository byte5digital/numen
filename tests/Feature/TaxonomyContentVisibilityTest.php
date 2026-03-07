<?php

namespace Tests\Feature;

use App\Models\Content;
use App\Models\Space;
use App\Models\TaxonomyTerm;
use App\Models\User;
use App\Models\Vocabulary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaxonomyContentVisibilityTest extends TestCase
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

    // ─── Test 1: Content show page includes taxonomy terms ────────────────────

    public function test_content_show_page_includes_taxonomy_terms(): void
    {
        $this->actingAs($this->admin);

        $vocab = Vocabulary::factory()->create(['space_id' => $this->space->id]);
        $term = TaxonomyTerm::factory()->create(['vocabulary_id' => $vocab->id]);
        $content = Content::factory()->create(['space_id' => $this->space->id]);

        // Assign the term to the content
        $content->taxonomyTerms()->attach($term->id, [
            'sort_order' => 0,
            'auto_assigned' => false,
            'confidence' => null,
        ]);

        $response = $this->get("/admin/content/{$content->id}");

        $response->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Content/Show')
                ->has('taxonomyTerms')
                ->where('taxonomyTerms.0.vocabulary_name', $vocab->name)
                ->where('taxonomyTerms.0.terms.0.id', $term->id)
                ->where('taxonomyTerms.0.terms.0.name', $term->name)
                ->where('taxonomyTerms.0.terms.0.auto_assigned', false)
            );
    }

    public function test_content_show_page_includes_ai_assigned_term_with_confidence(): void
    {
        $this->actingAs($this->admin);

        $vocab = Vocabulary::factory()->create(['space_id' => $this->space->id]);
        $term = TaxonomyTerm::factory()->create(['vocabulary_id' => $vocab->id]);
        $content = Content::factory()->create(['space_id' => $this->space->id]);

        $content->taxonomyTerms()->attach($term->id, [
            'sort_order' => 0,
            'auto_assigned' => true,
            'confidence' => 0.94,
        ]);

        $response = $this->get("/admin/content/{$content->id}");

        $response->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Content/Show')
                ->where('taxonomyTerms.0.terms.0.auto_assigned', true)
                ->where('taxonomyTerms.0.terms.0.confidence', 0.94)
            );
    }

    public function test_content_show_page_has_empty_taxonomy_terms_when_none_assigned(): void
    {
        $this->actingAs($this->admin);

        $content = Content::factory()->create(['space_id' => $this->space->id]);

        $response = $this->get("/admin/content/{$content->id}");

        $response->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Content/Show')
                ->where('taxonomyTerms', [])
            );
    }

    // ─── Test 2: Term assignment via POST endpoint ────────────────────────────

    public function test_admin_can_assign_term_to_content(): void
    {
        $this->actingAs($this->admin);

        $vocab = Vocabulary::factory()->create(['space_id' => $this->space->id]);
        $term = TaxonomyTerm::factory()->create(['vocabulary_id' => $vocab->id]);
        $content = Content::factory()->create(['space_id' => $this->space->id]);

        $response = $this->post("/admin/content/{$content->id}/terms", [
            'term_id' => $term->id,
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('content_taxonomy', [
            'content_id' => $content->id,
            'term_id' => $term->id,
        ]);
    }

    public function test_assign_term_validates_term_id_required(): void
    {
        $this->actingAs($this->admin);

        $content = Content::factory()->create(['space_id' => $this->space->id]);

        $response = $this->post("/admin/content/{$content->id}/terms", []);

        $response->assertSessionHasErrors(['term_id']);
    }

    public function test_assign_term_validates_term_id_exists(): void
    {
        $this->actingAs($this->admin);

        $content = Content::factory()->create(['space_id' => $this->space->id]);

        $response = $this->post("/admin/content/{$content->id}/terms", [
            'term_id' => 'nonexistent-term-id',
        ]);

        $response->assertSessionHasErrors(['term_id']);
    }

    public function test_admin_can_remove_term_from_content(): void
    {
        $this->actingAs($this->admin);

        $vocab = Vocabulary::factory()->create(['space_id' => $this->space->id]);
        $term = TaxonomyTerm::factory()->create(['vocabulary_id' => $vocab->id]);
        $content = Content::factory()->create(['space_id' => $this->space->id]);

        $content->taxonomyTerms()->attach($term->id, [
            'sort_order' => 0,
            'auto_assigned' => false,
            'confidence' => null,
        ]);

        $this->assertDatabaseHas('content_taxonomy', [
            'content_id' => $content->id,
            'term_id' => $term->id,
        ]);

        $response = $this->delete("/admin/content/{$content->id}/terms/{$term->id}");

        $response->assertRedirect();

        $this->assertDatabaseMissing('content_taxonomy', [
            'content_id' => $content->id,
            'term_id' => $term->id,
        ]);
    }

    // ─── Test 3: Term show page returns assigned content ─────────────────────

    public function test_term_show_page_returns_assigned_content(): void
    {
        $this->actingAs($this->admin);

        $vocab = Vocabulary::factory()->create(['space_id' => $this->space->id]);
        $term = TaxonomyTerm::factory()->create(['vocabulary_id' => $vocab->id]);
        $content = Content::factory()->create(['space_id' => $this->space->id]);

        $content->taxonomyTerms()->attach($term->id, [
            'sort_order' => 0,
            'auto_assigned' => false,
            'confidence' => null,
        ]);

        $response = $this->get("/admin/taxonomy/terms/{$term->id}");

        $response->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Taxonomy/TermShow')
                ->has('term')
                ->has('content')
                ->where('term.id', $term->id)
                ->where('term.name', $term->name)
                ->where('content.total', 1)
                ->where('content.data.0.id', $content->id)
            );
    }

    public function test_term_show_page_returns_empty_when_no_content_assigned(): void
    {
        $this->actingAs($this->admin);

        $vocab = Vocabulary::factory()->create(['space_id' => $this->space->id]);
        $term = TaxonomyTerm::factory()->create(['vocabulary_id' => $vocab->id]);

        $response = $this->get("/admin/taxonomy/terms/{$term->id}");

        $response->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Taxonomy/TermShow')
                ->where('content.total', 0)
            );
    }

    public function test_term_show_page_includes_descendants_when_requested(): void
    {
        $this->actingAs($this->admin);

        $vocab = Vocabulary::factory()->create([
            'space_id' => $this->space->id,
            'hierarchy' => true,
        ]);
        $parent = TaxonomyTerm::factory()->create(['vocabulary_id' => $vocab->id]);
        $child = TaxonomyTerm::factory()->withParent($parent)->create();
        $content = Content::factory()->create(['space_id' => $this->space->id]);

        // Assign content to child term only
        $content->taxonomyTerms()->attach($child->id, [
            'sort_order' => 0,
            'auto_assigned' => false,
            'confidence' => null,
        ]);

        // Without descendants: parent term should see 0 content
        $response = $this->get("/admin/taxonomy/terms/{$parent->id}");
        $response->assertOk()
            ->assertInertia(fn ($page) => $page->where('content.total', 0));

        // With descendants: parent should see child's content
        $response = $this->get("/admin/taxonomy/terms/{$parent->id}?descendants=1");
        $response->assertOk()
            ->assertInertia(fn ($page) => $page->where('content.total', 1));
    }

    // ─── Term search autocomplete ─────────────────────────────────────────────

    public function test_term_search_returns_matching_terms(): void
    {
        $this->actingAs($this->admin);

        $vocab = Vocabulary::factory()->create(['space_id' => $this->space->id]);
        TaxonomyTerm::factory()->create(['vocabulary_id' => $vocab->id, 'name' => 'Laravel']);
        TaxonomyTerm::factory()->create(['vocabulary_id' => $vocab->id, 'name' => 'PHP']);

        $response = $this->getJson("/admin/taxonomy/{$vocab->id}/terms/search?q=Lara");

        $response->assertOk()
            ->assertJsonPath('data.0.name', 'Laravel')
            ->assertJsonCount(1, 'data');
    }

    public function test_term_search_returns_all_terms_when_query_empty(): void
    {
        $this->actingAs($this->admin);

        $vocab = Vocabulary::factory()->create(['space_id' => $this->space->id]);
        TaxonomyTerm::factory()->count(3)->create(['vocabulary_id' => $vocab->id]);

        $response = $this->getJson("/admin/taxonomy/{$vocab->id}/terms/search");

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_term_show_page_redirects_guests(): void
    {
        $vocab = Vocabulary::factory()->create(['space_id' => $this->space->id]);
        $term = TaxonomyTerm::factory()->create(['vocabulary_id' => $vocab->id]);

        $response = $this->get("/admin/taxonomy/terms/{$term->id}");

        $response->assertRedirect('/login');
    }
}
