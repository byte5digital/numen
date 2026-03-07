<?php

namespace App\Services\Search;

use App\Models\Content;
use App\Services\Search\Results\SearchResult;
use App\Services\Search\Results\SearchResultCollection;
use Illuminate\Support\Facades\Log;

/**
 * Tier 1: Instant search via Meilisearch (Laravel Scout).
 * Falls back gracefully when Meilisearch is unavailable.
 */
class InstantSearchDriver
{
    public function search(SearchQuery $query): SearchResultCollection
    {
        try {
            $builder = Content::search($query->query)
                ->where('status', 'published')
                ->where('space_id', $query->spaceId);

            if ($query->contentType) {
                $builder->where('content_type', $query->contentType);
            }

            if ($query->locale) {
                $builder->where('locale', $query->locale);
            }

            // Note: Date range filters require raw Meilisearch filters (via engine extension)
            // Handled at the Meilisearch index config level for advanced usage

            foreach ($query->taxonomyFilters as $vocab => $term) {
                $builder->where('vocabulary_slugs', $vocab);
            }

            $paginator = $builder->paginate($query->perPage, 'page', $query->page);

            $items = collect($paginator->items())->map(function (Content $content): SearchResult {
                $version = $content->currentVersion;

                return new SearchResult(
                    contentId: $content->id,
                    title: $version !== null ? $version->title : $content->slug,
                    excerpt: $version !== null ? (string) $version->excerpt : '',
                    url: '/content/'.$content->slug,
                    contentType: $content->contentType !== null ? $content->contentType->slug : '',
                    score: 1.0,
                    publishedAt: $content->published_at !== null ? $content->published_at->toISOString() : '',
                    highlights: [],
                );
            })->all();

            return new SearchResultCollection(
                items: $items,
                total: $paginator->total(),
                page: $query->page,
                perPage: $query->perPage,
                tierUsed: 'instant',
            );

        } catch (\Throwable $e) {
            Log::warning('InstantSearchDriver: search failed', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Autocomplete suggestions (ultra-fast prefix search).
     *
     * @return string[]
     */
    public function suggest(string $prefix, string $spaceId, int $limit = 5): array
    {
        try {
            $results = Content::search($prefix)
                ->where('status', 'published')
                ->where('space_id', $spaceId)
                ->take($limit)
                ->get();

            return $results->map(function (Content $c): string {
                $v = $c->currentVersion;

                return $v !== null ? $v->title : $c->slug;
            })->filter()->values()->all();

        } catch (\Throwable $e) {
            Log::warning('InstantSearchDriver: suggest failed', ['error' => $e->getMessage()]);

            return [];
        }
    }
}
