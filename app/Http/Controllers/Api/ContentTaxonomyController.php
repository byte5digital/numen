<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TaxonomyTermResource;
use App\Models\Content;
use App\Models\Space;
use App\Models\TaxonomyTerm;
use App\Services\Taxonomy\TaxonomyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ContentTaxonomyController extends Controller
{
    public function __construct(private readonly TaxonomyService $taxonomy) {}

    /**
     * List all terms for a piece of content.
     */
    public function terms(string $slug): AnonymousResourceCollection
    {
        $content = Content::published()
            ->where('slug', $slug)
            ->with('taxonomyTerms.vocabulary')
            ->firstOrFail();

        return TaxonomyTermResource::collection($content->taxonomyTerms);
    }

    /**
     * Assign terms to content (additive — does not remove existing).
     * Content is resolved within the current space to prevent cross-space IDOR.
     */
    public function assign(Request $request, string $id): JsonResponse
    {
        $content = $this->resolveContentForSpace($request, $id);

        $validated = $request->validate([
            'assignments' => ['required', 'array'],
            'assignments.*.term_id' => ['required', 'string', 'exists:taxonomy_terms,id'],
            'assignments.*.sort_order' => ['integer', 'min:0', 'max:9999'],
            'assignments.*.auto_assigned' => ['boolean'],
            'assignments.*.confidence' => ['nullable', 'numeric', 'min:0', 'max:1'],
        ]);

        // Validate all term IDs belong to the same space as the content
        $termIds = collect($validated['assignments'])->pluck('term_id')->all();
        $this->assertTermsBelongToSpace($termIds, $content->space_id);

        $this->taxonomy->assignTerms($content, $validated['assignments']);

        return response()->json(['data' => ['assigned' => true]]);
    }

    /**
     * Sync terms on content (replaces all existing assignments).
     * Content is resolved within the current space to prevent cross-space IDOR.
     */
    public function sync(Request $request, string $id): JsonResponse
    {
        $content = $this->resolveContentForSpace($request, $id);

        $validated = $request->validate([
            'term_ids' => ['present', 'array'],
            'term_ids.*' => ['string', 'exists:taxonomy_terms,id'],
        ]);

        // Validate all term IDs belong to the same space as the content
        if (! empty($validated['term_ids'])) {
            $this->assertTermsBelongToSpace($validated['term_ids'], $content->space_id);
        }

        $this->taxonomy->syncTerms($content, $validated['term_ids']);

        return response()->json(['data' => ['synced' => true]]);
    }

    /**
     * Remove a term from content.
     * Content is resolved within the current space to prevent cross-space IDOR.
     */
    public function remove(Request $request, string $id, string $termId): JsonResponse
    {
        $content = $this->resolveContentForSpace($request, $id);
        $this->taxonomy->removeTerms($content, [$termId]);

        return response()->json(['data' => ['removed' => true]]);
    }

    /**
     * Trigger AI auto-categorization for content.
     * Content is resolved within the current space to prevent cross-space IDOR.
     */
    public function autoCategorize(Request $request, string $id): JsonResponse
    {
        $content = $this->resolveContentForSpace($request, $id);

        // Dispatch async job for AI categorization
        \App\Jobs\CategorizePipelineContent::dispatch($content);

        return response()->json(['data' => ['queued' => true, 'content_id' => $content->id]]);
    }

    /**
     * Resolve a Content record scoped to the current space (X-Space header).
     * Aborts with 404 if the content does not belong to this space.
     */
    private function resolveContentForSpace(Request $request, string $id): Content
    {
        $spaceSlug = $request->header('X-Space', 'default');
        $space = Space::where('slug', $spaceSlug)->firstOrFail();

        return Content::where('space_id', $space->id)->findOrFail($id);
    }

    /**
     * Assert that all given term IDs belong to the specified space.
     * Aborts with 422 if any term is from a different space.
     *
     * @param  array<int, string>  $termIds
     */
    private function assertTermsBelongToSpace(array $termIds, string $spaceId): void
    {
        if (empty($termIds)) {
            return;
        }

        $ownedCount = TaxonomyTerm::whereIn('id', $termIds)
            ->whereHas('vocabulary', fn ($q) => $q->where('space_id', $spaceId))
            ->count();

        if ($ownedCount !== count($termIds)) {
            abort(422, 'One or more terms do not belong to this space.');
        }
    }
}
