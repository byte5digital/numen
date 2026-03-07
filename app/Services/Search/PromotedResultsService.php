<?php

namespace App\Services\Search;

use App\Models\PromotedResult;
use App\Services\Search\Results\SearchResult;
use App\Services\Search\Results\SearchResultCollection;
use Illuminate\Support\Facades\Log;

/**
 * Injects promoted (pinned) results at the top of search results.
 */
class PromotedResultsService
{
    public function apply(SearchResultCollection $results, SearchQuery $query): SearchResultCollection
    {
        try {
            $promoted = PromotedResult::where('space_id', $query->spaceId)
                ->where('query', 'LIKE', '%'.strtolower(trim($query->query)).'%')
                ->with(['content.currentVersion', 'content.contentType'])
                ->orderBy('position')
                ->get()
                ->filter(fn (PromotedResult $p) => $p->isActive());

            if ($promoted->isEmpty()) {
                return $results;
            }

            // Build promoted result items (prepend to results)
            $promotedItems = $promoted->map(function (PromotedResult $p): SearchResult {
                $content = $p->content;
                $version = $content !== null ? $content->currentVersion : null;
                $contentType = ($content !== null && $content->contentType !== null) ? $content->contentType->slug : '';

                return new SearchResult(
                    contentId: $p->content_id,
                    title: $version !== null ? $version->title : '',
                    excerpt: $version !== null ? (string) $version->excerpt : '',
                    url: $content !== null ? '/content/'.$content->slug : '',
                    contentType: $contentType,
                    score: 9999.0,  // Pinned results always float to top
                    publishedAt: ($content !== null && $content->published_at !== null) ? $content->published_at->toISOString() : '',
                    highlights: [],
                    metadata: ['promoted' => true, 'position' => $p->position],
                );
            })->values()->all();

            // Filter out promoted items from regular results (avoid duplicates)
            $promotedIds = $promoted->pluck('content_id')->toArray();
            $regularItems = array_filter(
                $results->items(),
                fn (SearchResult $r) => ! in_array($r->contentId, $promotedIds, true),
            );

            $allItems = array_merge($promotedItems, array_values($regularItems));

            return new SearchResultCollection(
                items: $allItems,
                total: $results->total() + count($promotedItems),
                page: $results->page(),
                perPage: $results->perPage(),
                tierUsed: $results->tierUsed(),
            );

        } catch (\Throwable $e) {
            Log::warning('PromotedResultsService: failed', ['error' => $e->getMessage()]);

            return $results;
        }
    }
}
