<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ContentResource;
use App\Http\Resources\TaxonomyTermResource;
use App\Http\Resources\TaxonomyTermTreeResource;
use App\Models\Space;
use App\Models\TaxonomyTerm;
use App\Models\Vocabulary;
use App\Services\Taxonomy\TaxonomyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TaxonomyTermController extends Controller
{
    public function __construct(private readonly TaxonomyService $taxonomy) {}

    /**
     * List terms for a vocabulary (flat or tree).
     */
    public function index(Request $request, string $vocabSlug): AnonymousResourceCollection|JsonResponse
    {
        $spaceSlug = $request->header('X-Space', 'default');
        $space = Space::where('slug', $spaceSlug)->firstOrFail();

        $vocabulary = Vocabulary::forSpace($space->id)
            ->where('slug', $vocabSlug)
            ->firstOrFail();

        if ($request->boolean('tree')) {
            $tree = $this->taxonomy->getTree($vocabulary->id);

            return response()->json([
                'data' => TaxonomyTermTreeResource::collection($tree),
            ]);
        }

        $terms = TaxonomyTerm::inVocabulary($vocabulary->id)->ordered()->get();

        return TaxonomyTermResource::collection($terms);
    }

    /**
     * Show a single term.
     */
    public function show(Request $request, string $vocabSlug, string $termSlug): TaxonomyTermResource
    {
        $spaceSlug = $request->header('X-Space', 'default');
        $space = Space::where('slug', $spaceSlug)->firstOrFail();

        $vocabulary = Vocabulary::forSpace($space->id)
            ->where('slug', $vocabSlug)
            ->firstOrFail();

        $term = TaxonomyTerm::inVocabulary($vocabulary->id)
            ->where('slug', $termSlug)
            ->firstOrFail();

        return new TaxonomyTermResource($term);
    }

    /**
     * Show content for a term.
     */
    public function content(Request $request, string $vocabSlug, string $termSlug): AnonymousResourceCollection
    {
        $spaceSlug = $request->header('X-Space', 'default');
        $space = Space::where('slug', $spaceSlug)->firstOrFail();

        $vocabulary = Vocabulary::forSpace($space->id)
            ->where('slug', $vocabSlug)
            ->firstOrFail();

        $term = TaxonomyTerm::inVocabulary($vocabulary->id)
            ->where('slug', $termSlug)
            ->firstOrFail();

        $perPage = max(1, min((int) $request->query('per_page', 20), 100));
        $includeDescendants = $request->boolean('include_descendants');

        $paginator = $this->taxonomy->getContentForTerm($term, $includeDescendants, $perPage);

        return ContentResource::collection($paginator);
    }

    /**
     * Create a term within a vocabulary.
     * Vocabulary is resolved through the X-Space scope to prevent cross-space IDOR.
     */
    public function store(Request $request, string $vocabId): TaxonomyTermResource
    {
        // Scope vocabulary lookup to the request's space — prevents cross-space term creation
        $spaceSlug = $request->header('X-Space', 'default');
        $space = Space::where('slug', $spaceSlug)->firstOrFail();

        $vocabulary = Vocabulary::forSpace($space->id)->findOrFail($vocabId);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'parent_id' => ['nullable', 'string', 'exists:taxonomy_terms,id'],
            'description' => ['nullable', 'string', 'max:5000'],
            'sort_order' => ['integer', 'min:0', 'max:9999'],
            'metadata' => ['nullable', 'array'],
        ]);

        // Validate parent_id belongs to the same vocabulary (prevents cross-vocabulary parentage)
        if (! empty($validated['parent_id'])) {
            $parentInVocab = TaxonomyTerm::where('id', $validated['parent_id'])
                ->where('vocabulary_id', $vocabulary->id)
                ->exists();

            if (! $parentInVocab) {
                abort(422, 'The parent term does not belong to this vocabulary.');
            }
        }

        $term = $this->taxonomy->createTerm($vocabulary->id, $validated);

        return new TaxonomyTermResource($term);
    }

    /**
     * Update a term.
     * Resolves the term through the X-Space scope to prevent cross-space IDOR.
     */
    public function update(Request $request, string $id): TaxonomyTermResource
    {
        // Resolve space and verify the term's vocabulary belongs to it
        $spaceSlug = $request->header('X-Space', 'default');
        $space = Space::where('slug', $spaceSlug)->firstOrFail();

        $term = TaxonomyTerm::whereHas(
            'vocabulary',
            fn ($q) => $q->where('space_id', $space->id)
        )->findOrFail($id);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'slug' => ['sometimes', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'parent_id' => ['nullable', 'string', 'exists:taxonomy_terms,id'],
            'description' => ['nullable', 'string', 'max:5000'],
            'sort_order' => ['integer', 'min:0', 'max:9999'],
            'metadata' => ['nullable', 'array'],
        ]);

        // Validate parent_id belongs to the same vocabulary (prevents cross-vocabulary parentage)
        if (array_key_exists('parent_id', $validated) && $validated['parent_id'] !== null) {
            $parentInVocab = TaxonomyTerm::where('id', $validated['parent_id'])
                ->where('vocabulary_id', $term->vocabulary_id)
                ->exists();

            if (! $parentInVocab) {
                abort(422, 'The parent term does not belong to this vocabulary.');
            }
        }

        $term = $this->taxonomy->updateTerm($term, $validated);

        return new TaxonomyTermResource($term);
    }

    /**
     * Delete a term.
     * Resolves the term through the X-Space scope to prevent cross-space IDOR.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        // Resolve space and verify the term's vocabulary belongs to it
        $spaceSlug = $request->header('X-Space', 'default');
        $space = Space::where('slug', $spaceSlug)->firstOrFail();

        $term = TaxonomyTerm::whereHas(
            'vocabulary',
            fn ($q) => $q->where('space_id', $space->id)
        )->findOrFail($id);

        $validated = $request->validate([
            'child_strategy' => ['sometimes', 'string', 'in:reparent,cascade'],
        ]);

        $strategy = $validated['child_strategy'] ?? 'reparent';
        $this->taxonomy->deleteTerm($term, $strategy);

        return response()->json(['data' => ['deleted' => true]]);
    }

    /**
     * Move a term to a new parent.
     * Resolves the term through the X-Space scope to prevent cross-space IDOR.
     */
    public function move(Request $request, string $id): TaxonomyTermResource
    {
        // Resolve space and verify the term's vocabulary belongs to it
        $spaceSlug = $request->header('X-Space', 'default');
        $space = Space::where('slug', $spaceSlug)->firstOrFail();

        $term = TaxonomyTerm::whereHas(
            'vocabulary',
            fn ($q) => $q->where('space_id', $space->id)
        )->findOrFail($id);

        $validated = $request->validate([
            'parent_id' => ['nullable', 'string', 'exists:taxonomy_terms,id'],
        ]);

        // Validate parent_id belongs to the same vocabulary (prevents cross-vocabulary parentage)
        if (! empty($validated['parent_id'])) {
            $parentInVocab = TaxonomyTerm::where('id', $validated['parent_id'])
                ->where('vocabulary_id', $term->vocabulary_id)
                ->exists();

            if (! $parentInVocab) {
                abort(422, 'The parent term does not belong to this vocabulary.');
            }
        }

        $term = $this->taxonomy->moveTerm($term, $validated['parent_id'] ?? null);

        return new TaxonomyTermResource($term);
    }

    /**
     * Reorder siblings within the current space.
     * Verifies all supplied term IDs belong to the space in X-Space header.
     */
    public function reorder(Request $request): JsonResponse
    {
        $spaceSlug = $request->header('X-Space', 'default');
        $space = Space::where('slug', $spaceSlug)->firstOrFail();

        $validated = $request->validate([
            'ordering' => ['required', 'array'],
            'ordering.*' => ['integer'],
        ]);

        // Verify every term in the ordering belongs to this space — prevents cross-space reordering
        $termIds = array_keys($validated['ordering']);
        $ownedCount = TaxonomyTerm::whereIn('id', $termIds)
            ->whereHas('vocabulary', fn ($q) => $q->where('space_id', $space->id))
            ->count();

        if ($ownedCount !== count($termIds)) {
            abort(403, 'One or more terms do not belong to this space.');
        }

        $this->taxonomy->reorderTerms($validated['ordering']);

        return response()->json(['data' => ['reordered' => true]]);
    }
}
