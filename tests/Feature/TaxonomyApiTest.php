<?php

namespace Tests\Feature;

use App\Models\Space;
use App\Models\TaxonomyTerm;
use App\Models\User;
use App\Models\Vocabulary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TaxonomyApiTest extends TestCase
{
    use RefreshDatabase;

    private Space $space;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->space = Space::factory()->create(['slug' => 'test-space']);
        $this->user = User::factory()->create();
    }

    // ─── GET /api/v1/taxonomies ───────────────────────────────────────────────

    public function test_list_vocabularies_is_public(): void
    {
        Vocabulary::factory()->count(3)->create(['space_id' => $this->space->id]);

        $response = $this->getJson('/api/v1/taxonomies', [
            'X-Space' => 'test-space',
        ]);

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_list_vocabularies_scoped_to_space(): void
    {
        $other = Space::factory()->create(['slug' => 'other-space']);
        Vocabulary::factory()->count(2)->create(['space_id' => $this->space->id]);
        Vocabulary::factory()->count(3)->create(['space_id' => $other->id]);

        $response = $this->getJson('/api/v1/taxonomies', [
            'X-Space' => 'test-space',
        ]);

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_list_vocabularies_returns_correct_fields(): void
    {
        Vocabulary::factory()->create([
            'space_id' => $this->space->id,
            'name' => 'Categories',
            'slug' => 'categories',
        ]);

        $response = $this->getJson('/api/v1/taxonomies', ['X-Space' => 'test-space']);

        $response->assertOk()
            ->assertJsonPath('data.0.name', 'Categories')
            ->assertJsonPath('data.0.slug', 'categories')
            ->assertJsonStructure(['data' => [['id', 'name', 'slug', 'hierarchy', 'allow_multiple']]]);
    }

    public function test_list_vocabularies_404_for_unknown_space(): void
    {
        $response = $this->getJson('/api/v1/taxonomies', ['X-Space' => 'nonexistent']);

        $response->assertNotFound();
    }

    // ─── GET /api/v1/taxonomies/{vocabSlug} ───────────────────────────────────

    public function test_show_vocabulary_with_tree(): void
    {
        $vocab = Vocabulary::factory()->create([
            'space_id' => $this->space->id,
            'slug' => 'categories',
        ]);
        TaxonomyTerm::factory()->create(['vocabulary_id' => $vocab->id]);

        $response = $this->getJson('/api/v1/taxonomies/categories', ['X-Space' => 'test-space']);

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'vocabulary' => ['id', 'name', 'slug'],
                    'tree',
                ],
            ]);
    }

    public function test_show_vocabulary_404_for_unknown_slug(): void
    {
        $response = $this->getJson('/api/v1/taxonomies/nonexistent', ['X-Space' => 'test-space']);

        $response->assertNotFound();
    }

    // ─── POST /api/v1/taxonomies ──────────────────────────────────────────────

    public function test_create_vocabulary_requires_auth(): void
    {
        $response = $this->postJson('/api/v1/taxonomies', [
            'name' => 'Tags',
        ], ['X-Space' => 'test-space']);

        $response->assertUnauthorized();
    }

    public function test_authenticated_user_can_create_vocabulary(): void
    {
        Sanctum::actingAs($this->user);

        // Space is derived from X-Space header — not passed in the body
        $response = $this->postJson('/api/v1/taxonomies', [
            'name' => 'Tags',
            'hierarchy' => false,
        ], ['X-Space' => 'test-space']);

        $response->assertSuccessful()
            ->assertJsonPath('data.name', 'Tags')
            ->assertJsonPath('data.slug', 'tags');

        $this->assertDatabaseHas('vocabularies', [
            'space_id' => $this->space->id,
            'name' => 'Tags',
        ]);
    }

    public function test_create_vocabulary_validates_required_fields(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/v1/taxonomies', [], ['X-Space' => 'test-space']);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    public function test_create_vocabulary_returns_404_for_unknown_space(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/v1/taxonomies', [
            'name' => 'Tags',
        ], ['X-Space' => 'nonexistent-space']);

        // Space resolved from header — unknown space returns 404 (not 422)
        $response->assertNotFound();
    }

    public function test_create_vocabulary_validates_slug_format(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/v1/taxonomies', [
            'name' => 'Tags',
            'slug' => 'Invalid Slug!',
        ], ['X-Space' => 'test-space']);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['slug']);
    }

    // ─── Security: Space-Scoping on Update ───────────────────────────────────

    public function test_cannot_update_vocabulary_from_another_space(): void
    {
        Sanctum::actingAs($this->user);
        $otherSpace = Space::factory()->create(['slug' => 'other-space']);
        $otherVocab = Vocabulary::factory()->create(['space_id' => $otherSpace->id]);

        // Attacker sends their space header but targets another space's vocabulary ID
        $response = $this->putJson("/api/v1/taxonomies/{$otherVocab->id}", [
            'name' => 'Hijacked',
        ], ['X-Space' => 'test-space']);

        $response->assertNotFound();
        $this->assertDatabaseMissing('vocabularies', ['id' => $otherVocab->id, 'name' => 'Hijacked']);
    }

    public function test_cannot_delete_vocabulary_from_another_space(): void
    {
        Sanctum::actingAs($this->user);
        $otherSpace = Space::factory()->create(['slug' => 'other-space']);
        $otherVocab = Vocabulary::factory()->create(['space_id' => $otherSpace->id]);

        $response = $this->deleteJson("/api/v1/taxonomies/{$otherVocab->id}", [], [
            'X-Space' => 'test-space',
        ]);

        $response->assertNotFound();
        $this->assertDatabaseHas('vocabularies', ['id' => $otherVocab->id]);
    }

    // ─── PUT /api/v1/taxonomies/{id} ──────────────────────────────────────────

    public function test_update_vocabulary_requires_auth(): void
    {
        $vocab = Vocabulary::factory()->create(['space_id' => $this->space->id]);

        $response = $this->putJson("/api/v1/taxonomies/{$vocab->id}", [
            'name' => 'Updated',
        ], ['X-Space' => 'test-space']);

        $response->assertUnauthorized();
    }

    public function test_authenticated_user_can_update_vocabulary(): void
    {
        Sanctum::actingAs($this->user);
        $vocab = Vocabulary::factory()->create(['space_id' => $this->space->id]);

        $response = $this->putJson("/api/v1/taxonomies/{$vocab->id}", [
            'name' => 'Updated Name',
            'description' => 'New description',
        ], ['X-Space' => 'test-space']);

        $response->assertOk()
            ->assertJsonPath('data.name', 'Updated Name');

        $this->assertDatabaseHas('vocabularies', [
            'id' => $vocab->id,
            'name' => 'Updated Name',
        ]);
    }

    public function test_update_vocabulary_returns_404_for_missing_id(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->putJson('/api/v1/taxonomies/nonexistent', [
            'name' => 'X',
        ], ['X-Space' => 'test-space']);

        $response->assertNotFound();
    }

    // ─── DELETE /api/v1/taxonomies/{id} ───────────────────────────────────────

    public function test_delete_vocabulary_requires_auth(): void
    {
        $vocab = Vocabulary::factory()->create(['space_id' => $this->space->id]);

        $response = $this->deleteJson("/api/v1/taxonomies/{$vocab->id}", [], [
            'X-Space' => 'test-space',
        ]);

        $response->assertUnauthorized();
    }

    public function test_authenticated_user_can_delete_vocabulary(): void
    {
        Sanctum::actingAs($this->user);
        $vocab = Vocabulary::factory()->create(['space_id' => $this->space->id]);

        $response = $this->deleteJson("/api/v1/taxonomies/{$vocab->id}", [], [
            'X-Space' => 'test-space',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.deleted', true);

        $this->assertDatabaseMissing('vocabularies', ['id' => $vocab->id]);
    }

    public function test_deleting_vocabulary_cascades_to_terms(): void
    {
        Sanctum::actingAs($this->user);
        $vocab = Vocabulary::factory()->create(['space_id' => $this->space->id]);
        $term = TaxonomyTerm::factory()->create(['vocabulary_id' => $vocab->id]);

        $this->deleteJson("/api/v1/taxonomies/{$vocab->id}", [], ['X-Space' => 'test-space']);

        $this->assertDatabaseMissing('taxonomy_terms', ['id' => $term->id]);
    }

    // ─── GET /api/v1/taxonomies/{vocabSlug}/terms ─────────────────────────────

    public function test_list_terms_flat(): void
    {
        $vocab = Vocabulary::factory()->create([
            'space_id' => $this->space->id,
            'slug' => 'tags',
        ]);
        TaxonomyTerm::factory()->count(4)->create(['vocabulary_id' => $vocab->id]);

        $response = $this->getJson('/api/v1/taxonomies/tags/terms', ['X-Space' => 'test-space']);

        $response->assertOk()
            ->assertJsonCount(4, 'data');
    }

    public function test_list_terms_tree_format(): void
    {
        $vocab = Vocabulary::factory()->create([
            'space_id' => $this->space->id,
            'slug' => 'categories',
        ]);
        $root = TaxonomyTerm::factory()->create(['vocabulary_id' => $vocab->id]);
        TaxonomyTerm::factory()->create([
            'vocabulary_id' => $vocab->id,
            'parent_id' => $root->id,
        ]);

        $response = $this->getJson(
            '/api/v1/taxonomies/categories/terms?tree=1',
            ['X-Space' => 'test-space']
        );

        $response->assertOk()
            ->assertJsonCount(1, 'data'); // only root terms at top level
    }

    // ─── GET /api/v1/taxonomies/{vocabSlug}/terms/{termSlug} ─────────────────

    public function test_show_term_by_slug(): void
    {
        $vocab = Vocabulary::factory()->create([
            'space_id' => $this->space->id,
            'slug' => 'categories',
        ]);
        TaxonomyTerm::factory()->create([
            'vocabulary_id' => $vocab->id,
            'name' => 'Laravel',
            'slug' => 'laravel',
        ]);

        $response = $this->getJson(
            '/api/v1/taxonomies/categories/terms/laravel',
            ['X-Space' => 'test-space']
        );

        $response->assertOk()
            ->assertJsonPath('data.slug', 'laravel')
            ->assertJsonPath('data.name', 'Laravel');
    }

    public function test_show_term_returns_404_for_missing_slug(): void
    {
        $vocab = Vocabulary::factory()->create([
            'space_id' => $this->space->id,
            'slug' => 'categories',
        ]);

        $response = $this->getJson(
            '/api/v1/taxonomies/categories/terms/nonexistent',
            ['X-Space' => 'test-space']
        );

        $response->assertNotFound();
    }

    // ─── POST /api/v1/taxonomies/{vocabId}/terms ──────────────────────────────

    public function test_create_term_requires_auth(): void
    {
        $vocab = Vocabulary::factory()->create(['space_id' => $this->space->id]);

        $response = $this->postJson("/api/v1/taxonomies/{$vocab->id}/terms", [
            'name' => 'Laravel',
        ], ['X-Space' => 'test-space']);

        $response->assertUnauthorized();
    }

    public function test_authenticated_user_can_create_term(): void
    {
        Sanctum::actingAs($this->user);
        $vocab = Vocabulary::factory()->create(['space_id' => $this->space->id]);

        $response = $this->postJson("/api/v1/taxonomies/{$vocab->id}/terms", [
            'name' => 'Laravel',
            'sort_order' => 0,
        ], ['X-Space' => 'test-space']);

        $response->assertSuccessful()
            ->assertJsonPath('data.name', 'Laravel')
            ->assertJsonPath('data.slug', 'laravel');
    }

    public function test_cannot_create_term_in_another_spaces_vocabulary(): void
    {
        Sanctum::actingAs($this->user);
        $otherSpace = Space::factory()->create(['slug' => 'other-space']);
        $otherVocab = Vocabulary::factory()->create(['space_id' => $otherSpace->id]);

        $response = $this->postJson("/api/v1/taxonomies/{$otherVocab->id}/terms", [
            'name' => 'Injected',
        ], ['X-Space' => 'test-space']);

        // Vocabulary not found in the current space
        $response->assertNotFound();
    }

    public function test_create_term_validates_name_required(): void
    {
        Sanctum::actingAs($this->user);
        $vocab = Vocabulary::factory()->create(['space_id' => $this->space->id]);

        $response = $this->postJson("/api/v1/taxonomies/{$vocab->id}/terms", [],
            ['X-Space' => 'test-space']
        );

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    public function test_create_term_validates_parent_exists(): void
    {
        Sanctum::actingAs($this->user);
        $vocab = Vocabulary::factory()->create(['space_id' => $this->space->id]);

        $response = $this->postJson("/api/v1/taxonomies/{$vocab->id}/terms", [
            'name' => 'Child',
            'parent_id' => 'nonexistent-id',
        ], ['X-Space' => 'test-space']);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['parent_id']);
    }

    public function test_create_term_rejects_parent_from_different_vocabulary(): void
    {
        Sanctum::actingAs($this->user);
        $vocab = Vocabulary::factory()->create(['space_id' => $this->space->id]);
        $otherVocab = Vocabulary::factory()->create(['space_id' => $this->space->id]);
        $parentInOtherVocab = TaxonomyTerm::factory()->create(['vocabulary_id' => $otherVocab->id]);

        $response = $this->postJson("/api/v1/taxonomies/{$vocab->id}/terms", [
            'name' => 'Child',
            'parent_id' => $parentInOtherVocab->id,
        ], ['X-Space' => 'test-space']);

        $response->assertStatus(422);
    }

    // ─── PUT /api/v1/terms/{id} ───────────────────────────────────────────────

    public function test_update_term_requires_auth(): void
    {
        $vocab = Vocabulary::factory()->create(['space_id' => $this->space->id]);
        $term = TaxonomyTerm::factory()->create(['vocabulary_id' => $vocab->id]);

        $response = $this->putJson("/api/v1/terms/{$term->id}", [
            'name' => 'Updated',
        ], ['X-Space' => 'test-space']);

        $response->assertUnauthorized();
    }

    public function test_authenticated_user_can_update_term(): void
    {
        Sanctum::actingAs($this->user);
        $vocab = Vocabulary::factory()->create(['space_id' => $this->space->id]);
        $term = TaxonomyTerm::factory()->create(['vocabulary_id' => $vocab->id]);

        $response = $this->putJson("/api/v1/terms/{$term->id}", [
            'name' => 'Updated Name',
        ], ['X-Space' => 'test-space']);

        $response->assertOk()
            ->assertJsonPath('data.name', 'Updated Name');
    }

    public function test_cannot_update_term_from_another_space(): void
    {
        Sanctum::actingAs($this->user);
        $otherSpace = Space::factory()->create(['slug' => 'other-space']);
        $otherVocab = Vocabulary::factory()->create(['space_id' => $otherSpace->id]);
        $otherTerm = TaxonomyTerm::factory()->create(['vocabulary_id' => $otherVocab->id]);

        $response = $this->putJson("/api/v1/terms/{$otherTerm->id}", [
            'name' => 'Hijacked',
        ], ['X-Space' => 'test-space']);

        $response->assertNotFound();
        $this->assertDatabaseMissing('taxonomy_terms', ['id' => $otherTerm->id, 'name' => 'Hijacked']);
    }

    // ─── DELETE /api/v1/terms/{id} ────────────────────────────────────────────

    public function test_delete_term_requires_auth(): void
    {
        $vocab = Vocabulary::factory()->create(['space_id' => $this->space->id]);
        $term = TaxonomyTerm::factory()->create(['vocabulary_id' => $vocab->id]);

        $response = $this->deleteJson("/api/v1/terms/{$term->id}", [], [
            'X-Space' => 'test-space',
        ]);

        $response->assertUnauthorized();
    }

    public function test_authenticated_user_can_delete_term(): void
    {
        Sanctum::actingAs($this->user);
        $vocab = Vocabulary::factory()->create(['space_id' => $this->space->id]);
        $term = TaxonomyTerm::factory()->create(['vocabulary_id' => $vocab->id]);

        $response = $this->deleteJson("/api/v1/terms/{$term->id}", [], [
            'X-Space' => 'test-space',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.deleted', true);

        $this->assertDatabaseMissing('taxonomy_terms', ['id' => $term->id]);
    }

    public function test_delete_term_validates_child_strategy(): void
    {
        Sanctum::actingAs($this->user);
        $vocab = Vocabulary::factory()->create(['space_id' => $this->space->id]);
        $term = TaxonomyTerm::factory()->create(['vocabulary_id' => $vocab->id]);

        $response = $this->deleteJson("/api/v1/terms/{$term->id}", [
            'child_strategy' => 'invalid-strategy',
        ], ['X-Space' => 'test-space']);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['child_strategy']);
    }

    public function test_cannot_delete_term_from_another_space(): void
    {
        Sanctum::actingAs($this->user);
        $otherSpace = Space::factory()->create(['slug' => 'other-space']);
        $otherVocab = Vocabulary::factory()->create(['space_id' => $otherSpace->id]);
        $otherTerm = TaxonomyTerm::factory()->create(['vocabulary_id' => $otherVocab->id]);

        $response = $this->deleteJson("/api/v1/terms/{$otherTerm->id}", [], [
            'X-Space' => 'test-space',
        ]);

        $response->assertNotFound();
        $this->assertDatabaseHas('taxonomy_terms', ['id' => $otherTerm->id]);
    }

    // ─── POST /api/v1/terms/{id}/move ─────────────────────────────────────────

    public function test_move_term_requires_auth(): void
    {
        $vocab = Vocabulary::factory()->create(['space_id' => $this->space->id]);
        $term = TaxonomyTerm::factory()->create(['vocabulary_id' => $vocab->id]);

        $response = $this->postJson("/api/v1/terms/{$term->id}/move", [
            'parent_id' => null,
        ], ['X-Space' => 'test-space']);

        $response->assertUnauthorized();
    }

    public function test_authenticated_user_can_move_term(): void
    {
        Sanctum::actingAs($this->user);
        $vocab = Vocabulary::factory()->create(['space_id' => $this->space->id]);
        $newParent = TaxonomyTerm::factory()->create(['vocabulary_id' => $vocab->id]);
        $term = TaxonomyTerm::factory()->create(['vocabulary_id' => $vocab->id]);

        $response = $this->postJson("/api/v1/terms/{$term->id}/move", [
            'parent_id' => $newParent->id,
        ], ['X-Space' => 'test-space']);

        $response->assertOk()
            ->assertJsonPath('data.parent_id', $newParent->id);
    }

    public function test_move_term_rejects_parent_from_different_vocabulary(): void
    {
        Sanctum::actingAs($this->user);
        $vocab = Vocabulary::factory()->create(['space_id' => $this->space->id]);
        $otherVocab = Vocabulary::factory()->create(['space_id' => $this->space->id]);
        $term = TaxonomyTerm::factory()->create(['vocabulary_id' => $vocab->id]);
        $crossVocabParent = TaxonomyTerm::factory()->create(['vocabulary_id' => $otherVocab->id]);

        $response = $this->postJson("/api/v1/terms/{$term->id}/move", [
            'parent_id' => $crossVocabParent->id,
        ], ['X-Space' => 'test-space']);

        $response->assertStatus(422);
    }

    // ─── POST /api/v1/terms/reorder ───────────────────────────────────────────

    public function test_reorder_terms_requires_auth(): void
    {
        $response = $this->postJson('/api/v1/terms/reorder', [
            'ordering' => [],
        ], ['X-Space' => 'test-space']);

        $response->assertUnauthorized();
    }

    public function test_authenticated_user_can_reorder_terms(): void
    {
        Sanctum::actingAs($this->user);
        $vocab = Vocabulary::factory()->create(['space_id' => $this->space->id]);
        $term1 = TaxonomyTerm::factory()->create(['vocabulary_id' => $vocab->id, 'sort_order' => 0]);
        $term2 = TaxonomyTerm::factory()->create(['vocabulary_id' => $vocab->id, 'sort_order' => 1]);

        $response = $this->postJson('/api/v1/terms/reorder', [
            'ordering' => [
                $term1->id => 1,
                $term2->id => 0,
            ],
        ], ['X-Space' => 'test-space']);

        $response->assertOk()
            ->assertJsonPath('data.reordered', true);

        $this->assertEquals(1, $term1->fresh()->sort_order);
        $this->assertEquals(0, $term2->fresh()->sort_order);
    }

    public function test_cannot_reorder_terms_from_another_space(): void
    {
        Sanctum::actingAs($this->user);
        $otherSpace = Space::factory()->create(['slug' => 'other-space']);
        $otherVocab = Vocabulary::factory()->create(['space_id' => $otherSpace->id]);
        $otherTerm = TaxonomyTerm::factory()->create(['vocabulary_id' => $otherVocab->id, 'sort_order' => 0]);

        $response = $this->postJson('/api/v1/terms/reorder', [
            'ordering' => [$otherTerm->id => 99],
        ], ['X-Space' => 'test-space']);

        $response->assertForbidden();
        $this->assertEquals(0, $otherTerm->fresh()->sort_order);
    }
}
