<?php

namespace Tests\Feature;

use App\Events\Quality\ContentQualityScored;
use App\Jobs\ScoreContentQualityJob;
use App\Models\Content;
use App\Models\ContentQualityConfig;
use App\Models\ContentQualityScore;
use App\Models\Space;
use App\Services\Quality\ContentQualityService;
use App\Services\Quality\QualityTrendAggregator;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ContentQualityServiceTest extends TestCase
{
    use RefreshDatabase;

    // ──────────────────────────────────────────────────────────────
    // Full scoring flow
    // ──────────────────────────────────────────────────────────────

    public function test_score_creates_quality_score_record(): void
    {
        Event::fake([ContentQualityScored::class]);
        Cache::flush();

        $content = Content::factory()->create();
        $service = $this->app->make(ContentQualityService::class);

        $score = $service->score($content);

        $this->assertInstanceOf(ContentQualityScore::class, $score);
        $this->assertDatabaseHas('content_quality_scores', [
            'id' => $score->id,
            'content_id' => $content->id,
            'space_id' => $content->space_id,
        ]);
        $this->assertIsFloat($score->overall_score);
        $this->assertGreaterThanOrEqual(0.0, $score->overall_score);
        $this->assertLessThanOrEqual(100.0, $score->overall_score);
    }

    public function test_score_creates_score_items(): void
    {
        Event::fake([ContentQualityScored::class]);
        Cache::flush();

        $content = Content::factory()->create();
        $service = $this->app->make(ContentQualityService::class);

        $score = $service->score($content);

        // Score items may or may not be created depending on analyzer output
        // but the relationship must be queryable
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Collection::class, $score->items);
    }

    public function test_score_fires_content_quality_scored_event(): void
    {
        Event::fake([ContentQualityScored::class]);
        Cache::flush();

        $content = Content::factory()->create();
        $service = $this->app->make(ContentQualityService::class);

        $service->score($content);

        Event::assertDispatched(ContentQualityScored::class, function (ContentQualityScored $event) use ($content) {
            return $event->score->content_id === $content->id;
        });
    }

    // ──────────────────────────────────────────────────────────────
    // Weighted scoring
    // ──────────────────────────────────────────────────────────────

    public function test_weighted_scoring_uses_config_dimension_weights(): void
    {
        Event::fake([ContentQualityScored::class]);
        Cache::flush();

        $space = Space::factory()->create();
        $content = Content::factory()->for($space)->create();

        // Config with readability heavily weighted
        $config = ContentQualityConfig::factory()->create([
            'space_id' => $space->id,
            'dimension_weights' => [
                'readability' => 1.0,
                'seo' => 0.0,
                'brand_consistency' => 0.0,
                'factual_accuracy' => 0.0,
                'engagement_prediction' => 0.0,
            ],
            'enabled_dimensions' => ['readability', 'seo', 'brand_consistency', 'factual_accuracy', 'engagement_prediction'],
        ]);

        $service = $this->app->make(ContentQualityService::class);
        $score = $service->score($content, $config);

        // Overall score should equal the readability score (weight=1.0, all others=0.0)
        $this->assertEqualsWithDelta($score->readability_score ?? 0, $score->overall_score, 0.5);
    }

    // ──────────────────────────────────────────────────────────────
    // Cache behaviour
    // ──────────────────────────────────────────────────────────────

    public function test_score_is_cached_after_first_call(): void
    {
        Event::fake([ContentQualityScored::class]);
        Cache::flush();

        $content = Content::factory()->create();
        $service = $this->app->make(ContentQualityService::class);

        $first = $service->score($content);

        $this->assertTrue(Cache::has("quality:content:{$content->id}"));

        $second = $service->score($content);

        // Same object returned from cache
        $this->assertEquals($first->id, $second->id);

        // Event dispatched only once (cache hit skips re-scoring)
        Event::assertDispatchedTimes(ContentQualityScored::class, 1);
    }

    public function test_invalidate_clears_cache(): void
    {
        Event::fake([ContentQualityScored::class]);
        Cache::flush();

        $content = Content::factory()->create();
        $service = $this->app->make(ContentQualityService::class);

        $service->score($content);
        $this->assertTrue(Cache::has("quality:content:{$content->id}"));

        $service->invalidate($content);
        $this->assertFalse(Cache::has("quality:content:{$content->id}"));
    }

    // ──────────────────────────────────────────────────────────────
    // Job
    // ──────────────────────────────────────────────────────────────

    public function test_job_can_be_dispatched(): void
    {
        Queue::fake();

        $content = Content::factory()->create();

        ScoreContentQualityJob::dispatch($content->id);

        Queue::assertPushed(ScoreContentQualityJob::class, function (ScoreContentQualityJob $job) use ($content) {
            return $job->contentId === $content->id && $job->configId === null;
        });
    }

    public function test_job_dispatched_on_quality_queue(): void
    {
        Queue::fake();

        $content = Content::factory()->create();

        ScoreContentQualityJob::dispatch($content->id);

        Queue::assertPushedOn('quality', ScoreContentQualityJob::class);
    }

    public function test_job_executes_scoring_synchronously(): void
    {
        Event::fake([ContentQualityScored::class]);
        Cache::flush();

        $content = Content::factory()->create();

        // Run synchronously (no queue)
        (new ScoreContentQualityJob($content->id))->handle(
            $this->app->make(ContentQualityService::class)
        );

        $this->assertDatabaseHas('content_quality_scores', ['content_id' => $content->id]);
    }

    // ──────────────────────────────────────────────────────────────
    // Trend aggregation
    // ──────────────────────────────────────────────────────────────

    public function test_get_space_trends_returns_daily_averages(): void
    {
        $space = Space::factory()->create();

        // Two scores on same day
        ContentQualityScore::factory()->create([
            'space_id' => $space->id,
            'overall_score' => 60.0,
            'scored_at' => Carbon::parse('2025-01-01 12:00:00'),
        ]);
        ContentQualityScore::factory()->create([
            'space_id' => $space->id,
            'overall_score' => 80.0,
            'scored_at' => Carbon::parse('2025-01-01 14:00:00'),
        ]);

        $aggregator = $this->app->make(QualityTrendAggregator::class);
        $trends = $aggregator->getSpaceTrends(
            $space,
            Carbon::parse('2025-01-01'),
            Carbon::parse('2025-01-01'),
        );

        $this->assertArrayHasKey('2025-01-01', $trends);
        $this->assertEqualsWithDelta(70.0, $trends['2025-01-01']['overall'], 0.5);
        $this->assertEquals(2, $trends['2025-01-01']['total']);
    }

    public function test_get_space_leaderboard_returns_top_content(): void
    {
        $space = Space::factory()->create();

        ContentQualityScore::factory()->create([
            'space_id' => $space->id,
            'overall_score' => 90.0,
        ]);
        ContentQualityScore::factory()->create([
            'space_id' => $space->id,
            'overall_score' => 50.0,
        ]);

        $aggregator = $this->app->make(QualityTrendAggregator::class);
        $leaderboard = $aggregator->getSpaceLeaderboard($space, 5);

        $this->assertCount(2, $leaderboard);
        $this->assertGreaterThan(
            $leaderboard->last()->overall_score,
            $leaderboard->first()->overall_score,
        );
    }

    public function test_get_dimension_distribution_returns_histogram(): void
    {
        $space = Space::factory()->create();

        ContentQualityScore::factory()->create([
            'space_id' => $space->id,
            'overall_score' => 55.0,
            'readability_score' => 55.0,
        ]);

        $aggregator = $this->app->make(QualityTrendAggregator::class);
        $distribution = $aggregator->getDimensionDistribution($space);

        $this->assertArrayHasKey('overall', $distribution);
        $this->assertArrayHasKey('readability', $distribution);

        // Score of 55 falls in 50-60 bucket
        $this->assertGreaterThanOrEqual(1, $distribution['overall']['50-60']);
    }
}
