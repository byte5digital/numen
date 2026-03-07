<?php

namespace Tests\Unit\Search;

use App\Services\Search\Results\SearchResult;
use App\Services\Search\Results\SearchResultCollection;
use App\Services\Search\SearchRanker;
use Tests\TestCase;

class SearchRankerTest extends TestCase
{
    private SearchRanker $ranker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ranker = new SearchRanker;
    }

    // ── Basic Hybrid Merge ───────────────────────────────────────────────────

    public function test_hybrid_merge_combines_results_from_both_tiers(): void
    {
        $keyword = $this->makeCollection([
            $this->makeResult('content-1', 'First Result'),
            $this->makeResult('content-2', 'Second Result'),
        ], 'instant');

        $semantic = $this->makeCollection([
            $this->makeResult('content-3', 'Semantic Result A'),
            $this->makeResult('content-4', 'Semantic Result B'),
        ], 'semantic');

        $merged = $this->ranker->hybridMerge($keyword, $semantic);

        $ids = array_map(fn (SearchResult $r) => $r->contentId, $merged->items());
        $this->assertContains('content-1', $ids);
        $this->assertContains('content-2', $ids);
        $this->assertContains('content-3', $ids);
        $this->assertContains('content-4', $ids);
    }

    public function test_hybrid_merge_uses_hybrid_as_tier(): void
    {
        $keyword = $this->makeCollection([$this->makeResult('c1', 'A')], 'instant');
        $semantic = $this->makeCollection([$this->makeResult('c2', 'B')], 'semantic');

        $merged = $this->ranker->hybridMerge($keyword, $semantic);

        $this->assertSame('hybrid', $merged->tierUsed());
    }

    // ── Deduplication ────────────────────────────────────────────────────────

    public function test_hybrid_merge_deduplicates_by_content_id(): void
    {
        $keyword = $this->makeCollection([
            $this->makeResult('shared-id', 'Result Keyword'),
            $this->makeResult('only-keyword', 'Keyword Only'),
        ], 'instant');

        $semantic = $this->makeCollection([
            $this->makeResult('shared-id', 'Result Semantic'),
            $this->makeResult('only-semantic', 'Semantic Only'),
        ], 'semantic');

        $merged = $this->ranker->hybridMerge($keyword, $semantic);

        $ids = array_map(fn (SearchResult $r) => $r->contentId, $merged->items());
        $this->assertCount(count(array_unique($ids)), $ids, 'Duplicate content IDs found in merged results');
    }

    // ── RRF Scoring ──────────────────────────────────────────────────────────

    public function test_item_appearing_in_both_tiers_scores_higher_than_single_tier(): void
    {
        $sharedResult = $this->makeResult('shared', 'Shared');
        $keywordOnly = $this->makeResult('keyword-only', 'Keyword Only');
        $semanticOnly = $this->makeResult('semantic-only', 'Semantic Only');

        $keyword = $this->makeCollection([$sharedResult, $keywordOnly], 'instant');
        $semantic = $this->makeCollection([$sharedResult, $semanticOnly], 'semantic');

        $merged = $this->ranker->hybridMerge($keyword, $semantic);

        $scores = [];
        foreach ($merged->items() as $item) {
            $scores[$item->contentId] = $item->score;
        }

        $this->assertGreaterThan($scores['keyword-only'], $scores['shared']);
        $this->assertGreaterThan($scores['semantic-only'], $scores['shared']);
    }

    public function test_results_are_sorted_by_rrf_score_descending(): void
    {
        // Top-ranked in both lists → highest RRF score
        $results = [
            $this->makeResult('id-1', 'Rank 1 in both'),
            $this->makeResult('id-2', 'Rank 2 in keyword'),
            $this->makeResult('id-3', 'Rank 3 in semantic'),
        ];

        $keyword = $this->makeCollection($results, 'instant');
        $semantic = $this->makeCollection(array_reverse($results), 'semantic');

        $merged = $this->ranker->hybridMerge($keyword, $semantic);
        $scores = array_map(fn (SearchResult $r) => $r->score, $merged->items());

        for ($i = 1; $i < count($scores); $i++) {
            $this->assertGreaterThanOrEqual($scores[$i], $scores[$i - 1], 'Results not sorted by score');
        }
    }

    public function test_rrf_score_uses_k_equals_60_constant(): void
    {
        // For rank 0 (first result) with weight 0.5: score = 0.5 * 1/(60+1) = 0.5/61
        $result = $this->makeResult('only-one', 'Only');
        $keyword = $this->makeCollection([$result], 'instant');
        $semantic = $this->makeCollection([], 'semantic');

        $merged = $this->ranker->hybridMerge($keyword, $semantic, 0.5, 0.5);

        $expectedScore = 0.5 * (1.0 / (60 + 0 + 1));
        $actualScore = $merged->items()[0]->score;

        $this->assertEqualsWithDelta($expectedScore, $actualScore, 0.0001);
    }

    // ── Empty Inputs ─────────────────────────────────────────────────────────

    public function test_hybrid_merge_with_empty_keyword_results_returns_semantic(): void
    {
        $keyword = $this->makeCollection([], 'instant');
        $semantic = $this->makeCollection([
            $this->makeResult('s1', 'Semantic 1'),
            $this->makeResult('s2', 'Semantic 2'),
        ], 'semantic');

        $merged = $this->ranker->hybridMerge($keyword, $semantic);

        $this->assertCount(2, $merged->items());
    }

    public function test_hybrid_merge_with_empty_semantic_results_returns_keyword(): void
    {
        $keyword = $this->makeCollection([
            $this->makeResult('k1', 'Keyword 1'),
        ], 'instant');
        $semantic = $this->makeCollection([], 'semantic');

        $merged = $this->ranker->hybridMerge($keyword, $semantic);

        $this->assertCount(1, $merged->items());
    }

    public function test_hybrid_merge_with_both_empty_returns_empty_collection(): void
    {
        $keyword = $this->makeCollection([], 'instant');
        $semantic = $this->makeCollection([], 'semantic');

        $merged = $this->ranker->hybridMerge($keyword, $semantic);

        $this->assertTrue($merged->isEmpty());
    }

    // ── Total Count ──────────────────────────────────────────────────────────

    public function test_total_is_max_of_both_collections(): void
    {
        $keyword = $this->makeCollection(
            [$this->makeResult('k1', 'K1')],
            'instant',
            total: 100
        );
        $semantic = $this->makeCollection(
            [$this->makeResult('s1', 'S1')],
            'semantic',
            total: 150
        );

        $merged = $this->ranker->hybridMerge($keyword, $semantic);

        $this->assertSame(150, $merged->total());
    }

    // ── Custom Weights ───────────────────────────────────────────────────────

    public function test_custom_semantic_weight_boosts_semantic_results(): void
    {
        $keyword = $this->makeCollection([
            $this->makeResult('k1', 'Keyword First'),
        ], 'instant');
        $semantic = $this->makeCollection([
            $this->makeResult('s1', 'Semantic First'),
        ], 'semantic');

        // Heavy semantic weight — semantic result should win
        $merged = $this->ranker->hybridMerge($keyword, $semantic, 0.1, 0.9);
        $topId = $merged->items()[0]->contentId;

        $this->assertSame('s1', $topId);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function makeResult(string $id, string $title, float $score = 1.0): SearchResult
    {
        return new SearchResult(
            contentId: $id,
            title: $title,
            excerpt: '',
            url: '/content/'.$id,
            contentType: 'article',
            score: $score,
            publishedAt: '2026-01-01T00:00:00Z',
        );
    }

    /** @param SearchResult[] $items */
    private function makeCollection(array $items, string $tier, int $total = 0): SearchResultCollection
    {
        return new SearchResultCollection(
            items: $items,
            total: $total ?: count($items),
            page: 1,
            perPage: 20,
            tierUsed: $tier,
        );
    }
}
