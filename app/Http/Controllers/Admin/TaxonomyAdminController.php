<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Space;
use App\Models\TaxonomyTerm;
use App\Models\Vocabulary;
use App\Services\Taxonomy\TaxonomyService;
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
        ]);

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
        ]);

        $this->taxonomy->updateTerm($term, $validated);

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

        $term = $this->taxonomy->moveTerm($term, $validated['parent_id'] ?? null);

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
