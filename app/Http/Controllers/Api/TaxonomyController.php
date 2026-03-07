<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TaxonomyTermTreeResource;
use App\Http\Resources\VocabularyResource;
use App\Models\Space;
use App\Models\Vocabulary;
use App\Services\Taxonomy\TaxonomyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TaxonomyController extends Controller
{
    public function __construct(private readonly TaxonomyService $taxonomy) {}

    /**
     * List vocabularies for the current space.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $spaceSlug = $request->header('X-Space', 'default');
        $space = Space::where('slug', $spaceSlug)->firstOrFail();

        $vocabularies = $space->vocabularies()
            ->withCount('terms')
            ->ordered()
            ->get();

        return VocabularyResource::collection($vocabularies);
    }

    /**
     * Show a vocabulary with its root terms.
     */
    public function show(Request $request, string $vocabSlug): JsonResponse
    {
        $spaceSlug = $request->header('X-Space', 'default');
        $space = Space::where('slug', $spaceSlug)->firstOrFail();

        $vocabulary = Vocabulary::forSpace($space->id)
            ->where('slug', $vocabSlug)
            ->firstOrFail();

        $tree = $this->taxonomy->getTree($vocabulary->id);

        return response()->json([
            'data' => [
                'vocabulary' => new VocabularyResource($vocabulary),
                'tree' => TaxonomyTermTreeResource::collection($tree),
            ],
        ]);
    }

    /**
     * Create a vocabulary.
     * Space is derived from the X-Space header — not accepted from the request body
     * to prevent cross-space privilege escalation.
     */
    public function store(Request $request): VocabularyResource
    {
        // Resolve space from header — never trust a space_id from the body
        $spaceSlug = $request->header('X-Space', 'default');
        $space = Space::where('slug', $spaceSlug)->firstOrFail();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'description' => ['nullable', 'string', 'max:5000'],
            'hierarchy' => ['boolean'],
            'allow_multiple' => ['boolean'],
            'settings' => ['nullable', 'array', 'max:50'],
            'sort_order' => ['integer', 'min:0', 'max:9999'],
        ]);

        $vocabulary = $this->taxonomy->createVocabulary($space->id, $validated);

        return new VocabularyResource($vocabulary);
    }

    /**
     * Update a vocabulary.
     * Verifies the vocabulary belongs to the space in X-Space header.
     */
    public function update(Request $request, string $id): VocabularyResource
    {
        // Scope to the request's space — prevents IDOR across spaces
        $spaceSlug = $request->header('X-Space', 'default');
        $space = Space::where('slug', $spaceSlug)->firstOrFail();

        $vocabulary = Vocabulary::forSpace($space->id)->findOrFail($id);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'slug' => ['sometimes', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'description' => ['nullable', 'string', 'max:5000'],
            'hierarchy' => ['boolean'],
            'allow_multiple' => ['boolean'],
            'settings' => ['nullable', 'array', 'max:50'],
            'sort_order' => ['integer', 'min:0', 'max:9999'],
        ]);

        $vocabulary = $this->taxonomy->updateVocabulary($vocabulary, $validated);

        return new VocabularyResource($vocabulary);
    }

    /**
     * Delete a vocabulary.
     * Verifies the vocabulary belongs to the space in X-Space header.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        // Scope to the request's space — prevents IDOR across spaces
        $spaceSlug = $request->header('X-Space', 'default');
        $space = Space::where('slug', $spaceSlug)->firstOrFail();

        $vocabulary = Vocabulary::forSpace($space->id)->findOrFail($id);
        $this->taxonomy->deleteVocabulary($vocabulary);

        return response()->json(['data' => ['deleted' => true]]);
    }
}
