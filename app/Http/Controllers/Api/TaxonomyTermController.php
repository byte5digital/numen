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
     */
    public function store(Request $request, string $vocabId): TaxonomyTermResource
    {
        $vocabulary = Vocabulary::findOrFail($vocabId);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'parent_id' => ['nullable', 'string', 'exists:taxonomy_terms,id'],
            'description' => ['nullable', 'string'],
            'sort_order' => ['integer'],
            'metadata' => ['nullable', 'array'],
        ]);

        $term = $this->taxonomy->createTerm($vocabulary->id, $validated);

        return new TaxonomyTermResource($term);
    }

    /**
     * Update a term.
     */
    public function update(Request $request, string $id): TaxonomyTermResource
    {
        $term = TaxonomyTerm::findOrFail($id);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'slug' => ['sometimes', 'string', 'max:255'],
            'parent_id' => ['nullable', 'string', 'exists:taxonomy_terms,id'],
            'description' => ['nullable', 'string'],
            'sort_order' => ['integer'],
            'metadata' => ['nullable', 'array'],
        ]);

        $term = $this->taxonomy->updateTerm($term, $validated);

        return new TaxonomyTermResource($term);
    }

    /**
     * Delete a term.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $term = TaxonomyTerm::findOrFail($id);

        $strategy = $request->input('child_strategy', 'reparent');
        $this->taxonomy->deleteTerm($term, $strategy);

        return response()->json(['data' => ['deleted' => true]]);
    }

    /**
     * Move a term to a new parent.
     */
    public function move(Request $request, string $id): TaxonomyTermResource
    {
        $term = TaxonomyTerm::findOrFail($id);

        $validated = $request->validate([
            'parent_id' => ['nullable', 'string', 'exists:taxonomy_terms,id'],
        ]);

        $term = $this->taxonomy->moveTerm($term, $validated['parent_id'] ?? null);

        return new TaxonomyTermResource($term);
    }

    /**
     * Reorder siblings.
     */
    public function reorder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ordering' => ['required', 'array'],
            'ordering.*' => ['integer'],
        ]);

        $this->taxonomy->reorderTerms($validated['ordering']);

        return response()->json(['data' => ['reordered' => true]]);
    }
}
