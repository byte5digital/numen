<?php

namespace App\Services\Taxonomy;

use App\Models\Content;
use App\Models\TaxonomyTerm;
use App\Models\Vocabulary;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class TaxonomyService
{
    /**
     * Create a vocabulary within a space.
     *
     * @param  array<string, mixed>  $data
     */
    public function createVocabulary(string $spaceId, array $data): Vocabulary
    {
        if (empty($data['slug'])) {
            $data['slug'] = $this->generateUniqueVocabularySlug($spaceId, $data['name']);
        }

        return Vocabulary::create(array_merge($data, ['space_id' => $spaceId]));
    }

    /**
     * Update a vocabulary.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateVocabulary(Vocabulary $vocabulary, array $data): Vocabulary
    {
        $vocabulary->update($data);

        return $vocabulary->fresh() ?? $vocabulary;
    }

    /**
     * Delete a vocabulary and all its terms.
     */
    public function deleteVocabulary(Vocabulary $vocabulary): void
    {
        $vocabulary->delete();
    }

    /**
     * Create a term within a vocabulary (handles parent_id, path computation, slug generation).
     *
     * @param  array<string, mixed>  $data
     */
    public function createTerm(string $vocabularyId, array $data): TaxonomyTerm
    {
        if (empty($data['slug'])) {
            $data['slug'] = $this->generateUniqueSlug($vocabularyId, $data['name']);
        }

        $term = new TaxonomyTerm(array_merge($data, ['vocabulary_id' => $vocabularyId]));

        // Load parent for path computation in booted()
        if (! empty($data['parent_id'])) {
            $term->setRelation('parent', TaxonomyTerm::find($data['parent_id']));
        }

        $term->save();

        return $term;
    }

    /**
     * Update a term.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws \InvalidArgumentException if a parent_id change would create a circular reference.
     */
    public function updateTerm(TaxonomyTerm $term, array $data): TaxonomyTerm
    {
        // If parent_id changes, validate and reload relation before save for path recomputation
        if (isset($data['parent_id']) && $data['parent_id'] !== $term->parent_id) {
            $newParentId = $data['parent_id'];

            if ($newParentId !== null) {
                // A term cannot be its own parent
                if ($newParentId === $term->id) {
                    throw new \InvalidArgumentException('A term cannot be its own parent.');
                }

                $newParent = TaxonomyTerm::find($newParentId);

                // Guard: reparenting into a descendant would create an infinite cycle
                if ($newParent && $term->isAncestorOf($newParent)) {
                    throw new \InvalidArgumentException('Cannot set a descendant as the parent (circular reference).');
                }

                $term->setRelation('parent', $newParent);
            } else {
                $term->setRelation('parent', null);
            }
        }

        $term->update($data);

        // Re-compute paths for all descendants
        if (isset($data['parent_id'])) {
            $this->recomputeDescendantPaths($term->fresh() ?? $term);
        }

        return $term->fresh() ?? $term;
    }

    /**
     * Move a term to a new parent (re-computes path for term and all descendants).
     *
     * @throws \InvalidArgumentException if the move would create a circular reference.
     */
    public function moveTerm(TaxonomyTerm $term, ?string $newParentId): TaxonomyTerm
    {
        if ($newParentId !== null) {
            // A term cannot be its own parent
            if ($newParentId === $term->id) {
                throw new \InvalidArgumentException('A term cannot be its own parent.');
            }

            $newParent = TaxonomyTerm::find($newParentId);

            // Guard: moving into a descendant would create an infinite cycle
            if ($newParent && $term->isAncestorOf($newParent)) {
                throw new \InvalidArgumentException('Cannot move a term into one of its own descendants (circular reference).');
            }

            $term->setRelation('parent', $newParent);
        } else {
            $newParent = null;
            $term->setRelation('parent', null);
        }

        $term->parent_id = $newParentId;
        $term->computePath();
        $term->save();

        $this->recomputeDescendantPaths($term);

        return $term->fresh() ?? $term;
    }

    /**
     * Reorder siblings within a parent.
     *
     * @param  array<string, int>  $ordering  [term_id => sort_order]
     */
    public function reorderTerms(array $ordering): void
    {
        foreach ($ordering as $termId => $sortOrder) {
            TaxonomyTerm::where('id', $termId)->update(['sort_order' => $sortOrder]);
        }
    }

    /**
     * Build a full tree structure for a vocabulary.
     *
     * @return Collection<int, TaxonomyTerm>
     */
    public function getTree(string $vocabularyId): Collection
    {
        return TaxonomyTerm::inVocabulary($vocabularyId)
            ->roots()
            ->ordered()
            ->with('childrenRecursive')
            ->get();
    }

    /**
     * Assign terms to content (with optional AI metadata).
     *
     * @param  array<int, array{term_id: string, auto_assigned?: bool, confidence?: float|null}>  $assignments
     */
    public function assignTerms(Content $content, array $assignments): void
    {
        foreach ($assignments as $assignment) {
            $termId = $assignment['term_id'];

            $pivot = [
                'sort_order' => $assignment['sort_order'] ?? 0,
                'auto_assigned' => $assignment['auto_assigned'] ?? false,
                'confidence' => $assignment['confidence'] ?? null,
            ];

            $content->taxonomyTerms()->syncWithoutDetaching([$termId => $pivot]);
        }

        $this->refreshContentCountsForContent($content);
    }

    /**
     * Remove term assignments from content.
     *
     * @param  array<int, string>  $termIds
     */
    public function removeTerms(Content $content, array $termIds): void
    {
        $content->taxonomyTerms()->detach($termIds);
        // Refresh counts for remaining terms AND the removed terms
        $remainingIds = $content->taxonomyTerms()->pluck('taxonomy_terms.id')->toArray();
        $allAffectedIds = array_unique(array_merge($remainingIds, $termIds));
        $terms = TaxonomyTerm::whereIn('id', $allAffectedIds)->get();
        foreach ($terms as $term) {
            $this->recalculateContentCount($term);
        }
    }

    /**
     * Sync content's terms (replaces all assignments).
     *
     * @param  array<int, string>  $termIds
     */
    public function syncTerms(Content $content, array $termIds): void
    {
        // Capture previously assigned term IDs before sync
        $oldTermIds = $content->taxonomyTerms()->pluck('taxonomy_terms.id')->toArray();
        $content->taxonomyTerms()->sync($termIds);
        // Refresh counts for all affected terms (added + removed)
        $allAffectedIds = array_unique(array_merge($oldTermIds, $termIds));
        $terms = TaxonomyTerm::whereIn('id', $allAffectedIds)->get();
        foreach ($terms as $term) {
            $this->recalculateContentCount($term);
        }
    }

    /**
     * Get all content for a term (including descendants if $includeDescendants = true).
     */
    public function getContentForTerm(
        TaxonomyTerm $term,
        bool $includeDescendants = false,
        int $perPage = 20
    ): LengthAwarePaginator {
        $query = Content::query()->published();

        if ($includeDescendants) {
            $descendantIds = TaxonomyTerm::descendantsOf($term->id)
                ->pluck('id')
                ->prepend($term->id)
                ->toArray();

            $query->whereHas('taxonomyTerms', fn ($q) => $q->whereIn('taxonomy_terms.id', $descendantIds));
        } else {
            $query->inTerm($term->id);
        }

        return $query->with(['currentVersion', 'contentType'])->paginate($perPage);
    }

    /**
     * Recalculate content_count for a term (and optionally ancestors).
     */
    public function recalculateContentCount(TaxonomyTerm $term): void
    {
        $count = $term->contents()->count();
        $term->update(['content_count' => $count]);

        // Propagate to ancestors
        foreach ($term->getAncestorIds() as $ancestorId) {
            if ($ancestorId === $term->id) {
                continue;
            }
            $ancestor = TaxonomyTerm::find($ancestorId);
            if ($ancestor) {
                $ancestor->update(['content_count' => $ancestor->contents()->count()]);
            }
        }
    }

    /**
     * Delete a term (optionally reassign children to parent or delete subtree).
     */
    public function deleteTerm(TaxonomyTerm $term, string $childStrategy = 'reparent'): void
    {
        if ($childStrategy === 'reparent') {
            // Move children up to grandparent
            TaxonomyTerm::where('parent_id', $term->id)
                ->update(['parent_id' => $term->parent_id]);
        } else {
            // Cascade delete subtree
            $descendants = TaxonomyTerm::descendantsOf($term->id)->get();
            foreach ($descendants as $desc) {
                $desc->delete();
            }
        }

        $term->delete();
    }

    /**
     * Generate a unique slug within a vocabulary.
     */
    public function generateUniqueSlug(string $vocabularyId, string $name, ?string $excludeId = null): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $i = 1;

        while (true) {
            $query = TaxonomyTerm::where('vocabulary_id', $vocabularyId)->where('slug', $slug);
            if ($excludeId) {
                $query->where('id', '!=', $excludeId);
            }
            if (! $query->exists()) {
                break;
            }
            $slug = $base.'-'.($i++);
        }

        return $slug;
    }

    /**
     * Generate a unique slug within a space for a vocabulary.
     */
    private function generateUniqueVocabularySlug(string $spaceId, string $name): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $i = 1;

        while (Vocabulary::where('space_id', $spaceId)->where('slug', $slug)->exists()) {
            $slug = $base.'-'.($i++);
        }

        return $slug;
    }

    /**
     * Recursively recompute materialized paths for all descendants of a term.
     */
    private function recomputeDescendantPaths(TaxonomyTerm $parent): void
    {
        $children = TaxonomyTerm::where('parent_id', $parent->id)->get();

        foreach ($children as $child) {
            $child->setRelation('parent', $parent);
            $child->computePath();
            $child->save();

            $this->recomputeDescendantPaths($child);
        }
    }

    /**
     * Refresh content_count for all terms currently assigned to the given content.
     */
    private function refreshContentCountsForContent(Content $content): void
    {
        $termIds = $content->taxonomyTerms()->pluck('taxonomy_terms.id');
        $terms = TaxonomyTerm::whereIn('id', $termIds)->get();

        foreach ($terms as $term) {
            $this->recalculateContentCount($term);
        }
    }
}
