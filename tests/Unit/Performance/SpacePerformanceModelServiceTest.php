<?php

namespace Tests\Unit\Performance;

use App\Jobs\BuildPerformanceModelJob;
use App\Models\Performance\ContentAttribute;
use App\Models\Performance\ContentPerformanceSnapshot;
use App\Models\Performance\PerformanceCorrelation;
use App\Models\Performance\SpacePerformanceModel;
use App\Services\Performance\PerformanceCorrelatorService;
use App\Services\Performance\SpacePerformanceModelService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SpacePerformanceModelServiceTest extends TestCase
{
    use RefreshDatabase;

    private SpacePerformanceModelService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new SpacePerformanceModelService(
            new PerformanceCorrelatorService,
        );
    }

    private function seedSpaceContent(string $spaceId, int $count = 5): void
    {
        for ($i = 0; $i < $count; $i++) {
            $contentId = $spaceId.'_C'.str_pad((string) $i, 3, '0', STR_PAD_LEFT);

            ContentAttribute::factory()->create([
                'space_id' => $spaceId,
                'content_id' => $contentId,
                'word_count' => 500 + ($i * 400),
                'image_count' => $i + 1,
                'topics' => ['seo', 'marketing'],
                'persona_id' => 'PERSONA01',
            ]);

            for ($d = 0; $d < 3; $d++) {
                ContentPerformanceSnapshot::factory()->create([
                    'space_id' => $spaceId,
                    'content_id' => $contentId,
                    'period_type' => 'daily',
                    'period_start' => now()->subDays(($i * 10) + $d + 1)->format('Y-m-d'),
                ]);
            }

            ContentPerformanceSnapshot::factory()->create([
                'space_id' => $spaceId,
                'content_id' => $contentId,
                'period_type' => 'weekly',
                'period_start' => now()->subWeeks($i + 1)->startOfWeek()->format('Y-m-d'),
                'engagement_events' => 50 + ($i * 80),
                'avg_scroll_depth' => 0.2 + ($i * 0.15),
                'views' => 100 + ($i * 150),
                'composite_score' => 20 + ($i * 15),
            ]);
        }
    }

    public function test_build_model_creates_space_model(): void
    {
        $spaceId = 'SPACE01';
        $this->seedSpaceContent($spaceId);

        $model = $this->service->buildModel($spaceId);

        $this->assertInstanceOf(SpacePerformanceModel::class, $model);
        $this->assertSame($spaceId, $model->space_id);
        $this->assertNotEmpty($model->attribute_weights);
        $this->assertNotEmpty($model->top_performers);
        $this->assertIsArray($model->bottom_performers);
        $this->assertIsArray($model->topic_scores);
        $this->assertIsArray($model->persona_scores);
        $this->assertSame(5, $model->sample_size);
        $this->assertNotNull($model->computed_at);
    }

    public function test_build_model_stores_in_database(): void
    {
        $spaceId = 'SPACE02';
        $this->seedSpaceContent($spaceId);

        $this->service->buildModel($spaceId);

        $this->assertDatabaseHas('space_performance_models', [
            'space_id' => $spaceId,
            'model_version' => 'v1',
        ]);
    }

    public function test_build_model_identifies_top_and_bottom_performers(): void
    {
        $spaceId = 'SPACE03';
        $this->seedSpaceContent($spaceId);

        $model = $this->service->buildModel($spaceId);

        $this->assertNotEmpty($model->top_performers);
        $this->assertNotEmpty($model->bottom_performers);
    }

    public function test_build_model_computes_topic_scores(): void
    {
        $spaceId = 'SPACE04';
        $this->seedSpaceContent($spaceId);

        $model = $this->service->buildModel($spaceId);

        $this->assertArrayHasKey('seo', $model->topic_scores);
        $this->assertArrayHasKey('marketing', $model->topic_scores);
    }

    public function test_get_recommendations_returns_empty_without_model(): void
    {
        $recommendations = $this->service->getRecommendations('NONEXISTENT');
        $this->assertSame([], $recommendations);
    }

    public function test_get_recommendations_returns_actionable_items(): void
    {
        $spaceId = 'SPACE05';
        $this->seedSpaceContent($spaceId, 6);

        $this->service->buildModel($spaceId);

        $recommendations = $this->service->getRecommendations($spaceId);

        foreach ($recommendations as $rec) {
            $this->assertArrayHasKey('type', $rec);
            $this->assertArrayHasKey('message', $rec);
            $this->assertArrayHasKey('confidence', $rec);
            $this->assertArrayHasKey('attribute', $rec);
        }
    }

    public function test_refresh_model_clears_and_rebuilds(): void
    {
        $spaceId = 'SPACE06';
        $this->seedSpaceContent($spaceId);

        $this->service->buildModel($spaceId);

        $correlationCount = PerformanceCorrelation::where('space_id', $spaceId)->count();
        $this->assertGreaterThan(0, $correlationCount);

        $model = $this->service->refreshModel($spaceId);

        $this->assertInstanceOf(SpacePerformanceModel::class, $model);
        $this->assertNotNull($model->computed_at);
    }

    public function test_build_performance_model_job_dispatches(): void
    {
        Queue::fake();

        BuildPerformanceModelJob::dispatch('SPACE_TEST');

        Queue::assertPushed(BuildPerformanceModelJob::class, function ($job) {
            return $job->spaceId === 'SPACE_TEST';
        });
    }

    public function test_build_performance_model_job_runs(): void
    {
        $spaceId = 'SPACE07';
        $this->seedSpaceContent($spaceId);

        $job = new BuildPerformanceModelJob($spaceId);
        $job->handle($this->service);

        $this->assertDatabaseHas('space_performance_models', [
            'space_id' => $spaceId,
        ]);
    }

    public function test_model_confidence_scales_with_sample_size(): void
    {
        $spaceId1 = 'SPACE_SMALL';
        $this->seedSpaceContent($spaceId1, 3);

        $spaceId2 = 'SPACE_LARGE';
        $this->seedSpaceContent($spaceId2, 10);

        $model1 = $this->service->buildModel($spaceId1);
        $model2 = $this->service->buildModel($spaceId2);

        $this->assertLessThanOrEqual((float) $model2->model_confidence, (float) $model1->model_confidence + 0.01);
    }

    public function test_factory_creates_valid_model(): void
    {
        $model = SpacePerformanceModel::factory()->create();

        $this->assertNotNull($model->id);
        $this->assertNotEmpty($model->attribute_weights);
        $this->assertNotEmpty($model->top_performers);
    }
}
