<?php

namespace App\Services\Search;

use App\Services\Search\Results\SearchResult;
use App\Services\Search\Results\SearchResultCollection;

/**
 * Hybrid ranking using Reciprocal Rank Fusion (RRF).
 *
 * RRF formula: score = Σ (1 / (k + rank_i))
 * k = 60 (standard constant from the literature)
 *
 * Combines keyword search (Meilisearch) and semantic search (pgvector)
 * scores into a single ranked list.
 */
class SearchRanker
{
    private const K = 60;

    public function hybridMerge(
        SearchResultCollection $keyword,
        SearchResultCollection $semantic,
        float $keywordWeight = 0.5,
        float $semanticWeight = 0.5,
    ): SearchResultCollection {
        $k = self::K;
        /** @var array<string, array{score: float, item: SearchResult}> $scores */
        $scores = [];

        foreach ($keyword->items() as $rank => $item) {
            if (! isset($scores[$item->contentId])) {
                $scores[$item->contentId] = ['score' => 0.0, 'item' => $item];
            }
            $scores[$item->contentId]['score'] += $keywordWeight * (1.0 / ($k + $rank + 1));
        }

        foreach ($semantic->items() as $rank => $item) {
            if (! isset($scores[$item->contentId])) {
                $scores[$item->contentId] = ['score' => 0.0, 'item' => $item];
            }
            $scores[$item->contentId]['score'] += $semanticWeight * (1.0 / ($k + $rank + 1));
        }

        // Sort by fused score descending
        usort($scores, fn (array $a, array $b) => $b['score'] <=> $a['score']);

        $items = array_map(
            fn (array $s) => $s['item']->withScore($s['score']),
            $scores,
        );

        $total = max($keyword->total(), $semantic->total());

        return new SearchResultCollection(
            items: $items,
            total: $total,
            page: $keyword->page(),
            perPage: $keyword->perPage(),
            tierUsed: 'hybrid',
        );
    }
}
