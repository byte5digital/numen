<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TaxonomyTermResource;
use App\Models\Content;
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
     */
    public function assign(Request $request, string $id): JsonResponse
    {
        $content = Content::findOrFail($id);

        $validated = $request->validate([
            'assignments' => ['required', 'array'],
            'assignments.*.term_id' => ['required', 'string', 'exists:taxonomy_terms,id'],
            'assignments.*.sort_order' => ['integer'],
            'assignments.*.auto_assigned' => ['boolean'],
            'assignments.*.confidence' => ['nullable', 'numeric', 'min:0', 'max:1'],
        ]);

        $this->taxonomy->assignTerms($content, $validated['assignments']);

        return response()->json(['data' => ['assigned' => true]]);
    }

    /**
     * Sync terms on content (replaces all existing assignments).
     */
    public function sync(Request $request, string $id): JsonResponse
    {
        $content = Content::findOrFail($id);

        $validated = $request->validate([
            'term_ids' => ['required', 'array'],
            'term_ids.*' => ['string', 'exists:taxonomy_terms,id'],
        ]);

        $this->taxonomy->syncTerms($content, $validated['term_ids']);

        return response()->json(['data' => ['synced' => true]]);
    }

    /**
     * Remove a term from content.
     */
    public function remove(string $id, string $termId): JsonResponse
    {
        $content = Content::findOrFail($id);
        $this->taxonomy->removeTerms($content, [$termId]);

        return response()->json(['data' => ['removed' => true]]);
    }

    /**
     * Trigger AI auto-categorization for content (stub — integrates with TaxonomyCategorizationService).
     */
    public function autoCategorize(string $id): JsonResponse
    {
        $content = Content::findOrFail($id);

        // Dispatch async job for AI categorization
        \App\Jobs\CategorizePipelineContent::dispatch($content);

        return response()->json(['data' => ['queued' => true, 'content_id' => $content->id]]);
    }
}
