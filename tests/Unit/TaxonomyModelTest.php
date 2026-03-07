<?php

namespace Tests\Unit;

use App\Models\Content;
use App\Models\ContentType;
use App\Models\Space;
use App\Models\TaxonomyTerm;
use App\Models\Vocabulary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaxonomyModelTest extends TestCase
{
    use RefreshDatabase;

    private Space $space;

    protected function setUp(): void
    {
        parent::setUp();

        $this->space = Space::factory()->create();
    }

    // --- Vocabulary model ---

    public function test_vocabulary_belongs_to_space(): void
    {
        $vocab = Vocabulary::factory()->create(['space_id' => $this->space->id]);

        $this->assertInstanceOf(Space::class, $vocab->space);
        $this->assertEquals($this->space->id, $vocab->space->id);
    }

    public function test_vocabulary_has_many_terms(): void
    {
        $vocab = Vocabulary::factory()->create(['space_id' => $this->space->id]);
        TaxonomyTerm::factory()->count(3)->create(['vocabulary_id' => $vocab->id]);

        $this->assertCount(3, $vocab->terms);
    }

    public function test_vocabulary_root_terms_excludes_children(): void
    {
        $vocab = Vocabulary::factory()->create(['space_id' => $this->space->id]);
        $root = TaxonomyTerm::factory()->create(['vocabulary_id' => $vocab->id, 'parent_id' => null]);
        // child term
        TaxonomyTerm::factory()->create(['vocabulary_id' => $vocab->id, 'parent_id' => $root->id]);

        $roots = $vocab->rootTerms;

        $this->assertCount(1, $roots);
        $this->assertEquals($root->id, $roots->first()->id);
    }

    public function test_vocabulary_scope_for_space(): void
    {
        $otherSpace = Space::factory()->create();
        Vocabulary::factory()->create(['space_id' => $this->space->id]);
        Vocabulary::factory()->create(['space_id' => $otherSpace->id]);

        $vocabs = Vocabulary::forSpace($this->space->id)->get();

        $this->assertCount(1, $vocabs);
    }

    public function test_vocabulary_scope_ordered(): void
    {
        Vocabulary::factory()->create(['space_id' => $this->space->id, 'sort_order' => 10]);
        Vocabulary::factory()->create(['space_id' => $this->space->id, 'sort_order' => 1]);
        Vocabulary::factory()->create(['space_id' => $this->space->id, 'sort_order' => 5]);

        $vocabs = Vocabulary::forSpace($this->space->id)->ordered()->get();

        $this->assertEquals(1, $vocabs[0]->sort_order);
        $this->assertEquals(5, $vocabs[1]->sort_order);
        $this->assertEquals(10, $vocabs[2]->sort_order);
    }

    public function test_vocabulary_casts_booleans(): void
    {
        $vocab = Vocabulary::factory()->create([
            'space_id' => $this->space->id,
            'hierarchy' => true,
            'allow_multiple' => false,
        ]);

        $vocab->refresh();

        $this->assertIsBool($vocab->hierarchy);
        $this->assertIsBool($vocab->allow_multiple);
        $this->assertTrue($vocab->hierarchy);
        $this->assertFalse($vocab->allow_multiple);
    }

    public function test_vocabulary_casts_settings_array(): void
    {
        $vocab = Vocabulary::factory()->create([
            'space_id' => $this->space->id,
            'settings' => ['color' => 'blue', 'icon' => 'tag'],
        ]);

        $vocab->refresh();

        $this->assertIsArray($vocab->settings);
        $this->assertEquals('blue', $vocab->settings['color']);
    }

    // --- TaxonomyTerm model ---

    public function test_term_slug_auto_generated_from_name(): void
    {
        $vocab = Vocabulary::factory()->create(['space_id' => $this->space->id]);
        $term = TaxonomyTerm::factory()->create([
            'vocabulary_id' => $vocab->id,
            'name' => 'My Test Term',
            'slug' => '',
        ]);

        $this->assertEquals('my-test-term', $term->slug);
    }

    public function test_term_slug_not_overwritten_when_provided(): void
    {
        $vocab = Vocabulary::factory()->create(['space_id' => $this->space->id]);
        $term = TaxonomyTerm::factory()->create([
            'vocabulary_id' => $vocab->id,
            'name' => 'My Term',
            'slug' => 'custom-slug',
        ]);

        $this->assertEquals('custom-slug', $term->slug);
    }

    public function test_root_term_has_correct_path_and_depth(): void
    {
        $vocab = Vocabulary::factory()->create(['space_id' => $this->space->id]);
        $term = TaxonomyTerm::factory()->create([
            'vocabulary_id' => $vocab->id,
            'parent_id' => null,
        ]);

        $this->assertEquals(0, $term->depth);
        $this->assertStringContainsString($term->id, $term->path);
    }

    public function test_child_term_has_incremented_depth(): void
    {
        $vocab = Vocabulary::factory()->create(['space_id' => $this->space->id]);
        $parent = TaxonomyTerm::factory()->create(['vocabulary_id' => $vocab->id, 'parent_id' => null]);

        $child = new TaxonomyTerm([
            'vocabulary_id' => $vocab->id,
            'parent_id' => $parent->id,
            'name' => 'Child',
            'slug' => 'child',
        ]);
        $child->setRelation('parent', $parent);
        $child->save();

        $this->assertEquals(1, $child->depth);
    }

    public function test_term_path_includes_parent_id(): void
    {
        $vocab = Vocabulary::factory()->create(['space_id' => $this->space->id]);
        $parent = TaxonomyTerm::factory()->create(['vocabulary_id' => $vocab->id]);

        $child = new TaxonomyTerm([
            'vocabulary_id' => $vocab->id,
            'parent_id' => $parent->id,
            'name' => 'Child',
            'slug' => 'child',
        ]);
        $child->setRelation('parent', $parent);
        $child->save();

        $this->assertStringContainsString($parent->id, $child->path);
        $this->assertStringContainsString($child->id, $child->path);
    }

    public function test_term_belongs_to_vocabulary(): void
    {
        $vocab = Vocabulary::factory()->create(['space_id' => $this->space->id]);
        $term = TaxonomyTerm::factory()->create(['vocabulary_id' => $vocab->id]);

        $this->assertInstanceOf(Vocabulary::class, $term->vocabulary);
        $this->assertEquals($vocab->id, $term->vocabulary->id);
    }

    public function test_term_has_parent_relation(): void
    {
        $vocab = Vocabulary::factory()->create(['space_id' => $this->space->id]);
        $parent = TaxonomyTerm::factory()->create(['vocabulary_id' => $vocab->id]);
        $child = TaxonomyTerm::factory()->create(['vocabulary_id' => $vocab->id, 'parent_id' => $parent->id]);

        $this->assertInstanceOf(TaxonomyTerm::class, $child->parent);
        $this->assertEquals($parent->id, $child->parent->id);
    }

    public function test_term_has_children_relation(): void
    {
        $vocab = Vocabulary::factory()->create(['space_id' => $this->space->id]);
        $parent = TaxonomyTerm::factory()->create(['vocabulary_id' => $vocab->id]);
        TaxonomyTerm::factory()->count(3)->create(['vocabulary_id' => $vocab->id, 'parent_id' => $parent->id]);

        $this->assertCount(3, $parent->children);
    }

    public function test_term_scope_roots_returns_only_root_terms(): void
    {
        $vocab = Vocabulary::factory()->create(['space_id' => $this->space->id]);
        $root1 = TaxonomyTerm::factory()->create(['vocabulary_id' => $vocab->id, 'parent_id' => null]);
        $root2 = TaxonomyTerm::factory()->create(['vocabulary_id' => $vocab->id, 'parent_id' => null]);
        TaxonomyTerm::factory()->create(['vocabulary_id' => $vocab->id, 'parent_id' => $root1->id]);

        $roots = TaxonomyTerm::inVocabulary($vocab->id)->roots()->get();

        $this->assertCount(2, $roots);
        $this->assertTrue($roots->pluck('id')->contains($root1->id));
        $this->assertTrue($roots->pluck('id')->contains($root2->id));
    }

    public function test_term_scope_in_vocabulary(): void
    {
        $vocab1 = Vocabulary::factory()->create(['space_id' => $this->space->id]);
        $vocab2 = Vocabulary::factory()->create(['space_id' => $this->space->id]);
        TaxonomyTerm::factory()->count(2)->create(['vocabulary_id' => $vocab1->id]);
        TaxonomyTerm::factory()->count(3)->create(['vocabulary_id' => $vocab2->id]);

        $terms = TaxonomyTerm::inVocabulary($vocab1->id)->get();

        $this->assertCount(2, $terms);
    }

    public function test_term_scope_descendants_of(): void
    {
        $vocab = Vocabulary::factory()->create(['space_id' => $this->space->id]);
        $root = TaxonomyTerm::factory()->create(['vocabulary_id' => $vocab->id]);

        $child = new TaxonomyTerm([
            'vocabulary_id' => $vocab->id,
            'parent_id' => $root->id,
            'name' => 'Child',
            'slug' => 'child',
        ]);
        $child->setRelation('parent', $root);
        $child->save();

        $grandchild = new TaxonomyTerm([
            'vocabulary_id' => $vocab->id,
            'parent_id' => $child->id,
            'name' => 'Grandchild',
            'slug' => 'grandchild',
        ]);
        $grandchild->setRelation('parent', $child->fresh());
        $grandchild->save();

        $unrelated = TaxonomyTerm::factory()->create(['vocabulary_id' => $vocab->id]);

        $descendants = TaxonomyTerm::descendantsOf($root->id)->get();

        $this->assertTrue($descendants->pluck('id')->contains($child->id));
        $this->assertTrue($descendants->pluck('id')->contains($grandchild->id));
        $this->assertFalse($descendants->pluck('id')->contains($unrelated->id));
    }

    public function test_get_ancestor_ids_returns_correct_ids(): void
    {
        $vocab = Vocabulary::factory()->create(['space_id' => $this->space->id]);
        $root = TaxonomyTerm::factory()->create(['vocabulary_id' => $vocab->id]);

        $child = new TaxonomyTerm([
            'vocabulary_id' => $vocab->id,
            'parent_id' => $root->id,
            'name' => 'Child',
            'slug' => 'child',
        ]);
        $child->setRelation('parent', $root);
        $child->save();

        $grandchild = new TaxonomyTerm([
            'vocabulary_id' => $vocab->id,
            'parent_id' => $child->id,
            'name' => 'Grandchild',
            'slug' => 'grandchild',
        ]);
        $grandchild->setRelation('parent', $child->fresh());
        $grandchild->save();

        $ancestorIds = $grandchild->fresh()->getAncestorIds();

        $this->assertContains($root->id, $ancestorIds);
        $this->assertContains($child->id, $ancestorIds);
        $this->assertContains($grandchild->id, $ancestorIds);
    }

    public function test_is_ancestor_of_detects_parent_relationship(): void
    {
        $vocab = Vocabulary::factory()->create(['space_id' => $this->space->id]);
        $root = TaxonomyTerm::factory()->create(['vocabulary_id' => $vocab->id]);

        $child = new TaxonomyTerm([
            'vocabulary_id' => $vocab->id,
            'parent_id' => $root->id,
            'name' => 'Child',
            'slug' => 'child',
        ]);
        $child->setRelation('parent', $root);
        $child->save();

        $this->assertTrue($root->isAncestorOf($child));
        $this->assertFalse($child->isAncestorOf($root));
    }

    public function test_increment_and_decrement_content_count(): void
    {
        $vocab = Vocabulary::factory()->create(['space_id' => $this->space->id]);
        $term = TaxonomyTerm::factory()->create(['vocabulary_id' => $vocab->id, 'content_count' => 0]);

        $term->incrementContentCount();
        $this->assertEquals(1, $term->fresh()->content_count);

        $term->decrementContentCount();
        $this->assertEquals(0, $term->fresh()->content_count);
    }

    public function test_term_has_contents_relation(): void
    {
        $vocab = Vocabulary::factory()->create(['space_id' => $this->space->id]);
        $term = TaxonomyTerm::factory()->create(['vocabulary_id' => $vocab->id]);

        $type = ContentType::factory()->create(['space_id' => $this->space->id]);
        $content = Content::factory()->published()->create([
            'space_id' => $this->space->id,
            'content_type_id' => $type->id,
        ]);

        $term->contents()->attach($content->id, ['sort_order' => 0, 'auto_assigned' => false, 'confidence' => null]);

        $this->assertCount(1, $term->contents);
    }

    public function test_children_recursive_eager_loads_tree(): void
    {
        $vocab = Vocabulary::factory()->create(['space_id' => $this->space->id]);
        $root = TaxonomyTerm::factory()->create(['vocabulary_id' => $vocab->id]);

        $child = new TaxonomyTerm([
            'vocabulary_id' => $vocab->id,
            'parent_id' => $root->id,
            'name' => 'Child',
            'slug' => 'child',
        ]);
        $child->setRelation('parent', $root);
        $child->save();

        $grandchild = new TaxonomyTerm([
            'vocabulary_id' => $vocab->id,
            'parent_id' => $child->id,
            'name' => 'Grandchild',
            'slug' => 'grandchild',
        ]);
        $grandchild->setRelation('parent', $child->fresh());
        $grandchild->save();

        $loaded = TaxonomyTerm::with('childrenRecursive')->find($root->id);

        $this->assertCount(1, $loaded->childrenRecursive);
        $this->assertCount(1, $loaded->childrenRecursive->first()->childrenRecursive);
    }
}
