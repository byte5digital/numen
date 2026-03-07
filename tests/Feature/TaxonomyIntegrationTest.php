<?php

namespace Tests\Feature;

use App\Models\Content;
use App\Models\ContentType;
use App\Models\Space;
use App\Models\TaxonomyTerm;
use App\Models\Vocabulary;
use App\Services\AI\LLMManager;
use App\Services\AI\LLMResponse;
use App\Services\Taxonomy\TaxonomyCategorizationService;
use App\Services\Taxonomy\TaxonomyService;
use Database\Seeders\TaxonomySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class TaxonomyIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private TaxonomyService $service;

    private Space $space;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(TaxonomyService::class);
        $this->space = Space::factory()->create();
    }

    // ─── Full flow integration ────────────────────────────────────────────────

    public function test_full_flow_create_vocab_terms_assign_to_content(): void
    {
        // 1. Create vocabulary
        $vocab = $this->service->createVocabulary($this->space->id, [
            'name' => 'Categories',
            'slug' => 'categories',
        ]);

        // 2. Create hierarchical terms
        $tech = $this->service->createTerm($vocab->id, [
            'name' => 'Technology',
            'sort_order' => 0,
        ]);
        $webDev = $this->service->createTerm($vocab->id, [
            'name' => 'Web Development',
            'parent_id' => $tech->id,
            'sort_order' => 0,
        ]);
        $php = $this->service->createTerm($vocab->id, [
            'name' => 'PHP',
            'parent_id' => $webDev->id,
            'sort_order' => 0,
        ]);

        // 3. Verify hierarchy
        $this->assertEquals(0, $tech->depth);
        $this->assertEquals(1, $webDev->depth);
        $this->assertEquals(2, $php->depth);
        $this->assertStringContainsString($tech->id, $webDev->path);
        $this->assertStringContainsString($webDev->id, $php->path);

        // 4. Assign terms to content
        $type = ContentType::factory()->create(['space_id' => $this->space->id]);
        $content = Content::factory()->published()->create([
            'space_id' => $this->space->id,
            'content_type_id' => $type->id,
        ]);

        $this->service->assignTerms($content, [
            ['term_id' => $php->id, 'sort_order' => 0],
        ]);

        // 5. Verify assignments
        $freshContent = $content->fresh();
        $this->assertCount(1, $freshContent->taxonomyTerms);
        $this->assertEquals($php->id, $freshContent->taxonomyTerms->first()->id);

        // 6. Verify content count updated
        $this->assertEquals(1, $php->fresh()->content_count);
    }

    public function test_full_flow_get_tree_structure(): void
    {
        $vocab = $this->service->createVocabulary($this->space->id, ['name' => 'Topics']);

        $root1 = $this->service->createTerm($vocab->id, ['name' => 'Tech', 'sort_order' => 0]);
        $root2 = $this->service->createTerm($vocab->id, ['name' => 'Business', 'sort_order' => 1]);
        $child1 = $this->service->createTerm($vocab->id, [
            'name' => 'PHP',
            'parent_id' => $root1->id,
            'sort_order' => 0,
        ]);
        $child2 = $this->service->createTerm($vocab->id, [
            'name' => 'Vue',
            'parent_id' => $root1->id,
            'sort_order' => 1,
        ]);
        $this->service->createTerm($vocab->id, [
            'name' => 'Laravel',
            'parent_id' => $child1->id,
            'sort_order' => 0,
        ]);

        $tree = $this->service->getTree($vocab->id);

        $this->assertCount(2, $tree);
        $techNode = $tree->firstWhere('id', $root1->id);
        $this->assertNotNull($techNode);
        $this->assertCount(2, $techNode->childrenRecursive);

        $phpNode = $techNode->childrenRecursive->firstWhere('id', $child1->id);
        $this->assertNotNull($phpNode);
        $this->assertCount(1, $phpNode->childrenRecursive);
    }

    // ─── Edge case: moving term updates all descendants ───────────────────────

    public function test_move_term_updates_all_descendant_paths(): void
    {
        $vocab = $this->service->createVocabulary($this->space->id, ['name' => 'Topics']);

        $parentA = $this->service->createTerm($vocab->id, ['name' => 'Parent A']);
        $parentB = $this->service->createTerm($vocab->id, ['name' => 'Parent B']);
        $child = $this->service->createTerm($vocab->id, [
            'name' => 'Child',
            'parent_id' => $parentA->id,
        ]);
        $grandchild = $this->service->createTerm($vocab->id, [
            'name' => 'Grandchild',
            'parent_id' => $child->id,
        ]);
        $greatGrandchild = $this->service->createTerm($vocab->id, [
            'name' => 'Great Grandchild',
            'parent_id' => $grandchild->id,
        ]);

        $this->service->moveTerm($child, $parentB->id);

        // Refresh all from DB
        $child->refresh();
        $grandchild->refresh();
        $greatGrandchild->refresh();

        // Child should have parentB in path
        $this->assertStringContainsString($parentB->id, $child->path);
        $this->assertStringNotContainsString($parentA->id, $child->path);

        // All descendants should have updated paths
        $this->assertStringContainsString($parentB->id, $grandchild->path);
        $this->assertStringContainsString($child->id, $grandchild->path);

        $this->assertStringContainsString($parentB->id, $greatGrandchild->path);
        $this->assertStringContainsString($child->id, $greatGrandchild->path);
        $this->assertStringContainsString($grandchild->id, $greatGrandchild->path);
    }

    // ─── Edge case: deleting vocabulary cascades ──────────────────────────────

    public function test_delete_vocabulary_cascades_to_terms_and_pivot(): void
    {
        $vocab = $this->service->createVocabulary($this->space->id, ['name' => 'Tags']);
        $term1 = $this->service->createTerm($vocab->id, ['name' => 'PHP']);
        $term2 = $this->service->createTerm($vocab->id, ['name' => 'Laravel']);

        $type = ContentType::factory()->create(['space_id' => $this->space->id]);
        $content = Content::factory()->published()->create([
            'space_id' => $this->space->id,
            'content_type_id' => $type->id,
        ]);

        $this->service->assignTerms($content, [['term_id' => $term1->id]]);

        // Delete the vocabulary
        $this->service->deleteVocabulary($vocab);

        $this->assertDatabaseMissing('vocabularies', ['id' => $vocab->id]);
        $this->assertDatabaseMissing('taxonomy_terms', ['id' => $term1->id]);
        $this->assertDatabaseMissing('taxonomy_terms', ['id' => $term2->id]);
        $this->assertDatabaseMissing('content_taxonomy', ['term_id' => $term1->id]);
    }

    // ─── Edge case: deleting parent term nullifies children's parent_id ───────

    public function test_delete_parent_term_reparent_moves_children_to_grandparent(): void
    {
        $vocab = $this->service->createVocabulary($this->space->id, ['name' => 'Topics']);
        $grandparent = $this->service->createTerm($vocab->id, ['name' => 'Root']);
        $parent = $this->service->createTerm($vocab->id, [
            'name' => 'Parent',
            'parent_id' => $grandparent->id,
        ]);
        $child = $this->service->createTerm($vocab->id, [
            'name' => 'Child',
            'parent_id' => $parent->id,
        ]);

        $this->service->deleteTerm($parent, 'reparent');

        $this->assertDatabaseMissing('taxonomy_terms', ['id' => $parent->id]);
        $this->assertEquals($grandparent->id, $child->fresh()->parent_id);
    }

    public function test_delete_root_term_with_reparent_nullifies_children_parent(): void
    {
        $vocab = $this->service->createVocabulary($this->space->id, ['name' => 'Topics']);
        $root = $this->service->createTerm($vocab->id, ['name' => 'Root']);
        $child = $this->service->createTerm($vocab->id, [
            'name' => 'Child',
            'parent_id' => $root->id,
        ]);

        $this->service->deleteTerm($root, 'reparent');

        // Root had no parent, so child should now have null parent_id
        $this->assertNull($child->fresh()->parent_id);
    }

    // ─── Edge case: duplicate slug handling ───────────────────────────────────

    public function test_duplicate_slug_in_same_vocabulary_gets_incremented(): void
    {
        $vocab = $this->service->createVocabulary($this->space->id, ['name' => 'Topics']);

        $term1 = $this->service->createTerm($vocab->id, ['name' => 'PHP']);
        $term2 = $this->service->createTerm($vocab->id, ['name' => 'PHP']); // same name
        $term3 = $this->service->createTerm($vocab->id, ['name' => 'PHP']); // same again

        $this->assertEquals('php', $term1->slug);
        $this->assertEquals('php-1', $term2->slug);
        $this->assertEquals('php-2', $term3->slug);
    }

    public function test_duplicate_slug_across_vocabularies_is_allowed(): void
    {
        $vocab1 = $this->service->createVocabulary($this->space->id, ['name' => 'Categories']);
        $vocab2 = $this->service->createVocabulary($this->space->id, ['name' => 'Tags']);

        $term1 = $this->service->createTerm($vocab1->id, ['name' => 'Laravel']);
        $term2 = $this->service->createTerm($vocab2->id, ['name' => 'Laravel']);

        $this->assertEquals('laravel', $term1->slug);
        $this->assertEquals('laravel', $term2->slug);
    }

    // ─── Edge case: content count cache accuracy ──────────────────────────────

    public function test_content_count_is_accurate_after_assign_and_unassign(): void
    {
        $vocab = $this->service->createVocabulary($this->space->id, ['name' => 'Tags']);
        $term = $this->service->createTerm($vocab->id, ['name' => 'Laravel']);

        $type = ContentType::factory()->create(['space_id' => $this->space->id]);
        $content1 = Content::factory()->published()->create([
            'space_id' => $this->space->id,
            'content_type_id' => $type->id,
        ]);
        $content2 = Content::factory()->published()->create([
            'space_id' => $this->space->id,
            'content_type_id' => $type->id,
        ]);

        $this->service->assignTerms($content1, [['term_id' => $term->id]]);
        $this->service->assignTerms($content2, [['term_id' => $term->id]]);
        $this->assertEquals(2, $term->fresh()->content_count);

        $this->service->removeTerms($content1, [$term->id]);
        $this->assertEquals(1, $term->fresh()->content_count);

        $this->service->removeTerms($content2, [$term->id]);
        $this->assertEquals(0, $term->fresh()->content_count);
    }

    public function test_sync_terms_recalculates_content_counts(): void
    {
        $vocab = $this->service->createVocabulary($this->space->id, ['name' => 'Tags']);
        $term1 = $this->service->createTerm($vocab->id, ['name' => 'PHP']);
        $term2 = $this->service->createTerm($vocab->id, ['name' => 'Laravel']);

        $type = ContentType::factory()->create(['space_id' => $this->space->id]);
        $content = Content::factory()->published()->create([
            'space_id' => $this->space->id,
            'content_type_id' => $type->id,
        ]);

        $this->service->assignTerms($content, [['term_id' => $term1->id]]);
        $this->assertEquals(1, $term1->fresh()->content_count);

        // Sync to only term2 — removes term1
        $this->service->syncTerms($content, [$term2->id]);

        $this->assertEquals(0, $term1->fresh()->content_count);
        $this->assertEquals(1, $term2->fresh()->content_count);
    }

    // ─── Content model scopes ─────────────────────────────────────────────────

    public function test_scope_in_term_filters_content(): void
    {
        $vocab = $this->service->createVocabulary($this->space->id, ['name' => 'Tags']);
        $laravelTerm = $this->service->createTerm($vocab->id, ['name' => 'Laravel']);
        $phpTerm = $this->service->createTerm($vocab->id, ['name' => 'PHP']);

        $type = ContentType::factory()->create(['space_id' => $this->space->id]);
        $laravelContent = Content::factory()->published()->create([
            'space_id' => $this->space->id,
            'content_type_id' => $type->id,
        ]);
        $phpContent = Content::factory()->published()->create([
            'space_id' => $this->space->id,
            'content_type_id' => $type->id,
        ]);

        $this->service->assignTerms($laravelContent, [['term_id' => $laravelTerm->id]]);
        $this->service->assignTerms($phpContent, [['term_id' => $phpTerm->id]]);

        $results = Content::inTerm($laravelTerm->id)->get();

        $this->assertCount(1, $results);
        $this->assertEquals($laravelContent->id, $results->first()->id);
    }

    public function test_scope_in_taxonomy_filters_by_vocab_and_term_slug(): void
    {
        $vocab = $this->service->createVocabulary($this->space->id, [
            'name' => 'Categories',
            'slug' => 'categories',
        ]);
        $term = $this->service->createTerm($vocab->id, ['name' => 'Laravel', 'slug' => 'laravel']);

        $type = ContentType::factory()->create(['space_id' => $this->space->id]);
        $content = Content::factory()->published()->create([
            'space_id' => $this->space->id,
            'content_type_id' => $type->id,
        ]);

        $this->service->assignTerms($content, [['term_id' => $term->id]]);

        $results = Content::inTaxonomy('categories', 'laravel')->get();

        $this->assertCount(1, $results);
        $this->assertEquals($content->id, $results->first()->id);
    }

    // ─── AI categorization integration ───────────────────────────────────────

    public function test_auto_assign_with_mocked_llm_assigns_terms_above_threshold(): void
    {
        $vocab = $this->service->createVocabulary($this->space->id, ['name' => 'Tags']);
        $laravelTerm = $this->service->createTerm($vocab->id, ['name' => 'Laravel']);
        $phpTerm = $this->service->createTerm($vocab->id, ['name' => 'PHP']);

        $type = ContentType::factory()->create(['space_id' => $this->space->id]);
        $content = Content::factory()->published()->create([
            'space_id' => $this->space->id,
            'content_type_id' => $type->id,
        ]);

        // Mock LLM response with confidence scores
        $llmResponse = new LLMResponse(
            content: json_encode([
                ['term_id' => $laravelTerm->id, 'confidence' => 0.92],
                ['term_id' => $phpTerm->id, 'confidence' => 0.60],
            ]),
            model: 'claude-haiku', provider: 'anthropic',
            inputTokens: 100, outputTokens: 50, costUsd: 0.001, latencyMs: 200
        );

        $mockLlm = Mockery::mock(LLMManager::class);
        $mockLlm->shouldReceive('complete')->once()->andReturn($llmResponse);

        $categorizationService = new TaxonomyCategorizationService($mockLlm, $this->service);

        // Only assign terms above 0.7 threshold
        $assigned = $categorizationService->autoAssign($content, 0.7);

        $this->assertCount(1, $assigned);
        $this->assertEquals($laravelTerm->id, $assigned[0]['term']->id);
        $this->assertEquals(0.92, $assigned[0]['confidence']);

        // Verify in DB
        $this->assertDatabaseHas('content_taxonomy', [
            'content_id' => $content->id,
            'term_id' => $laravelTerm->id,
            'auto_assigned' => true,
            'confidence' => 0.92,
        ]);

        // PHP should NOT be assigned (below threshold)
        $this->assertDatabaseMissing('content_taxonomy', [
            'content_id' => $content->id,
            'term_id' => $phpTerm->id,
        ]);
    }

    public function test_auto_assign_respects_max_terms_config(): void
    {
        config(['numen.taxonomy.auto_assign_max_terms' => 2]);

        $vocab = $this->service->createVocabulary($this->space->id, ['name' => 'Tags']);
        $terms = [];
        for ($i = 0; $i < 5; $i++) {
            $terms[] = $this->service->createTerm($vocab->id, ['name' => "Term {$i}"]);
        }

        $type = ContentType::factory()->create(['space_id' => $this->space->id]);
        $content = Content::factory()->published()->create([
            'space_id' => $this->space->id,
            'content_type_id' => $type->id,
        ]);

        $llmResponse = new LLMResponse(
            content: json_encode(array_map(
                fn ($t): array => ['term_id' => $t->id, 'confidence' => 0.9],
                $terms
            )),
            model: 'claude-haiku', provider: 'anthropic',
            inputTokens: 100, outputTokens: 50, costUsd: 0.001, latencyMs: 200
        );

        $mockLlm = Mockery::mock(LLMManager::class);
        $mockLlm->shouldReceive('complete')->once()->andReturn($llmResponse);

        $categorizationService = new TaxonomyCategorizationService($mockLlm, $this->service);
        $assigned = $categorizationService->autoAssign($content, 0.7);

        $this->assertCount(2, $assigned);
        $this->assertCount(2, $content->fresh()->taxonomyTerms);
    }

    public function test_auto_assign_with_json_in_markdown_fences(): void
    {
        $vocab = $this->service->createVocabulary($this->space->id, ['name' => 'Tags']);
        $term = $this->service->createTerm($vocab->id, ['name' => 'PHP']);

        $type = ContentType::factory()->create(['space_id' => $this->space->id]);
        $content = Content::factory()->published()->create([
            'space_id' => $this->space->id,
            'content_type_id' => $type->id,
        ]);

        // LLM response wrapped in markdown fences (common pattern)
        $llmResponse = new LLMResponse(
            content: "Here are the categories:\n```json\n[{\"term_id\": \"{$term->id}\", \"confidence\": 0.85}]\n```",
            model: 'claude-haiku', provider: 'anthropic',
            inputTokens: 100, outputTokens: 50, costUsd: 0.001, latencyMs: 200
        );

        $mockLlm = Mockery::mock(LLMManager::class);
        $mockLlm->shouldReceive('complete')->once()->andReturn($llmResponse);

        $categorizationService = new TaxonomyCategorizationService($mockLlm, $this->service);
        $assigned = $categorizationService->autoAssign($content, 0.7);

        $this->assertCount(1, $assigned);
        $this->assertEquals($term->id, $assigned[0]['term']->id);
    }

    public function test_auto_assign_gracefully_handles_llm_failure(): void
    {
        $vocab = $this->service->createVocabulary($this->space->id, ['name' => 'Tags']);
        $this->service->createTerm($vocab->id, ['name' => 'PHP']);

        $type = ContentType::factory()->create(['space_id' => $this->space->id]);
        $content = Content::factory()->published()->create([
            'space_id' => $this->space->id,
            'content_type_id' => $type->id,
        ]);

        $mockLlm = Mockery::mock(LLMManager::class);
        $mockLlm->shouldReceive('complete')->once()->andThrow(new \RuntimeException('LLM unavailable'));

        $categorizationService = new TaxonomyCategorizationService($mockLlm, $this->service);
        $assigned = $categorizationService->autoAssign($content, 0.7);

        $this->assertEmpty($assigned);
        $this->assertCount(0, $content->fresh()->taxonomyTerms);
    }

    public function test_suggest_terms_returns_empty_when_no_vocabularies(): void
    {
        $type = ContentType::factory()->create(['space_id' => $this->space->id]);
        $content = Content::factory()->published()->create([
            'space_id' => $this->space->id,
            'content_type_id' => $type->id,
        ]);

        // No vocabularies for this space
        $mockLlm = Mockery::mock(LLMManager::class);
        $mockLlm->shouldNotReceive('complete');

        $categorizationService = new TaxonomyCategorizationService($mockLlm, $this->service);
        $suggestions = $categorizationService->suggestTerms($content);

        $this->assertEmpty($suggestions);
    }

    // ─── Seeder integration ───────────────────────────────────────────────────

    public function test_taxonomy_seeder_creates_default_vocabularies(): void
    {
        $space = Space::factory()->create();

        $seeder = new TaxonomySeeder;
        $seeder->run();

        $this->assertDatabaseHas('vocabularies', [
            'space_id' => $space->id,
            'slug' => 'categories',
            'name' => 'Categories',
        ]);

        $this->assertDatabaseHas('vocabularies', [
            'space_id' => $space->id,
            'slug' => 'tags',
            'name' => 'Tags',
        ]);
    }

    public function test_taxonomy_seeder_creates_default_terms(): void
    {
        $space = Space::factory()->create();

        $seeder = new TaxonomySeeder;
        $seeder->run();

        $vocab = Vocabulary::where('space_id', $space->id)
            ->where('slug', 'categories')
            ->first();

        $this->assertNotNull($vocab);
        $this->assertDatabaseHas('taxonomy_terms', [
            'vocabulary_id' => $vocab->id,
            'slug' => 'technology',
        ]);
        $this->assertDatabaseHas('taxonomy_terms', [
            'vocabulary_id' => $vocab->id,
            'slug' => 'web-development',
        ]);
    }

    public function test_taxonomy_seeder_creates_hierarchical_terms(): void
    {
        $space = Space::factory()->create();

        $seeder = new TaxonomySeeder;
        $seeder->run();

        $vocab = Vocabulary::where('space_id', $space->id)->where('slug', 'categories')->first();
        $this->assertNotNull($vocab);

        $technology = TaxonomyTerm::where('vocabulary_id', $vocab->id)
            ->where('slug', 'technology')
            ->first();

        $webDev = TaxonomyTerm::where('vocabulary_id', $vocab->id)
            ->where('slug', 'web-development')
            ->first();

        $this->assertNotNull($technology);
        $this->assertNotNull($webDev);
        $this->assertEquals($technology->id, $webDev->parent_id);
        $this->assertEquals(1, $webDev->depth);
    }

    public function test_taxonomy_seeder_creates_default_tags(): void
    {
        $space = Space::factory()->create();

        $seeder = new TaxonomySeeder;
        $seeder->run();

        $tags = Vocabulary::where('space_id', $space->id)->where('slug', 'tags')->first();
        $this->assertNotNull($tags);

        $tagSlugs = ['tutorial', 'getting-started', 'laravel', 'php', 'best-practices'];
        foreach ($tagSlugs as $slug) {
            $this->assertDatabaseHas('taxonomy_terms', [
                'vocabulary_id' => $tags->id,
                'slug' => $slug,
            ]);
        }
    }

    public function test_taxonomy_seeder_is_idempotent(): void
    {
        $space = Space::factory()->create();

        $seeder = new TaxonomySeeder;
        $seeder->run();
        $seeder->run(); // Run twice

        // Should only have one 'categories' vocabulary per space
        $this->assertEquals(
            1,
            Vocabulary::where('space_id', $space->id)->where('slug', 'categories')->count()
        );
    }

    public function test_taxonomy_seeder_seeds_all_spaces(): void
    {
        $space2 = Space::factory()->create();

        $seeder = new TaxonomySeeder;
        $seeder->run();

        // Both spaces should have vocabularies
        $this->assertDatabaseHas('vocabularies', [
            'space_id' => $space2->id,
            'slug' => 'categories',
        ]);
    }

    // ─── recalculateContentCount with ancestor propagation ───────────────────

    public function test_recalculate_content_count_propagates_to_ancestors(): void
    {
        $vocab = $this->service->createVocabulary($this->space->id, ['name' => 'Topics']);
        $root = $this->service->createTerm($vocab->id, ['name' => 'Root']);
        $child = $this->service->createTerm($vocab->id, [
            'name' => 'Child',
            'parent_id' => $root->id,
        ]);

        $type = ContentType::factory()->create(['space_id' => $this->space->id]);
        $content = Content::factory()->published()->create([
            'space_id' => $this->space->id,
            'content_type_id' => $type->id,
        ]);

        $child->contents()->attach($content->id, ['sort_order' => 0, 'auto_assigned' => false]);

        $this->service->recalculateContentCount($child);

        // Child should reflect its own count
        $this->assertEquals(1, $child->fresh()->content_count);
    }
}
