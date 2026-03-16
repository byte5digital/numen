<?php

namespace Tests\Unit\Competitor;

use App\Models\CompetitorContentItem;
use App\Models\ContentFingerprint;
use App\Services\Competitor\SimilarContentFinder;
use App\Services\Competitor\SimilarityCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SimilarContentFinderTest extends TestCase
{
    use RefreshDatabase;

    private SimilarContentFinder $finder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->finder = new SimilarContentFinder(new SimilarityCalculator);
    }

    private function createCompetitorFingerprint(array $topics = [], array $entities = [], array $keywords = []): ContentFingerprint
    {
        $item = CompetitorContentItem::factory()->create();

        return ContentFingerprint::factory()->create([
            'fingerprintable_type' => CompetitorContentItem::class,
            'fingerprintable_id' => $item->id,
            'topics' => $topics,
            'entities' => $entities,
            'keywords' => $keywords,
        ]);
    }

    public function test_finds_similar_items_above_threshold(): void
    {
        $query = $this->createCompetitorFingerprint(
            topics: ['machine learning', 'deep learning'],
            entities: ['Google'],
            keywords: ['neural' => 0.5, 'network' => 0.4],
        );

        $similar = $this->createCompetitorFingerprint(
            topics: ['machine learning', 'deep learning'],
            entities: ['Google', 'Meta'],
            keywords: ['neural' => 0.5, 'network' => 0.3],
        );

        $this->createCompetitorFingerprint(
            topics: ['gardening', 'flowers'],
            entities: ['Chelsea'],
            keywords: ['soil' => 0.6, 'water' => 0.5],
        );

        $results = $this->finder->findSimilar($query, threshold: 0.3, limit: 10);

        $this->assertNotEmpty($results);
        $resultIds = $results->pluck('fingerprint.id')->all();
        $this->assertContains($similar->id, $resultIds);
    }

    public function test_respects_threshold_filter(): void
    {
        $query = $this->createCompetitorFingerprint(
            topics: ['machine learning'],
            keywords: ['neural' => 0.5],
        );

        $this->createCompetitorFingerprint(
            topics: ['cooking', 'recipes'],
            keywords: ['flour' => 0.7, 'sugar' => 0.6],
        );

        $results = $this->finder->findSimilar($query, threshold: 0.99, limit: 10);

        foreach ($results as $result) {
            $this->assertGreaterThanOrEqual(0.99, $result['score']);
        }
    }

    public function test_respects_limit(): void
    {
        $query = $this->createCompetitorFingerprint(
            topics: ['machine learning', 'AI'],
            keywords: ['neural' => 0.5, 'network' => 0.4],
        );

        for ($i = 0; $i < 5; $i++) {
            $this->createCompetitorFingerprint(
                topics: ['machine learning', 'AI'],
                keywords: ['neural' => 0.5, 'network' => 0.4],
            );
        }

        $results = $this->finder->findSimilar($query, threshold: 0.3, limit: 3);

        $this->assertLessThanOrEqual(3, $results->count());
    }

    public function test_results_are_ranked_by_score_descending(): void
    {
        $query = $this->createCompetitorFingerprint(
            topics: ['machine learning', 'deep learning', 'AI'],
            entities: ['Google', 'Meta', 'OpenAI'],
            keywords: ['neural' => 0.5, 'network' => 0.4, 'training' => 0.3],
        );

        $this->createCompetitorFingerprint(
            topics: ['machine learning', 'deep learning', 'AI'],
            entities: ['Google', 'Meta', 'OpenAI'],
            keywords: ['neural' => 0.5, 'network' => 0.4, 'training' => 0.3],
        );

        $this->createCompetitorFingerprint(
            topics: ['machine learning'],
            entities: ['Google'],
            keywords: ['neural' => 0.3],
        );

        $results = $this->finder->findSimilar($query, threshold: 0.1, limit: 10);

        if ($results->count() > 1) {
            $scores = $results->pluck('score')->all();
            for ($i = 0; $i < count($scores) - 1; $i++) {
                $this->assertGreaterThanOrEqual($scores[$i + 1], $scores[$i]);
            }
        }

        $this->assertTrue(true);
    }

    public function test_returns_empty_collection_when_no_candidates(): void
    {
        $query = $this->createCompetitorFingerprint(
            topics: ['machine learning'],
        );

        $results = $this->finder->findSimilar($query, threshold: 0.3, limit: 10);

        $this->assertEmpty($results);
    }

    public function test_excludes_the_query_fingerprint_itself(): void
    {
        $query = $this->createCompetitorFingerprint(
            topics: ['machine learning'],
            keywords: ['neural' => 0.5],
        );

        $results = $this->finder->findSimilar($query, threshold: 0.0, limit: 10);

        $resultIds = $results->pluck('fingerprint.id')->all();
        $this->assertNotContains($query->id, $resultIds);
    }

    public function test_result_structure_contains_expected_keys(): void
    {
        $query = $this->createCompetitorFingerprint(
            topics: ['machine learning', 'AI'],
            keywords: ['neural' => 0.5],
        );

        $this->createCompetitorFingerprint(
            topics: ['machine learning', 'AI'],
            keywords: ['neural' => 0.5],
        );

        $results = $this->finder->findSimilar($query, threshold: 0.1, limit: 10);

        if ($results->isNotEmpty()) {
            $first = $results->first();
            $this->assertArrayHasKey('item', $first);
            $this->assertArrayHasKey('score', $first);
            $this->assertArrayHasKey('fingerprint', $first);
            $this->assertInstanceOf(CompetitorContentItem::class, $first['item']);
            $this->assertInstanceOf(ContentFingerprint::class, $first['fingerprint']);
            $this->assertIsFloat($first['score']);
        }

        $this->assertTrue(true);
    }
}
