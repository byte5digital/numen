<?php

namespace Tests\Feature;

use App\Models\Content;
use App\Models\ContentType;
use App\Models\Space;
use App\Models\TaxonomyTerm;
use App\Models\User;
use App\Models\Vocabulary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ContentTaxonomyApiTest extends TestCase
{
    use RefreshDatabase;

    private Space $space;

    private Content $content;

    private Vocabulary $vocab;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->space = Space::factory()->create(['slug' => 'test-space']);
        $this->user = User::factory()->create();

        $type = ContentType::factory()->create(['space_id' => $this->space->id]);

        $this->content = Content::factory()->published()->create([
            'space_id' => $this->space->id,
            'content_type_id' => $type->id,
            'slug' => 'my-article',
        ]);

        $this->vocab = Vocabulary::factory()->create([
            'space_id' => $this->space->id,
            'slug' => 'tags',
        ]);
    }

    // ─── GET /api/v1/content/{slug}/terms ────────────────────────────────────

    public function test_list_content_terms_is_public(): void
    {
        $term = TaxonomyTerm::factory()->create(['vocabulary_id' => $this->vocab->id]);
        $this->content->taxonomyTerms()->attach($term->id, [
            'sort_order' => 0,
            'auto_assigned' => false,
        ]);

        $response = $this->getJson('/api/v1/content/my-article/terms');

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_list_content_terms_returns_correct_structure(): void
    {
        $term = TaxonomyTerm::factory()->create([
            'vocabulary_id' => $this->vocab->id,
            'name' => 'Laravel',
            'slug' => 'laravel',
        ]);
        $this->content->taxonomyTerms()->attach($term->id, [
            'sort_order' => 0,
            'auto_assigned' => false,
        ]);

        $response = $this->getJson('/api/v1/content/my-article/terms');

        $response->assertOk()
            ->assertJsonPath('data.0.slug', 'laravel')
            ->assertJsonStructure(['data' => [['id', 'name', 'slug', 'vocabulary_id']]]);
    }

    public function test_list_terms_returns_empty_for_content_with_no_terms(): void
    {
        $response = $this->getJson('/api/v1/content/my-article/terms');

        $response->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_list_content_terms_returns_404_for_unpublished_content(): void
    {
        $type = ContentType::factory()->create(['space_id' => $this->space->id]);
        Content::factory()->create([
            'space_id' => $this->space->id,
            'content_type_id' => $type->id,
            'slug' => 'draft-content',
            'status' => 'draft',
        ]);

        $response = $this->getJson('/api/v1/content/draft-content/terms');

        $response->assertNotFound();
    }

    // ─── POST /api/v1/content/{id}/terms ─────────────────────────────────────

    public function test_assign_terms_requires_auth(): void
    {
        $term = TaxonomyTerm::factory()->create(['vocabulary_id' => $this->vocab->id]);

        $response = $this->postJson("/api/v1/content/{$this->content->id}/terms", [
            'assignments' => [['term_id' => $term->id]],
        ], ['X-Space' => 'test-space']);

        $response->assertUnauthorized();
    }

    public function test_authenticated_user_can_assign_terms(): void
    {
        Sanctum::actingAs($this->user);
        $term = TaxonomyTerm::factory()->create(['vocabulary_id' => $this->vocab->id]);

        $response = $this->postJson("/api/v1/content/{$this->content->id}/terms", [
            'assignments' => [
                ['term_id' => $term->id, 'sort_order' => 0],
            ],
        ], ['X-Space' => 'test-space']);

        $response->assertOk()
            ->assertJsonPath('data.assigned', true);

        $this->assertDatabaseHas('content_taxonomy', [
            'content_id' => $this->content->id,
            'term_id' => $term->id,
        ]);
    }

    public function test_assign_terms_with_confidence_score(): void
    {
        Sanctum::actingAs($this->user);
        $term = TaxonomyTerm::factory()->create(['vocabulary_id' => $this->vocab->id]);

        $this->postJson("/api/v1/content/{$this->content->id}/terms", [
            'assignments' => [
                [
                    'term_id' => $term->id,
                    'auto_assigned' => true,
                    'confidence' => 0.87,
                ],
            ],
        ], ['X-Space' => 'test-space']);

        $this->assertDatabaseHas('content_taxonomy', [
            'content_id' => $this->content->id,
            'term_id' => $term->id,
            'auto_assigned' => true,
            'confidence' => 0.87,
        ]);
    }

    public function test_assign_terms_validates_term_exists(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson("/api/v1/content/{$this->content->id}/terms", [
            'assignments' => [
                ['term_id' => 'nonexistent-term-id'],
            ],
        ], ['X-Space' => 'test-space']);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['assignments.0.term_id']);
    }

    public function test_assign_terms_validates_confidence_range(): void
    {
        Sanctum::actingAs($this->user);
        $term = TaxonomyTerm::factory()->create(['vocabulary_id' => $this->vocab->id]);

        $response = $this->postJson("/api/v1/content/{$this->content->id}/terms", [
            'assignments' => [
                ['term_id' => $term->id, 'confidence' => 1.5],
            ],
        ], ['X-Space' => 'test-space']);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['assignments.0.confidence']);
    }

    public function test_assign_terms_is_additive(): void
    {
        Sanctum::actingAs($this->user);
        $term1 = TaxonomyTerm::factory()->create(['vocabulary_id' => $this->vocab->id]);
        $term2 = TaxonomyTerm::factory()->create(['vocabulary_id' => $this->vocab->id]);

        $this->postJson("/api/v1/content/{$this->content->id}/terms", [
            'assignments' => [['term_id' => $term1->id]],
        ], ['X-Space' => 'test-space']);

        $this->postJson("/api/v1/content/{$this->content->id}/terms", [
            'assignments' => [['term_id' => $term2->id]],
        ], ['X-Space' => 'test-space']);

        $this->assertCount(2, $this->content->fresh()->taxonomyTerms);
    }

    // ─── Security: Cross-Space Term Assignment ────────────────────────────────

    public function test_cannot_assign_terms_from_another_space(): void
    {
        Sanctum::actingAs($this->user);
        $otherSpace = Space::factory()->create(['slug' => 'other-space']);
        $otherVocab = Vocabulary::factory()->create(['space_id' => $otherSpace->id]);
        $otherTerm = TaxonomyTerm::factory()->create(['vocabulary_id' => $otherVocab->id]);

        $response = $this->postJson("/api/v1/content/{$this->content->id}/terms", [
            'assignments' => [['term_id' => $otherTerm->id]],
        ], ['X-Space' => 'test-space']);

        $response->assertStatus(422);
        $this->assertDatabaseMissing('content_taxonomy', [
            'content_id' => $this->content->id,
            'term_id' => $otherTerm->id,
        ]);
    }

    public function test_cannot_assign_terms_to_content_from_another_space(): void
    {
        Sanctum::actingAs($this->user);
        $otherSpace = Space::factory()->create(['slug' => 'other-space']);
        $otherType = ContentType::factory()->create(['space_id' => $otherSpace->id]);
        $otherContent = Content::factory()->published()->create([
            'space_id' => $otherSpace->id,
            'content_type_id' => $otherType->id,
        ]);
        $term = TaxonomyTerm::factory()->create(['vocabulary_id' => $this->vocab->id]);

        // Attack: use test-space token but target other space's content ID
        $response = $this->postJson("/api/v1/content/{$otherContent->id}/terms", [
            'assignments' => [['term_id' => $term->id]],
        ], ['X-Space' => 'test-space']);

        $response->assertNotFound();
    }

    // ─── PUT /api/v1/content/{id}/terms ──────────────────────────────────────

    public function test_sync_terms_requires_auth(): void
    {
        $response = $this->putJson("/api/v1/content/{$this->content->id}/terms", [
            'term_ids' => [],
        ], ['X-Space' => 'test-space']);

        $response->assertUnauthorized();
    }

    public function test_authenticated_user_can_sync_terms(): void
    {
        Sanctum::actingAs($this->user);
        $term1 = TaxonomyTerm::factory()->create(['vocabulary_id' => $this->vocab->id]);
        $term2 = TaxonomyTerm::factory()->create(['vocabulary_id' => $this->vocab->id]);

        // Assign term1 initially
        $this->content->taxonomyTerms()->attach($term1->id, ['sort_order' => 0, 'auto_assigned' => false]);

        // Sync with term2 only
        $response = $this->putJson("/api/v1/content/{$this->content->id}/terms", [
            'term_ids' => [$term2->id],
        ], ['X-Space' => 'test-space']);

        $response->assertOk()
            ->assertJsonPath('data.synced', true);

        $terms = $this->content->fresh()->taxonomyTerms;
        $this->assertCount(1, $terms);
        $this->assertEquals($term2->id, $terms->first()->id);
    }

    public function test_sync_terms_can_clear_all_assignments(): void
    {
        Sanctum::actingAs($this->user);
        $term = TaxonomyTerm::factory()->create(['vocabulary_id' => $this->vocab->id]);
        $this->content->taxonomyTerms()->attach($term->id, ['sort_order' => 0, 'auto_assigned' => false]);

        $this->putJson("/api/v1/content/{$this->content->id}/terms", [
            'term_ids' => [],
        ], ['X-Space' => 'test-space']);

        $this->assertCount(0, $this->content->fresh()->taxonomyTerms);
    }

    public function test_cannot_sync_terms_from_another_space(): void
    {
        Sanctum::actingAs($this->user);
        $otherSpace = Space::factory()->create(['slug' => 'other-space']);
        $otherVocab = Vocabulary::factory()->create(['space_id' => $otherSpace->id]);
        $otherTerm = TaxonomyTerm::factory()->create(['vocabulary_id' => $otherVocab->id]);

        $response = $this->putJson("/api/v1/content/{$this->content->id}/terms", [
            'term_ids' => [$otherTerm->id],
        ], ['X-Space' => 'test-space']);

        $response->assertStatus(422);
    }

    // ─── DELETE /api/v1/content/{id}/terms/{termId} ───────────────────────────

    public function test_remove_term_requires_auth(): void
    {
        $term = TaxonomyTerm::factory()->create(['vocabulary_id' => $this->vocab->id]);

        $response = $this->deleteJson(
            "/api/v1/content/{$this->content->id}/terms/{$term->id}",
            [],
            ['X-Space' => 'test-space']
        );

        $response->assertUnauthorized();
    }

    public function test_authenticated_user_can_remove_term(): void
    {
        Sanctum::actingAs($this->user);
        $term = TaxonomyTerm::factory()->create(['vocabulary_id' => $this->vocab->id]);
        $this->content->taxonomyTerms()->attach($term->id, ['sort_order' => 0, 'auto_assigned' => false]);

        $response = $this->deleteJson(
            "/api/v1/content/{$this->content->id}/terms/{$term->id}",
            [],
            ['X-Space' => 'test-space']
        );

        $response->assertOk()
            ->assertJsonPath('data.removed', true);

        $this->assertDatabaseMissing('content_taxonomy', [
            'content_id' => $this->content->id,
            'term_id' => $term->id,
        ]);
    }

    public function test_remove_term_is_idempotent(): void
    {
        Sanctum::actingAs($this->user);
        $term = TaxonomyTerm::factory()->create(['vocabulary_id' => $this->vocab->id]);

        // Remove a term that was never assigned — should not throw
        $response = $this->deleteJson(
            "/api/v1/content/{$this->content->id}/terms/{$term->id}",
            [],
            ['X-Space' => 'test-space']
        );

        $response->assertOk();
    }

    public function test_cannot_remove_term_from_content_in_another_space(): void
    {
        Sanctum::actingAs($this->user);
        $otherSpace = Space::factory()->create(['slug' => 'other-space']);
        $otherType = ContentType::factory()->create(['space_id' => $otherSpace->id]);
        $otherContent = Content::factory()->published()->create([
            'space_id' => $otherSpace->id,
            'content_type_id' => $otherType->id,
        ]);
        $term = TaxonomyTerm::factory()->create(['vocabulary_id' => $this->vocab->id]);

        $response = $this->deleteJson(
            "/api/v1/content/{$otherContent->id}/terms/{$term->id}",
            [],
            ['X-Space' => 'test-space']
        );

        $response->assertNotFound();
    }

    // ─── POST /api/v1/content/{id}/auto-categorize ───────────────────────────

    public function test_auto_categorize_requires_auth(): void
    {
        $response = $this->postJson(
            "/api/v1/content/{$this->content->id}/auto-categorize",
            [],
            ['X-Space' => 'test-space']
        );

        $response->assertUnauthorized();
    }

    public function test_authenticated_user_can_trigger_auto_categorize(): void
    {
        Queue::fake();
        Sanctum::actingAs($this->user);

        $response = $this->postJson(
            "/api/v1/content/{$this->content->id}/auto-categorize",
            [],
            ['X-Space' => 'test-space']
        );

        $response->assertOk()
            ->assertJsonPath('data.queued', true)
            ->assertJsonPath('data.content_id', $this->content->id);

        Queue::assertPushed(\App\Jobs\CategorizePipelineContent::class);
    }

    public function test_cannot_auto_categorize_content_from_another_space(): void
    {
        Queue::fake();
        Sanctum::actingAs($this->user);
        $otherSpace = Space::factory()->create(['slug' => 'other-space']);
        $otherType = ContentType::factory()->create(['space_id' => $otherSpace->id]);
        $otherContent = Content::factory()->published()->create([
            'space_id' => $otherSpace->id,
            'content_type_id' => $otherType->id,
        ]);

        $response = $this->postJson(
            "/api/v1/content/{$otherContent->id}/auto-categorize",
            [],
            ['X-Space' => 'test-space']
        );

        $response->assertNotFound();
        Queue::assertNothingPushed();
    }

    // ─── GET /api/v1/taxonomies/{vocabSlug}/terms/{termSlug}/content ─────────

    public function test_term_content_listing_is_public(): void
    {
        $vocab = Vocabulary::factory()->create([
            'space_id' => $this->space->id,
            'slug' => 'categories',
        ]);
        $term = TaxonomyTerm::factory()->create([
            'vocabulary_id' => $vocab->id,
            'slug' => 'laravel',
        ]);
        $this->content->taxonomyTerms()->attach($term->id, ['sort_order' => 0, 'auto_assigned' => false]);

        $response = $this->getJson(
            '/api/v1/taxonomies/categories/terms/laravel/content',
            ['X-Space' => 'test-space']
        );

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_term_content_listing_paginates(): void
    {
        $vocab = Vocabulary::factory()->create([
            'space_id' => $this->space->id,
            'slug' => 'categories',
        ]);
        $term = TaxonomyTerm::factory()->create([
            'vocabulary_id' => $vocab->id,
            'slug' => 'php',
        ]);
        $type = ContentType::factory()->create(['space_id' => $this->space->id]);

        // Create 5 published content items assigned to this term
        Content::factory()->published()->count(5)->create([
            'space_id' => $this->space->id,
            'content_type_id' => $type->id,
        ])->each(function (Content $c) use ($term): void {
            $c->taxonomyTerms()->attach($term->id, ['sort_order' => 0, 'auto_assigned' => false]);
        });

        $response = $this->getJson(
            '/api/v1/taxonomies/categories/terms/php/content?per_page=2',
            ['X-Space' => 'test-space']
        );

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure(['meta' => ['total', 'per_page']]);
    }
}
