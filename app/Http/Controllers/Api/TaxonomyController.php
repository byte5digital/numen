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
     */
    public function store(Request $request): VocabularyResource
    {
        $validated = $request->validate([
            'space_id' => ['required', 'string', 'exists:spaces,id'],
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'hierarchy' => ['boolean'],
            'allow_multiple' => ['boolean'],
            'settings' => ['nullable', 'array'],
            'sort_order' => ['integer'],
        ]);

        $vocabulary = $this->taxonomy->createVocabulary($validated['space_id'], $validated);

        return new VocabularyResource($vocabulary);
    }

    /**
     * Update a vocabulary.
     */
    public function update(Request $request, string $id): VocabularyResource
    {
        $vocabulary = Vocabulary::findOrFail($id);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'slug' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'hierarchy' => ['boolean'],
            'allow_multiple' => ['boolean'],
            'settings' => ['nullable', 'array'],
            'sort_order' => ['integer'],
        ]);

        $vocabulary = $this->taxonomy->updateVocabulary($vocabulary, $validated);

        return new VocabularyResource($vocabulary);
    }

    /**
     * Delete a vocabulary.
     */
    public function destroy(string $id): JsonResponse
    {
        $vocabulary = Vocabulary::findOrFail($id);
        $this->taxonomy->deleteVocabulary($vocabulary);

        return response()->json(['data' => ['deleted' => true]]);
    }
}
