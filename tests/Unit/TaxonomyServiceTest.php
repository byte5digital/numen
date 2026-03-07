<?php

namespace Tests\Unit;

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

        $this->service = new TaxonomyService;
        $this->space = Space::factory()->create();
    }

    // --- Vocabulary CRUD ---

    public function test_create_vocabulary_generates_slug_from_name(): void
    {
        $vocab = $this->service->createVocabulary($this->space->id, [
            'name' => 'My Tags',
        ]);

        $this->assertEquals('my-tags', $vocab->slug);
        $this->assertEquals($this->space->id, $vocab->space_id);
    }

    public function test_create_vocabulary_uses_provided_slug(): void
    {
        $vocab = $this->service->createVocabulary($this->space->id, [
            'name' => 'My Tags',
            'slug' => 'custom-slug',
        ]);

        $this->assertEquals('custom-slug', $vocab->slug);
    }

    public function test_create_vocabulary_generates_unique_slug_on_collision(): void
    {
        $this->service->createVocabulary($this->space->id, ['name' => 'Tags']);
        $vocab2 = $this->service->createVocabulary($this->space->id, ['name' => 'Tags']);

        $this->assertEquals('tags-1', $vocab2->slug);
    }

    public function test_update_vocabulary_changes_fields(): void
    {
        $vocab = Vocabulary::factory()->create([
            'space_id' => $this->space->id,
            'name' => 'Original Name',
        ]);

        $updated = $this->service->updateVocabulary($vocab, ['name' => 'New Name', 'description' => 'Updated desc']);

        $this->assertEquals('New Name', $updated->name);
        $this->assertEquals('Updated desc', $updated->description);
    }

    public function test_delete_vocabulary_removes_it(): void
    {
        $vocab = Vocabulary::factory()->create(['space_id' => $this->space->id]);

        $this->service->deleteVocabulary($vocab);

        $this->assertDatabaseMissing('vocabularies', ['id' => $vocab->id]);
    }

    public function test_delete_vocabulary_cascades_to_terms(): void
    {
        $vocab = Vocabulary::factory()->create(['space_id' => $this->space->id]);
        $term = TaxonomyTerm::factory()->create(['vocabulary_id' => $vocab->id]);

        $this->service->deleteVocabulary($vocab);

        $this->assertDatabaseMissing('taxonomy_terms', ['id' => $term->id]);
    }

    // --- Term CRUD ---

    public function test_create_term_generates_slug(): void
    {
        $vocab = Vocabulary::factory()->create(['space_id' => $this->space->id]);

        $term = $this->service->createTerm($vocab->id, ['name' => 'My Term']);

        $this->assertEquals('my-term', $term->slug);
    }

    public function test_create_term_generates_unique_slug_on_collision(): void
    {
        $vocab = Vocabulary::factory()->create(['space_id' => $this->space->id]);

        $this->service->createTerm($vocab->id, ['name' => 'Tag']);
        $term2 = $this->service->createTerm($vocab->id, ['name' => 'Tag']);

        $this->assertEquals('tag-1', $term2->slug);
    }

    public function test_create_root_term_has_depth_zero(): void
    {
        $vocab = Vocabulary::factory()->create(['space_id' => $this->space->id]);

        $term = $this->service->createTerm($vocab->id, ['name' => 'Root']);

        $this->assertEquals(0, $term->depth);
    }

    public function test_create_child_term_sets_correct_depth_and_path(): void
    {
        $vocab = Vocabulary::factory()->create(['space_id' => $this->space->id]);
        $parent = $this->service->createTerm($vocab->id, ['name' => 'Parent']);

        $child = $this->service->createTerm($vocab->id, [
            'name' => 'Child',
            'parent_id' => $parent->id,
        ]);

        $this->assertEquals(1, $child->depth);
        $this->assertStringContainsString($parent->id, $child->path);
        $this->assertStringContainsString($child->id, $child->path);
    }

    public function test_update_term_changes_fields(): void
    {
        $vocab = Vocabulary::factory()->create(['space_id' => $this->space->id]);
        $term = $this->service->createTerm($vocab->id, ['name' => 'Original']);

        $updated = $this->service->updateTerm($term, ['name' => 'Updated', 'description' => 'New desc']);

        $this->assertEquals('Updated', $updated->name);
        $this->assertEquals('New desc', $updated->description);
    }

    public function test_update_term_reparent_recomputes_path(): void
    {
        $vocab = Vocabulary::factory()->create(['space_id' => $this->space->id]);
        $parent1 = $this->service->createTerm($vocab->id, ['name' => 'Parent 1']);
        $parent2 = $this->service->createTerm($vocab->id, ['name' => 'Parent 2']);
        $child = $this->service->createTerm($vocab->id, [
            'name' => 'Child',
            'parent_id' => $parent1->id,
        ]);

        $updated = $this->service->updateTerm($child, ['parent_id' => $parent2->id]);

        $this->assertStringContainsString($parent2->id, $updated->path);
        $this->assertStringNotContainsString($parent1->id, $updated->path);
    }

    // --- moveTerm ---

    public function test_move_term_to_new_parent_updates_path(): void
    {
        $vocab = Vocabulary::factory()->create(['space_id' => $this->space->id]);
        $parent1 = $this->service->createTerm($vocab->id, ['name' => 'P1']);
        $parent2 = $this->service->createTerm($vocab->id, ['name' => 'P2']);
        $child = $this->service->createTerm($vocab->id, ['name' => 'Child', 'parent_id' => $parent1->id]);

        $moved = $this->service->moveTerm($child, $parent2->id);

        $this->assertEquals($parent2->id, $moved->parent_id);
        $this->assertStringContainsString($parent2->id, $moved->path);
        $this->assertStringNotContainsString($parent1->id, $moved->path);
    }

    public function test_move_term_to_root_updates_depth_to_zero(): void
    {
        $vocab = Vocabulary::factory()->create(['space_id' => $this->space->id]);
        $parent = $this->service->createTerm($vocab->id, ['name' => 'Parent']);
        $child = $this->service->createTerm($vocab->id, ['name' => 'Child', 'parent_id' => $parent->id]);

        $moved = $this->service->moveTerm($child, null);

        $this->assertNull($moved->parent_id);
        $this->assertEquals(0, $moved->depth);
    }

    public function test_move_term_recomputes_descendant_paths(): void
    {
        $vocab = Vocabulary::factory()->create(['space_id' => $this->space->id]);
        $parent1 = $this->service->createTerm($vocab->id, ['name' => 'P1']);
        $parent2 = $this->service->createTerm($vocab->id, ['name' => 'P2']);
        $child = $this->service->createTerm($vocab->id, ['name' => 'Child', 'parent_id' => $parent1->id]);
        $grandchild = $this->service->createTerm($vocab->id, ['name' => 'GC', 'parent_id' => $child->id]);

        $this->service->moveTerm($child, $parent2->id);

        $grandchild->refresh();
        $this->assertStringContainsString($parent2->id, $grandchild->path);
        $this->assertStringContainsString($child->id, $grandchild->path);
        $this->assertEquals(2, $grandchild->depth);
    }

    public function test_deeply_nested_move_recomputes_all_descendants(): void
    {
        $vocab = Vocabulary::factory()->create(['space_id' => $this->space->id]);
        $root1 = $this->service->createTerm($vocab->id, ['name' => 'Root 1']);
        $root2 = $this->service->createTerm($vocab->id, ['name' => 'Root 2']);

        // Build 5-level deep tree under root1
        $current = $root1;
        $terms = [];
        for ($i = 1; $i <= 5; $i++) {
            $term = $this->service->createTerm($vocab->id, [
                'name' => "Level {$i}",
                'parent_id' => $current->id,
            ]);
            $terms[] = $term;
            $current = $term;
        }

        // Move Level 1 (depth=1) to root2
        $level1 = $terms[0];
        $this->service->moveTerm($level1, $root2->id);

        // Level 5 should now be depth=5 (root2=0, level1=1, level2=2, ..., level5=5)
        $deepest = end($terms);
        $deepest->refresh();
        $this->assertStringContainsString($root2->id, $deepest->path);
        $this->assertEquals(5, $deepest->depth);
    }

    // --- delete term ---

    public function test_delete_term_with_reparent_strategy(): void
    {
        $vocab = Vocabulary::factory()->create(['space_id' => $this->space->id]);
        $parent = $this->service->createTerm($vocab->id, ['name' => 'Parent']);
        $child = $this->service->createTerm($vocab->id, ['name' => 'Child', 'parent_id' => $parent->id]);
        $grandchild = $this->service->createTerm($vocab->id, ['name' => 'GC', 'parent_id' => $child->id]);

        $this->service->deleteTerm($child, 'reparent');

        $this->assertDatabaseMissing('taxonomy_terms', ['id' => $child->id]);
        $grandchild->refresh();
        $this->assertEquals($parent->id, $grandchild->parent_id);
    }

    public function test_delete_term_with_cascade_strategy(): void
    {
        $vocab = Vocabulary::factory()->create(['space_id' => $this->space->id]);
        $parent = $this->service->createTerm($vocab->id, ['name' => 'Parent']);
        $child = $this->service->createTerm($vocab->id, ['name' => 'Child', 'parent_id' => $parent->id]);
        $grandchild = $this->service->createTerm($vocab->id, ['name' => 'GC', 'parent_id' => $child->id]);

        $this->service->deleteTerm($child, 'cascade');

        $this->assertDatabaseMissing('taxonomy_terms', ['id' => $child->id]);
        $this->assertDatabaseMissing('taxonomy_terms', ['id' => $grandchild->id]);
    }

    public function test_delete_root_term_without_children(): void
    {
        $vocab = Vocabulary::factory()->create(['space_id' => $this->space->id]);
        $term = $this->service->createTerm($vocab->id, ['name' => 'Solo']);

        $this->service->deleteTerm($term);

        $this->assertDatabaseMissing('taxonomy_terms', ['id' => $term->id]);
    }

    // --- reorderTerms ---

    public function test_reorder_terms_updates_sort_order(): void
    {
        $vocab = Vocabulary::factory()->create(['space_id' => $this->space->id]);
        $term1 = $this->service->createTerm($vocab->id, ['name' => 'A', 'sort_order' => 3]);
        $term2 = $this->service->createTerm($vocab->id, ['name' => 'B', 'sort_order' => 1]);

        $this->service->reorderTerms([
            $term1->id => 1,
            $term2->id => 2,
        ]);

        $this->assertEquals(1, $term1->fresh()->sort_order);
        $this->assertEquals(2, $term2->fresh()->sort_order);
    }

    // --- getTree ---

    public function test_get_tree_returns_root_terms_with_nested_children(): void
    {
        $vocab = Vocabulary::factory()->create(['space_id' => $this->space->id]);
        $root = $this->service->createTerm($vocab->id, ['name' => 'Root']);
        $child = $this->service->createTerm($vocab->id, ['name' => 'Child', 'parent_id' => $root->id]);
        $this->service->createTerm($vocab->id, ['name' => 'GC', 'parent_id' => $child->id]);

        $tree = $this->service->getTree($vocab->id);

        $this->assertCount(1, $tree);
        $this->assertEquals($root->id, $tree->first()->id);
        $this->assertCount(1, $tree->first()->childrenRecursive);
    }

    public function test_get_tree_for_empty_vocabulary(): void
    {
        $vocab = Vocabulary::factory()->create(['space_id' => $this->space->id]);

        $tree = $this->service->getTree($vocab->id);

        $this->assertCount(0, $tree);
    }

    // --- assignTerms, syncTerms, removeTerms ---

    public function test_assign_terms_attaches_terms_to_content(): void
    {
        $vocab = Vocabulary::factory()->create(['space_id' => $this->space->id]);
        $term = $this->service->createTerm($vocab->id, ['name' => 'Tag']);
        $type = ContentType::factory()->create(['space_id' => $this->space->id]);
        $content = Content::factory()->published()->create([
            'space_id' => $this->space->id,
            'content_type_id' => $type->id,
        ]);

        $this->service->assignTerms($content, [
            ['term_id' => $term->id, 'auto_assigned' => false, 'confidence' => null],
        ]);

        $this->assertTrue($content->taxonomyTerms()->where('taxonomy_terms.id', $term->id)->exists());
    }

    public function test_assign_terms_updates_content_count(): void
    {
        $vocab = Vocabulary::factory()->create(['space_id' => $this->space->id]);
        $term = $this->service->createTerm($vocab->id, ['name' => 'Tag']);
        $type = ContentType::factory()->create(['space_id' => $this->space->id]);
        $content = Content::factory()->published()->create([
            'space_id' => $this->space->id,
            'content_type_id' => $type->id,
        ]);

        $this->service->assignTerms($content, [
            ['term_id' => $term->id],
        ]);

        $this->assertEquals(1, $term->fresh()->content_count);
    }

    public function test_assign_terms_is_additive(): void
    {
        $vocab = Vocabulary::factory()->create(['space_id' => $this->space->id]);
        $term1 = $this->service->createTerm($vocab->id, ['name' => 'Tag1']);
        $term2 = $this->service->createTerm($vocab->id, ['name' => 'Tag2']);
        $type = ContentType::factory()->create(['space_id' => $this->space->id]);
        $content = Content::factory()->published()->create([
            'space_id' => $this->space->id,
            'content_type_id' => $type->id,
        ]);

        $this->service->assignTerms($content, [['term_id' => $term1->id]]);
        $this->service->assignTerms($content, [['term_id' => $term2->id]]);

        $this->assertCount(2, $content->taxonomyTerms);
    }

    public function test_sync_terms_replaces_existing_assignments(): void
    {
        $vocab = Vocabulary::factory()->create(['space_id' => $this->space->id]);
        $term1 = $this->service->createTerm($vocab->id, ['name' => 'Tag1']);
        $term2 = $this->service->createTerm($vocab->id, ['name' => 'Tag2']);
        $term3 = $this->service->createTerm($vocab->id, ['name' => 'Tag3']);
        $type = ContentType::factory()->create(['space_id' => $this->space->id]);
        $content = Content::factory()->published()->create([
            'space_id' => $this->space->id,
            'content_type_id' => $type->id,
        ]);

        // Assign term1 and term2 first
        $this->service->assignTerms($content, [
            ['term_id' => $term1->id],
            ['term_id' => $term2->id],
        ]);

        // Sync to only term3
        $this->service->syncTerms($content, [$term3->id]);

        $termIds = $content->taxonomyTerms()->pluck('taxonomy_terms.id')->toArray();
        $this->assertNotContains($term1->id, $termIds);
        $this->assertNotContains($term2->id, $termIds);
        $this->assertContains($term3->id, $termIds);
    }

    public function test_remove_terms_detaches_specific_terms(): void
    {
        $vocab = Vocabulary::factory()->create(['space_id' => $this->space->id]);
        $term1 = $this->service->createTerm($vocab->id, ['name' => 'Tag1']);
        $term2 = $this->service->createTerm($vocab->id, ['name' => 'Tag2']);
        $type = ContentType::factory()->create(['space_id' => $this->space->id]);
        $content = Content::factory()->published()->create([
            'space_id' => $this->space->id,
            'content_type_id' => $type->id,
        ]);

        $this->service->assignTerms($content, [
            ['term_id' => $term1->id],
            ['term_id' => $term2->id],
        ]);

        $this->service->removeTerms($content, [$term1->id]);

        $termIds = $content->taxonomyTerms()->pluck('taxonomy_terms.id')->toArray();
        $this->assertNotContains($term1->id, $termIds);
        $this->assertContains($term2->id, $termIds);
    }

    public function test_remove_terms_decrements_content_count(): void
    {
        $vocab = Vocabulary::factory()->create(['space_id' => $this->space->id]);
        $term = $this->service->createTerm($vocab->id, ['name' => 'Tag']);
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

    // --- content count recalculation ---

    public function test_recalculate_content_count_reflects_actual_assignments(): void
    {
        $vocab = Vocabulary::factory()->create(['space_id' => $this->space->id]);
        $term = $this->service->createTerm($vocab->id, ['name' => 'Tag']);
        $type = ContentType::factory()->create(['space_id' => $this->space->id]);

        // Manually attach 3 content items
        for ($i = 0; $i < 3; $i++) {
            $c = Content::factory()->published()->create([
                'space_id' => $this->space->id,
                'content_type_id' => $type->id,
            ]);
            $term->contents()->attach($c->id, ['sort_order' => 0, 'auto_assigned' => false]);
        }

        // Force wrong count
        $term->update(['content_count' => 0]);

        $this->service->recalculateContentCount($term);

        $this->assertEquals(3, $term->fresh()->content_count);
    }

    // --- generateUniqueSlug ---

    public function test_generate_unique_slug_avoids_collisions(): void
    {
        $vocab = Vocabulary::factory()->create(['space_id' => $this->space->id]);
        TaxonomyTerm::factory()->create(['vocabulary_id' => $vocab->id, 'slug' => 'my-slug']);
        TaxonomyTerm::factory()->create(['vocabulary_id' => $vocab->id, 'slug' => 'my-slug-1']);

        $slug = $this->service->generateUniqueSlug($vocab->id, 'my slug');

        $this->assertEquals('my-slug-2', $slug);
    }

    public function test_generate_unique_slug_excludes_current_term(): void
    {
        $vocab = Vocabulary::factory()->create(['space_id' => $this->space->id]);
        $term = TaxonomyTerm::factory()->create(['vocabulary_id' => $vocab->id, 'slug' => 'my-slug']);

        $slug = $this->service->generateUniqueSlug($vocab->id, 'my slug', $term->id);

        $this->assertEquals('my-slug', $slug);
    }

    // --- getContentForTerm ---

    public function test_get_content_for_term_returns_only_published(): void
    {
        $vocab = Vocabulary::factory()->create(['space_id' => $this->space->id]);
        $term = $this->service->createTerm($vocab->id, ['name' => 'Tag']);
        $type = ContentType::factory()->create(['space_id' => $this->space->id]);

        $published = Content::factory()->published()->create([
            'space_id' => $this->space->id,
            'content_type_id' => $type->id,
        ]);
        $draft = Content::factory()->create([
            'space_id' => $this->space->id,
            'content_type_id' => $type->id,
            'status' => 'draft',
        ]);

        $term->contents()->attach($published->id, ['sort_order' => 0, 'auto_assigned' => false]);
        $term->contents()->attach($draft->id, ['sort_order' => 0, 'auto_assigned' => false]);

        $paginator = $this->service->getContentForTerm($term);

        $ids = $paginator->pluck('id')->toArray();
        $this->assertContains($published->id, $ids);
        $this->assertNotContains($draft->id, $ids);
    }

    public function test_get_content_for_term_includes_descendants_when_flag_set(): void
    {
        $vocab = Vocabulary::factory()->create(['space_id' => $this->space->id]);
        $parent = $this->service->createTerm($vocab->id, ['name' => 'Parent']);
        $child = $this->service->createTerm($vocab->id, ['name' => 'Child', 'parent_id' => $parent->id]);
        $type = ContentType::factory()->create(['space_id' => $this->space->id]);

        $contentOnChild = Content::factory()->published()->create([
            'space_id' => $this->space->id,
            'content_type_id' => $type->id,
        ]);
        $child->contents()->attach($contentOnChild->id, ['sort_order' => 0, 'auto_assigned' => false]);

        $paginator = $this->service->getContentForTerm($parent, includeDescendants: true);

        $this->assertCount(1, $paginator->items());
    }

    // --- Circular Reference Guards ---

    public function test_move_term_throws_when_moving_into_itself(): void
    {
        $vocab = Vocabulary::factory()->create(['space_id' => $this->space->id]);
        $term = $this->service->createTerm($vocab->id, ['name' => 'Root']);

        $this->expectException(\InvalidArgumentException::class);
        $this->service->moveTerm($term, $term->id);
    }

    public function test_move_term_throws_when_moving_into_direct_child(): void
    {
        $vocab = Vocabulary::factory()->create(['space_id' => $this->space->id]);
        $parent = $this->service->createTerm($vocab->id, ['name' => 'Parent']);
        $child = $this->service->createTerm($vocab->id, ['name' => 'Child', 'parent_id' => $parent->id]);

        $this->expectException(\InvalidArgumentException::class);
        // Moving parent into its own child is a cycle
        $this->service->moveTerm($parent->fresh(), $child->id);
    }

    public function test_move_term_throws_when_moving_into_grandchild(): void
    {
        $vocab = Vocabulary::factory()->create(['space_id' => $this->space->id]);
        $root = $this->service->createTerm($vocab->id, ['name' => 'Root']);
        $child = $this->service->createTerm($vocab->id, ['name' => 'Child', 'parent_id' => $root->id]);
        $grandchild = $this->service->createTerm($vocab->id, ['name' => 'Grandchild', 'parent_id' => $child->id]);

        $this->expectException(\InvalidArgumentException::class);
        $this->service->moveTerm($root->fresh(), $grandchild->id);
    }

    public function test_update_term_throws_when_parent_creates_cycle(): void
    {
        $vocab = Vocabulary::factory()->create(['space_id' => $this->space->id]);
        $parent = $this->service->createTerm($vocab->id, ['name' => 'Parent']);
        $child = $this->service->createTerm($vocab->id, ['name' => 'Child', 'parent_id' => $parent->id]);

        $this->expectException(\InvalidArgumentException::class);
        $this->service->updateTerm($parent->fresh(), ['parent_id' => $child->id]);
    }

    public function test_move_term_to_null_parent_succeeds(): void
    {
        $vocab = Vocabulary::factory()->create(['space_id' => $this->space->id]);
        $parent = $this->service->createTerm($vocab->id, ['name' => 'Parent']);
        $child = $this->service->createTerm($vocab->id, ['name' => 'Child', 'parent_id' => $parent->id]);

        $moved = $this->service->moveTerm($child->fresh(), null);

        $this->assertNull($moved->parent_id);
        $this->assertEquals(0, $moved->depth);
    }
}
