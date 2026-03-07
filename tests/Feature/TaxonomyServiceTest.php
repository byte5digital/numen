<?php

namespace Tests\Feature;

use App\Models\Content;
use App\Models\ContentType;
use App\Models\Space;
use App\Models\TaxonomyTerm;
use App\Models\Vocabulary;
use App\Services\Taxonomy\TaxonomyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaxonomyServiceTest extends TestCase
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

    // ─── createVocabulary ─────────────────────────────────────────────────────

    public function test_create_vocabulary_with_explicit_slug(): void
    {
        $vocab = $this->service->createVocabulary($this->space->id, [
            'name' => 'Categories',
            'slug' => 'categories',
        ]);

        $this->assertInstanceOf(Vocabulary::class, $vocab);
        $this->assertEquals('Categories', $vocab->name);
        $this->assertEquals('categories', $vocab->slug);
        $this->assertEquals($this->space->id, $vocab->space_id);
    }

    public function test_create_vocabulary_auto_generates_slug_from_name(): void
    {
        $vocab = $this->service->createVocabulary($this->space->id, [
            'name' => 'My Blog Tags',
        ]);

        $this->assertEquals('my-blog-tags', $vocab->slug);
    }

    public function test_create_vocabulary_generates_unique_slug_for_duplicates(): void
    {
        $this->service->createVocabulary($this->space->id, ['name' => 'Tags']);
        $second = $this->service->createVocabulary($this->space->id, ['name' => 'Tags']);

        $this->assertEquals('tags', Vocabulary::where('slug', 'tags')->first()?->slug);
        $this->assertStringStartsWith('tags-', $second->slug);
    }

    public function test_create_vocabulary_persists_to_database(): void
    {
        $this->service->createVocabulary($this->space->id, ['name' => 'Topics', 'sort_order' => 3]);

        $this->assertDatabaseHas('vocabularies', [
            'space_id' => $this->space->id,
            'name' => 'Topics',
            'sort_order' => 3,
        ]);
    }

    // ─── createTerm ──────────────────────────────────────────────────────────

    public function test_create_root_term_sets_depth_zero(): void
    {
        $vocab = Vocabulary::factory()->create(['space_id' => $this->space->id]);
        $term = $this->service->createTerm($vocab->id, ['name' => 'Laravel']);

        $this->assertEquals(0, $term->depth);
        $this->assertNull($term->parent_id);
    }

    public function test_create_child_term_sets_depth_one(): void
    {
        $vocab = Vocabulary::factory()->create(['space_id' => $this->space->id]);
        $parent = $this->service->createTerm($vocab->id, ['name' => 'PHP']);
        $child = $this->service->createTerm($vocab->id, [
            'name' => 'Laravel',
            'parent_id' => $parent->id,
        ]);

        $this->assertEquals(1, $child->depth);
        $this->assertEquals($parent->id, $child->parent_id);
    }

    public function test_create_term_builds_materialized_path(): void
    {
        $vocab = Vocabulary::factory()->create(['space_id' => $this->space->id]);
        $root = $this->service->createTerm($vocab->id, ['name' => 'Root']);
        $child = $this->service->createTerm($vocab->id, [
            'name' => 'Child',
            'parent_id' => $root->id,
        ]);

        $this->assertStringContainsString($root->id, $child->path);
        $this->assertStringContainsString($child->id, $child->path);
    }

    public function test_create_term_auto_generates_slug(): void
    {
        $vocab = Vocabulary::factory()->create(['space_id' => $this->space->id]);
        $term = $this->service->createTerm($vocab->id, ['name' => 'Web Development']);

        $this->assertEquals('web-development', $term->slug);
    }

    public function test_create_term_generates_unique_slug_within_vocabulary(): void
    {
        $vocab = Vocabulary::factory()->create(['space_id' => $this->space->id]);
        $this->service->createTerm($vocab->id, ['name' => 'PHP']);
        $duplicate = $this->service->createTerm($vocab->id, ['name' => 'PHP']);

        $this->assertNotEquals('php', $duplicate->slug);
        $this->assertStringStartsWith('php-', $duplicate->slug);
    }

    public function test_same_slug_allowed_in_different_vocabularies(): void
    {
        $vocab1 = Vocabulary::factory()->create(['space_id' => $this->space->id]);
        $vocab2 = Vocabulary::factory()->create(['space_id' => $this->space->id]);

        $term1 = $this->service->createTerm($vocab1->id, ['name' => 'News', 'slug' => 'news']);
        $term2 = $this->service->createTerm($vocab2->id, ['name' => 'News', 'slug' => 'news']);

        $this->assertEquals('news', $term1->slug);
        $this->assertEquals('news', $term2->slug);
    }

    // ─── moveTerm ────────────────────────────────────────────────────────────

    public function test_move_term_to_different_parent_updates_path(): void
    {
        $vocab = Vocabulary::factory()->create(['space_id' => $this->space->id]);
        $parentA = $this->service->createTerm($vocab->id, ['name' => 'Parent A']);
        $parentB = $this->service->createTerm($vocab->id, ['name' => 'Parent B']);
        $term = $this->service->createTerm($vocab->id, [
            'name' => 'Child',
            'parent_id' => $parentA->id,
        ]);

        $moved = $this->service->moveTerm($term, $parentB->id);

        $this->assertEquals($parentB->id, $moved->parent_id);
        $this->assertStringContainsString($parentB->id, $moved->path);
        $this->assertStringNotContainsString($parentA->id, $moved->path);
    }

    public function test_move_term_to_root_sets_depth_zero(): void
    {
        $vocab = Vocabulary::factory()->create(['space_id' => $this->space->id]);
        $parent = $this->service->createTerm($vocab->id, ['name' => 'Parent']);
        $term = $this->service->createTerm($vocab->id, [
            'name' => 'Child',
            'parent_id' => $parent->id,
        ]);

        $moved = $this->service->moveTerm($term, null);

        $this->assertEquals(0, $moved->depth);
        $this->assertNull($moved->parent_id);
    }

    public function test_move_term_updates_descendant_paths(): void
    {
        $vocab = Vocabulary::factory()->create(['space_id' => $this->space->id]);
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

        $this->service->moveTerm($child, $parentB->id);

        $grandchild->refresh();
        $this->assertStringContainsString($parentB->id, $grandchild->path);
        $this->assertStringContainsString($child->id, $grandchild->path);
        $this->assertStringNotContainsString($parentA->id, $grandchild->path);
    }

    // ─── reorderTerms ────────────────────────────────────────────────────────

    public function test_reorder_terms_updates_sort_order(): void
    {
        $vocab = Vocabulary::factory()->create(['space_id' => $this->space->id]);
        $term1 = $this->service->createTerm($vocab->id, ['name' => 'Alpha', 'sort_order' => 0]);
        $term2 = $this->service->createTerm($vocab->id, ['name' => 'Beta', 'sort_order' => 1]);
        $term3 = $this->service->createTerm($vocab->id, ['name' => 'Gamma', 'sort_order' => 2]);

        $this->service->reorderTerms([
            $term1->id => 2,
            $term2->id => 0,
            $term3->id => 1,
        ]);

        $this->assertEquals(2, $term1->fresh()->sort_order);
        $this->assertEquals(0, $term2->fresh()->sort_order);
        $this->assertEquals(1, $term3->fresh()->sort_order);
    }

    // ─── getTree ─────────────────────────────────────────────────────────────

    public function test_get_tree_returns_root_terms_with_children(): void
    {
        $vocab = Vocabulary::factory()->create(['space_id' => $this->space->id]);
        $root = $this->service->createTerm($vocab->id, ['name' => 'Root']);
        $this->service->createTerm($vocab->id, [
            'name' => 'Child',
            'parent_id' => $root->id,
        ]);
        $this->service->createTerm($vocab->id, ['name' => 'Another Root']);

        $tree = $this->service->getTree($vocab->id);

        $this->assertCount(2, $tree);
        $rootInTree = $tree->firstWhere('id', $root->id);
        $this->assertNotNull($rootInTree);
        $this->assertCount(1, $rootInTree->childrenRecursive);
    }

    public function test_get_tree_returns_empty_collection_for_empty_vocab(): void
    {
        $vocab = Vocabulary::factory()->create(['space_id' => $this->space->id]);

        $tree = $this->service->getTree($vocab->id);

        $this->assertEmpty($tree);
    }

    // ─── assignTerms ─────────────────────────────────────────────────────────

    public function test_assign_terms_creates_pivot_records(): void
    {
        $vocab = Vocabulary::factory()->create(['space_id' => $this->space->id]);
        $term = $this->service->createTerm($vocab->id, ['name' => 'Laravel']);
        $type = ContentType::factory()->create(['space_id' => $this->space->id]);
        $content = Content::factory()->published()->create([
            'space_id' => $this->space->id,
            'content_type_id' => $type->id,
        ]);

        $this->service->assignTerms($content, [
            ['term_id' => $term->id, 'sort_order' => 0],
        ]);

        $this->assertDatabaseHas('content_taxonomy', [
            'content_id' => $content->id,
            'term_id' => $term->id,
        ]);
    }

    public function test_assign_terms_stores_ai_metadata(): void
    {
        $vocab = Vocabulary::factory()->create(['space_id' => $this->space->id]);
        $term = $this->service->createTerm($vocab->id, ['name' => 'PHP']);
        $type = ContentType::factory()->create(['space_id' => $this->space->id]);
        $content = Content::factory()->published()->create([
            'space_id' => $this->space->id,
            'content_type_id' => $type->id,
        ]);

        $this->service->assignTerms($content, [
            [
                'term_id' => $term->id,
                'auto_assigned' => true,
                'confidence' => 0.95,
                'sort_order' => 0,
            ],
        ]);

        $this->assertDatabaseHas('content_taxonomy', [
            'content_id' => $content->id,
            'term_id' => $term->id,
            'auto_assigned' => true,
            'confidence' => 0.95,
        ]);
    }

    public function test_assign_terms_is_idempotent(): void
    {
        $vocab = Vocabulary::factory()->create(['space_id' => $this->space->id]);
        $term = $this->service->createTerm($vocab->id, ['name' => 'Vue']);
        $type = ContentType::factory()->create(['space_id' => $this->space->id]);
        $content = Content::factory()->published()->create([
            'space_id' => $this->space->id,
            'content_type_id' => $type->id,
        ]);

        $this->service->assignTerms($content, [['term_id' => $term->id]]);
        $this->service->assignTerms($content, [['term_id' => $term->id]]);

        $this->assertEquals(1, $content->taxonomyTerms()->count());
    }

    // ─── syncTerms ───────────────────────────────────────────────────────────

    public function test_sync_terms_replaces_all_existing_assignments(): void
    {
        $vocab = Vocabulary::factory()->create(['space_id' => $this->space->id]);
        $term1 = $this->service->createTerm($vocab->id, ['name' => 'PHP']);
        $term2 = $this->service->createTerm($vocab->id, ['name' => 'Laravel']);
        $term3 = $this->service->createTerm($vocab->id, ['name' => 'Vue']);
        $type = ContentType::factory()->create(['space_id' => $this->space->id]);
        $content = Content::factory()->published()->create([
            'space_id' => $this->space->id,
            'content_type_id' => $type->id,
        ]);

        $this->service->assignTerms($content, [
            ['term_id' => $term1->id],
            ['term_id' => $term2->id],
        ]);

        $this->service->syncTerms($content, [$term3->id]);

        $this->assertCount(1, $content->fresh()->taxonomyTerms);
        $this->assertEquals($term3->id, $content->fresh()->taxonomyTerms->first()->id);
    }

    // ─── removeTerms ─────────────────────────────────────────────────────────

    public function test_remove_terms_detaches_from_content(): void
    {
        $vocab = Vocabulary::factory()->create(['space_id' => $this->space->id]);
        $term = $this->service->createTerm($vocab->id, ['name' => 'PHP']);
        $type = ContentType::factory()->create(['space_id' => $this->space->id]);
        $content = Content::factory()->published()->create([
            'space_id' => $this->space->id,
            'content_type_id' => $type->id,
        ]);

        $this->service->assignTerms($content, [['term_id' => $term->id]]);
        $this->service->removeTerms($content, [$term->id]);

        $this->assertCount(0, $content->fresh()->taxonomyTerms);
    }

    // ─── recalculateContentCount ─────────────────────────────────────────────

    public function test_recalculate_content_count_updates_term(): void
    {
        $vocab = Vocabulary::factory()->create(['space_id' => $this->space->id]);
        $term = $this->service->createTerm($vocab->id, ['name' => 'PHP']);
        $type = ContentType::factory()->create(['space_id' => $this->space->id]);

        $content1 = Content::factory()->published()->create([
            'space_id' => $this->space->id,
            'content_type_id' => $type->id,
        ]);
        $content2 = Content::factory()->published()->create([
            'space_id' => $this->space->id,
            'content_type_id' => $type->id,
        ]);

        $term->contents()->attach($content1->id, ['sort_order' => 0, 'auto_assigned' => false]);
        $term->contents()->attach($content2->id, ['sort_order' => 0, 'auto_assigned' => false]);

        $this->service->recalculateContentCount($term);

        $this->assertEquals(2, $term->fresh()->content_count);
    }

    public function test_assign_terms_updates_content_count(): void
    {
        $vocab = Vocabulary::factory()->create(['space_id' => $this->space->id]);
        $term = $this->service->createTerm($vocab->id, ['name' => 'Laravel']);
        $type = ContentType::factory()->create(['space_id' => $this->space->id]);
        $content = Content::factory()->published()->create([
            'space_id' => $this->space->id,
            'content_type_id' => $type->id,
        ]);

        $this->assertEquals(0, $term->content_count);

        $this->service->assignTerms($content, [['term_id' => $term->id]]);

        $this->assertEquals(1, $term->fresh()->content_count);
    }

    public function test_remove_terms_decrements_content_count(): void
    {
        $vocab = Vocabulary::factory()->create(['space_id' => $this->space->id]);
        $term = $this->service->createTerm($vocab->id, ['name' => 'Laravel']);
        $type = ContentType::factory()->create(['space_id' => $this->space->id]);
        $content = Content::factory()->published()->create([
            'space_id' => $this->space->id,
            'content_type_id' => $type->id,
        ]);

        $this->service->assignTerms($content, [['term_id' => $term->id]]);
        $this->assertEquals(1, $term->fresh()->content_count);

        $this->service->removeTerms($content, [$term->id]);
        $this->assertEquals(0, $term->fresh()->content_count);
    }

    // ─── deleteTerm ──────────────────────────────────────────────────────────

    public function test_delete_term_reparent_strategy_moves_children_to_grandparent(): void
    {
        $vocab = Vocabulary::factory()->create(['space_id' => $this->space->id]);
        $root = $this->service->createTerm($vocab->id, ['name' => 'Root']);
        $middle = $this->service->createTerm($vocab->id, [
            'name' => 'Middle',
            'parent_id' => $root->id,
        ]);
        $child = $this->service->createTerm($vocab->id, [
            'name' => 'Child',
            'parent_id' => $middle->id,
        ]);

        $this->service->deleteTerm($middle, 'reparent');

        $this->assertDatabaseMissing('taxonomy_terms', ['id' => $middle->id]);
        $this->assertEquals($root->id, $child->fresh()->parent_id);
    }

    public function test_delete_term_cascade_strategy_deletes_subtree(): void
    {
        $vocab = Vocabulary::factory()->create(['space_id' => $this->space->id]);
        $root = $this->service->createTerm($vocab->id, ['name' => 'Root']);
        $child = $this->service->createTerm($vocab->id, [
            'name' => 'Child',
            'parent_id' => $root->id,
        ]);
        $grandchild = $this->service->createTerm($vocab->id, [
            'name' => 'Grandchild',
            'parent_id' => $child->id,
        ]);

        $this->service->deleteTerm($root, 'cascade');

        $this->assertDatabaseMissing('taxonomy_terms', ['id' => $root->id]);
        $this->assertDatabaseMissing('taxonomy_terms', ['id' => $child->id]);
        $this->assertDatabaseMissing('taxonomy_terms', ['id' => $grandchild->id]);
    }

    // ─── generateUniqueSlug ──────────────────────────────────────────────────

    public function test_generate_unique_slug_returns_base_when_no_conflict(): void
    {
        $vocab = Vocabulary::factory()->create(['space_id' => $this->space->id]);

        $slug = $this->service->generateUniqueSlug($vocab->id, 'My Term');

        $this->assertEquals('my-term', $slug);
    }

    public function test_generate_unique_slug_appends_counter_on_conflict(): void
    {
        $vocab = Vocabulary::factory()->create(['space_id' => $this->space->id]);
        TaxonomyTerm::factory()->create([
            'vocabulary_id' => $vocab->id,
            'slug' => 'my-term',
        ]);

        $slug = $this->service->generateUniqueSlug($vocab->id, 'My Term');

        $this->assertEquals('my-term-1', $slug);
    }

    public function test_generate_unique_slug_excludes_given_id(): void
    {
        $vocab = Vocabulary::factory()->create(['space_id' => $this->space->id]);
        $term = TaxonomyTerm::factory()->create([
            'vocabulary_id' => $vocab->id,
            'slug' => 'my-term',
        ]);

        $slug = $this->service->generateUniqueSlug($vocab->id, 'My Term', $term->id);

        // Should allow reuse because the existing record is excluded
        $this->assertEquals('my-term', $slug);
    }
}
