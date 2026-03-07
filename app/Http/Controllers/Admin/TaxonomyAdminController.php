<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Content;
use App\Models\Space;
use App\Models\TaxonomyTerm;
use App\Models\Vocabulary;
use App\Services\Taxonomy\TaxonomyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TaxonomyAdminController extends Controller
{
    public function __construct(private readonly TaxonomyService $taxonomy) {}

    /**
     * List all vocabularies for the default space.
     */
    public function index(): Response
    {
        $space = Space::first();

        $vocabularies = $space
            ? Vocabulary::forSpace($space->id)->withCount('terms')->ordered()->get()
            : collect();

        return Inertia::render('Taxonomy/Index', [
            'vocabularies' => $vocabularies,
            'spaceId' => $space?->id,
        ]);
    }

    /**
     * Show a vocabulary with its term tree.
     */
    public function show(string $id): Response
    {
        $vocabulary = Vocabulary::withCount('terms')->findOrFail($id);
        $tree = $this->taxonomy->getTree($vocabulary->id);

        return Inertia::render('Taxonomy/Show', [
            'vocabulary' => $vocabulary,
            'tree' => $tree,
        ]);
    }

    /**
     * Show a single taxonomy term with its assigned content.
     */
    public function showTerm(Request $request, string $termId): Response
    {
        $term = TaxonomyTerm::with(['vocabulary', 'children'])->findOrFail($termId);

        $includeDescendants = $request->boolean('descendants');

        $query = Content::with(['currentVersion', 'contentType']);

        if ($includeDescendants) {
            $descendantIds = TaxonomyTerm::descendantsOf($term->id)
                ->pluck('id')
                ->prepend($term->id)
                ->toArray();

            $query->whereHas('taxonomyTerms', fn ($q) => $q->whereIn('taxonomy_terms.id', $descendantIds));
        } else {
            $query->whereHas('taxonomyTerms', fn ($q) => $q->where('taxonomy_terms.id', $term->id));
        }

        $content = $query->with(['taxonomyTerms' => fn ($q) => $q->where('taxonomy_terms.id', $term->id)])
            ->paginate(15)
            ->through(fn (Content $c) => [
                'id' => $c->id,
                'slug' => $c->slug,
                'title' => $c->currentVersion->title ?? 'Untitled',
                'status' => $c->status,
                'type' => $c->contentType->slug,
                'type_name' => $c->contentType->name,
                'auto_assigned' => (static function (Content $c): bool {
                    $first = $c->taxonomyTerms->first();
                    if ($first === null) {
                        return false;
                    }

                    return (bool) $first->pivot->auto_assigned;
                })($c),
                'confidence' => (static function (Content $c): ?float {
                    $first = $c->taxonomyTerms->first();
                    if ($first === null) {
                        return null;
                    }
                    $conf = $first->pivot->confidence;

                    return $conf !== null ? (float) $conf : null;
                })($c),
                'published_at' => $c->published_at?->format('Y-m-d H:i'),
                'created_at' => $c->created_at->diffForHumans(),
            ]);

        return Inertia::render('Taxonomy/TermShow', [
            'term' => [
                'id' => $term->id,
                'name' => $term->name,
                'slug' => $term->slug,
                'description' => $term->description,
                'depth' => $term->depth,
                'content_count' => $term->content_count,
                'vocabulary' => [
                    'id' => $term->vocabulary->id,
                    'name' => $term->vocabulary->name,
                    'slug' => $term->vocabulary->slug,
                    'hierarchy' => $term->vocabulary->hierarchy,
                ],
                'children' => $term->children->map(fn (TaxonomyTerm $child) => [
                    'id' => $child->id,
                    'name' => $child->name,
                    'slug' => $child->slug,
                    'content_count' => $child->content_count,
                ])->values(),
            ],
            'content' => $content,
            'includeDescendants' => $includeDescendants,
        ]);
    }

    /**
     * Search terms within a vocabulary (for autocomplete).
     */
    public function searchTerms(Request $request, string $vocabId): JsonResponse
    {
        $vocabulary = Vocabulary::findOrFail($vocabId);
        $q = (string) $request->get('q', '');

        $terms = TaxonomyTerm::inVocabulary($vocabulary->id)
            ->when($q !== '', fn ($query) => $query->where('name', 'like', "%{$q}%"))
            ->ordered()
            ->limit(20)
            ->get(['id', 'name', 'slug', 'depth']);

        return response()->json(['data' => $terms]);
    }

    /**
     * Create a vocabulary.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'space_id' => ['required', 'string', 'exists:spaces,id'],
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'description' => ['nullable', 'string', 'max:5000'],
            'hierarchy' => ['boolean'],
            'allow_multiple' => ['boolean'],
            'sort_order' => ['integer', 'min:0', 'max:9999'],
        ]);

        $vocabulary = $this->taxonomy->createVocabulary($validated['space_id'], $validated);

        return redirect("/admin/taxonomy/{$vocabulary->id}")
            ->with('success', "Vocabulary \"{$vocabulary->name}\" created.");
    }

    /**
     * Update a vocabulary.
     */
    public function update(Request $request, string $id): RedirectResponse
    {
        $vocabulary = Vocabulary::findOrFail($id);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'slug' => ['sometimes', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'description' => ['nullable', 'string', 'max:5000'],
            'hierarchy' => ['boolean'],
            'allow_multiple' => ['boolean'],
            'sort_order' => ['integer', 'min:0', 'max:9999'],
        ]);

        $this->taxonomy->updateVocabulary($vocabulary, $validated);

        return back()->with('success', 'Vocabulary updated.');
    }

    /**
     * Delete a vocabulary.
     */
    public function destroy(string $id): RedirectResponse
    {
        $vocabulary = Vocabulary::findOrFail($id);
        $this->taxonomy->deleteVocabulary($vocabulary);

        return redirect('/admin/taxonomy')->with('success', 'Vocabulary deleted.');
    }

    /**
     * Create a term within a vocabulary.
     */
    public function storeTerm(Request $request, string $vocabId): RedirectResponse
    {
        $vocabulary = Vocabulary::findOrFail($vocabId);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'parent_id' => ['nullable', 'string', 'exists:taxonomy_terms,id'],
            'description' => ['nullable', 'string', 'max:5000'],
            'sort_order' => ['integer', 'min:0', 'max:9999'],
            'metadata' => ['nullable', 'array', 'max:50'],
        ]);

        // Prevent cross-vocabulary parentage
        if (! empty($validated['parent_id'])) {
            $parentInVocab = TaxonomyTerm::where('id', $validated['parent_id'])
                ->where('vocabulary_id', $vocabulary->id)
                ->exists();

            if (! $parentInVocab) {
                return back()->withErrors(['parent_id' => 'The parent term does not belong to this vocabulary.']);
            }
        }

        $this->taxonomy->createTerm($vocabulary->id, $validated);

        return back()->with('success', 'Term created.');
    }

    /**
     * Update a term.
     */
    public function updateTerm(Request $request, string $termId): RedirectResponse
    {
        $term = TaxonomyTerm::findOrFail($termId);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'slug' => ['sometimes', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'parent_id' => ['nullable', 'string', 'exists:taxonomy_terms,id'],
            'description' => ['nullable', 'string', 'max:5000'],
            'sort_order' => ['integer', 'min:0', 'max:9999'],
            'metadata' => ['nullable', 'array', 'max:50'],
        ]);

        // Prevent cross-vocabulary parentage
        if (array_key_exists('parent_id', $validated) && $validated['parent_id'] !== null) {
            $parentInVocab = TaxonomyTerm::where('id', $validated['parent_id'])
                ->where('vocabulary_id', $term->vocabulary_id)
                ->exists();

            if (! $parentInVocab) {
                return back()->withErrors(['parent_id' => 'The parent term does not belong to this vocabulary.']);
            }
        }

        try {
            $this->taxonomy->updateTerm($term, $validated);
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['parent_id' => $e->getMessage()]);
        }

        return back()->with('success', 'Term updated.');
    }

    /**
     * Delete a term.
     */
    public function destroyTerm(Request $request, string $termId): RedirectResponse
    {
        $term = TaxonomyTerm::findOrFail($termId);

        $validated = $request->validate([
            'child_strategy' => ['sometimes', 'string', 'in:reparent,cascade'],
        ]);
        $strategy = $validated['child_strategy'] ?? 'reparent';

        $this->taxonomy->deleteTerm($term, $strategy);

        return back()->with('success', 'Term deleted.');
    }

    /**
     * Move a term (AJAX).
     */
    public function moveTerm(Request $request, string $termId): \Illuminate\Http\JsonResponse
    {
        $term = TaxonomyTerm::findOrFail($termId);

        $validated = $request->validate([
            'parent_id' => ['nullable', 'string', 'exists:taxonomy_terms,id'],
        ]);

        $newParentId = $validated['parent_id'] ?? null;

        // Prevent cross-vocabulary parentage
        if ($newParentId !== null) {
            $parentInVocab = TaxonomyTerm::where('id', $newParentId)
                ->where('vocabulary_id', $term->vocabulary_id)
                ->exists();

            if (! $parentInVocab) {
                return response()->json(['error' => 'The parent term does not belong to this vocabulary.'], 422);
            }
        }

        try {
            $term = $this->taxonomy->moveTerm($term, $newParentId);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $term]);
    }

    /**
     * Reorder terms (AJAX).
     */
    public function reorderTerms(Request $request): \Illuminate\Http\JsonResponse
    {
        $validated = $request->validate([
            'ordering' => ['required', 'array'],
            'ordering.*' => ['integer'],
        ]);

        $this->taxonomy->reorderTerms($validated['ordering']);

        return response()->json(['data' => ['reordered' => true]]);
    }
}
