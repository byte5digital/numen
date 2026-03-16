<?php

namespace Tests\Unit\Competitor;

use App\Models\ContentFingerprint;
use App\Services\Competitor\SimilarityCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SimilarityCalculatorTest extends TestCase
{
    use RefreshDatabase;

    private SimilarityCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new SimilarityCalculator;
    }

    private function makeFingerprint(array $topics = [], array $entities = [], array $keywords = []): ContentFingerprint
    {
        return ContentFingerprint::factory()->make([
            'topics' => $topics,
            'entities' => $entities,
            'keywords' => $keywords,
        ]);
    }

    public function test_identical_fingerprints_score_one(): void
    {
        $fp = $this->makeFingerprint(
            topics: ['machine learning', 'deep learning'],
            entities: ['Google', 'OpenAI'],
            keywords: ['neural' => 0.5, 'network' => 0.4, 'training' => 0.3],
        );

        $score = $this->calculator->calculateSimilarity($fp, $fp);

        $this->assertEqualsWithDelta(1.0, $score, 0.001);
    }

    public function test_completely_different_fingerprints_score_zero(): void
    {
        $a = $this->makeFingerprint(
            topics: ['machine learning'],
            entities: ['Google'],
            keywords: ['neural' => 0.5, 'network' => 0.4],
        );

        $b = $this->makeFingerprint(
            topics: ['gardening'],
            entities: ['Chelsea'],
            keywords: ['flowers' => 0.6, 'soil' => 0.5],
        );

        $score = $this->calculator->calculateSimilarity($a, $b);

        $this->assertEquals(0.0, $score);
    }

    public function test_partial_overlap_scores_between_zero_and_one(): void
    {
        $a = $this->makeFingerprint(
            topics: ['machine learning', 'deep learning', 'AI'],
            entities: ['Google', 'Meta'],
            keywords: ['neural' => 0.5, 'network' => 0.4, 'training' => 0.3],
        );

        $b = $this->makeFingerprint(
            topics: ['machine learning', 'computer vision', 'AI'],
            entities: ['Google', 'Apple'],
            keywords: ['neural' => 0.5, 'model' => 0.4, 'training' => 0.2],
        );

        $score = $this->calculator->calculateSimilarity($a, $b);

        $this->assertGreaterThan(0.0, $score);
        $this->assertLessThan(1.0, $score);
    }

    public function test_jaccard_similarity_is_symmetric(): void
    {
        $a = $this->makeFingerprint(
            topics: ['topic one', 'topic two'],
            entities: ['Entity A'],
        );
        $b = $this->makeFingerprint(
            topics: ['topic one', 'topic three'],
            entities: ['Entity B'],
        );

        $this->assertEquals(
            $this->calculator->jaccardSimilarity($a, $b),
            $this->calculator->jaccardSimilarity($b, $a),
        );
    }

    public function test_cosine_similarity_is_symmetric(): void
    {
        $a = $this->makeFingerprint(
            keywords: ['word' => 0.5, 'another' => 0.3],
        );
        $b = $this->makeFingerprint(
            keywords: ['word' => 0.4, 'different' => 0.6],
        );

        $this->assertEquals(
            $this->calculator->cosineSimilarity($a, $b),
            $this->calculator->cosineSimilarity($b, $a),
        );
    }

    public function test_jaccard_with_empty_sets_returns_zero(): void
    {
        $a = $this->makeFingerprint();
        $b = $this->makeFingerprint();

        $this->assertEquals(0.0, $this->calculator->jaccardSimilarity($a, $b));
    }

    public function test_cosine_with_empty_keywords_returns_zero(): void
    {
        $a = $this->makeFingerprint();
        $b = $this->makeFingerprint(keywords: ['word' => 0.5]);

        $this->assertEquals(0.0, $this->calculator->cosineSimilarity($a, $b));
    }

    public function test_score_is_bounded_between_zero_and_one(): void
    {
        $a = $this->makeFingerprint(
            topics: ['a', 'b', 'c'],
            entities: ['X'],
            keywords: ['alpha' => 0.9, 'beta' => 0.8],
        );
        $b = $this->makeFingerprint(
            topics: ['b', 'c', 'd'],
            entities: ['X', 'Y'],
            keywords: ['alpha' => 0.7, 'gamma' => 0.6],
        );

        $score = $this->calculator->calculateSimilarity($a, $b);

        $this->assertGreaterThanOrEqual(0.0, $score);
        $this->assertLessThanOrEqual(1.0, $score);
    }

    public function test_case_insensitive_matching_on_topics_and_entities(): void
    {
        $a = $this->makeFingerprint(
            topics: ['Machine Learning'],
            entities: ['Google'],
        );
        $b = $this->makeFingerprint(
            topics: ['machine learning'],
            entities: ['google'],
        );

        $score = $this->calculator->jaccardSimilarity($a, $b);

        $this->assertEquals(1.0, $score);
    }
}
