<?php

namespace App\Services\Search;

use App\Models\Content;
use App\Services\Search\Results\AskResponse;
use App\Services\Search\Results\SearchResult;
use App\Services\Search\Results\SearchResultCollection;
use Illuminate\Support\Facades\Log;

/**
 * Central search orchestrator.
 *
 * Tier routing:
 * - 'instant'  → Meilisearch only
 * - 'semantic' → pgvector only
 * - 'hybrid'   → both merged via Reciprocal Rank Fusion
 *
 * Graceful degradation:
 * - Tier 1 fails → pgvector semantic → SQL LIKE
 * - Tier 2 fails → Meilisearch keyword → SQL LIKE
 * - Tier 3 fails → returns Tier 2 results
 */
class SearchService
{
    public function __construct(
        private readonly InstantSearchDriver $instant,
        private readonly SemanticSearchDriver $semantic,
        private readonly ConversationalDriver $conversational,
        private readonly SearchRanker $ranker,
        private readonly PromotedResultsService $promoted,
        private readonly SearchAnalyticsRecorder $analytics,
        private readonly SearchCapabilityDetector $capabilities,
    ) {}

    public function search(SearchQuery $query): SearchResultCollection
    {
        $startTime = microtime(true);

        $caps = $this->capabilities->detect();

        $results = match ($query->mode) {
            'instant' => $this->searchInstant($query, $caps),
            'semantic' => $this->searchSemantic($query, $caps),
            default => $this->searchHybrid($query, $caps),
        };

        // Inject promoted results
        $results = $this->promoted->apply($results, $query);

        // Record analytics (non-blocking, catches own errors)
        $this->analytics->record($query, $results, microtime(true) - $startTime);

        return $results;
    }

    public function ask(AskQuery $query): AskResponse
    {
        $caps = $this->capabilities->detect();

        if (! $caps->hasAsk()) {
            // Fallback: return semantic search results with a no-answer message
            Log::info('SearchService: ask tier unavailable, returning no-answer');

            return AskResponse::noAnswer($query->question, $query->conversationId);
        }

        return $this->conversational->ask($query, $caps);
    }

    /**
     * @return string[]
     */
    public function suggest(string $prefix, string $spaceId, int $limit = 5): array
    {
        $caps = $this->capabilities->detect();

        if ($caps->hasInstant()) {
            return $this->instant->suggest($prefix, $spaceId, $limit);
        }

        // SQL LIKE fallback for suggestions
        return Content::published()
            ->where('space_id', $spaceId)
            ->whereHas('currentVersion', fn ($q) => $q->where('title', 'LIKE', "{$prefix}%"))
            ->with('currentVersion')
            ->limit($limit)
            ->get()
            ->map(fn (Content $c) => $c->currentVersion !== null ? $c->currentVersion->title : '')
            ->filter()
            ->values()
            ->all();
    }

    private function searchInstant(SearchQuery $query, SearchCapabilities $caps): SearchResultCollection
    {
        if ($caps->hasInstant()) {
            try {
                return $this->instant->search($query);
            } catch (\Throwable $e) {
                Log::warning('SearchService: instant search failed, falling back', ['error' => $e->getMessage()]);
            }
        }

        return $this->sqlFallback($query);
    }

    private function searchSemantic(SearchQuery $query, SearchCapabilities $caps): SearchResultCollection
    {
        if ($caps->hasSemantic()) {
            try {
                return $this->semantic->search($query);
            } catch (\Throwable $e) {
                Log::warning('SearchService: semantic search failed, falling back', ['error' => $e->getMessage()]);
            }
        }

        // Fallback to instant
        if ($caps->hasInstant()) {
            try {
                return $this->instant->search($query);
            } catch (\Throwable $e) {
                Log::warning('SearchService: instant fallback failed too', ['error' => $e->getMessage()]);
            }
        }

        return $this->sqlFallback($query);
    }

    private function searchHybrid(SearchQuery $query, SearchCapabilities $caps): SearchResultCollection
    {
        $instantResults = null;
        $semanticResults = null;

        if ($caps->hasInstant()) {
            try {
                $instantResults = $this->instant->search($query);
            } catch (\Throwable $e) {
                Log::warning('SearchService: instant search failed in hybrid', ['error' => $e->getMessage()]);
            }
        }

        if ($caps->hasSemantic()) {
            try {
                $semanticResults = $this->semantic->search($query);
            } catch (\Throwable $e) {
                Log::warning('SearchService: semantic search failed in hybrid', ['error' => $e->getMessage()]);
            }
        }

        // Merge & rank
        if ($instantResults && $semanticResults) {
            return $this->ranker->hybridMerge($instantResults, $semanticResults);
        }

        if ($instantResults) {
            return $instantResults;
        }

        if ($semanticResults) {
            return $semanticResults;
        }

        return $this->sqlFallback($query);
    }

    private function sqlFallback(SearchQuery $query): SearchResultCollection
    {
        // Escape LIKE wildcards to prevent pattern injection
        $term = addcslashes($query->query, '%_\\');

        $results = Content::published()
            ->where('space_id', $query->spaceId)
            ->whereHas('currentVersion', function ($q) use ($term): void {
                $q->where('title', 'LIKE', "%{$term}%")
                    ->orWhere('body', 'LIKE', "%{$term}%")
                    ->orWhere('excerpt', 'LIKE', "%{$term}%");
            })
            ->when($query->contentType, fn ($q) => $q->ofType($query->contentType))
            ->when($query->locale, fn ($q) => $q->forLocale($query->locale))
            ->with(['currentVersion', 'contentType'])
            ->paginate($query->perPage, ['*'], 'page', $query->page);

        $items = collect($results->items())->map(function (Content $content): SearchResult {
            $version = $content->currentVersion;

            return new SearchResult(
                contentId: $content->id,
                title: $version !== null ? $version->title : $content->slug,
                excerpt: $version !== null ? (string) $version->excerpt : '',
                url: '/content/'.$content->slug,
                contentType: $content->contentType !== null ? $content->contentType->slug : '',
                score: 1.0,
                publishedAt: $content->published_at !== null ? $content->published_at->toISOString() : '',
            );
        })->all();

        return new SearchResultCollection(
            items: $items,
            total: $results->total(),
            page: $query->page,
            perPage: $query->perPage,
            tierUsed: 'sql',
        );
    }
}
