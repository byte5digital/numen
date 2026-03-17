<?php

namespace Tests\Unit\Performance;

use App\Models\Content;
use App\Models\Performance\ContentPerformanceSnapshot;
use App\Models\Performance\ContentRefreshSuggestion;
use App\Models\Space;
use App\Services\Performance\ContentRefreshAdvisorService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContentRefreshAdvisorServiceTest extends TestCase
{
    use RefreshDatabase;

    private ContentRefreshAdvisorService $service;

    private Space $space;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ContentRefreshAdvisorService::class);
        $this->space = Space::factory()->create();
    }

    public function test_analyze_returns_no_refresh_for_nonexistent_content(): void
    {
        $result = $this->service->analyze('nonexistent-id');

        $this->assertFalse($result['needs_refresh']);
        $this->assertEmpty($result['reasons']);
        $this->assertEquals('low', $result['priority']);
    }

    public function test_analyze_detects_staleness(): void
    {
        $content = Content::factory()->create([
            'space_id' => $this->space->id,
            'status' => 'published',
            'updated_at' => Carbon::now()->subDays(120),
        ]);

        $result = $this->service->analyze($content->id);

        $this->assertTrue($result['needs_refresh']);
        $types = array_column($result['reasons'], 'type');
        $this->assertContains('staleness', $types);
    }

    public function test_analyze_detects_high_bounce_rate(): void
    {
        $content = Content::factory()->create([
            'space_id' => $this->space->id,
            'status' => 'published',
        ]);

        ContentPerformanceSnapshot::factory()->create([
            'content_id' => $content->id,
            'space_id' => $this->space->id,
            'period_type' => 'weekly',
            'period_start' => Carbon::now()->startOfWeek(),
            'bounce_rate' => 0.80,
            'avg_scroll_depth' => 0.60,
            'composite_score' => 50.0,
        ]);

        $result = $this->service->analyze($content->id);

        $this->assertTrue($result['needs_refresh']);
        $types = array_column($result['reasons'], 'type');
        $this->assertContains('high_bounce_rate', $types);
    }

    public function test_analyze_detects_low_scroll_depth(): void
    {
        $content = Content::factory()->create([
            'space_id' => $this->space->id,
            'status' => 'published',
        ]);

        ContentPerformanceSnapshot::factory()->create([
            'content_id' => $content->id,
            'space_id' => $this->space->id,
            'period_type' => 'weekly',
            'period_start' => Carbon::now()->startOfWeek(),
            'bounce_rate' => 0.30,
            'avg_scroll_depth' => 0.15,
            'composite_score' => 50.0,
        ]);

        $result = $this->service->analyze($content->id);

        $this->assertTrue($result['needs_refresh']);
        $types = array_column($result['reasons'], 'type');
        $this->assertContains('low_scroll_depth', $types);
    }

    public function test_analyze_detects_declining_views(): void
    {
        $content = Content::factory()->create([
            'space_id' => $this->space->id,
            'status' => 'published',
        ]);

        $weekStart = Carbon::now()->startOfWeek();
        foreach ([10, 20, 40, 80] as $i => $views) {
            ContentPerformanceSnapshot::factory()->create([
                'content_id' => $content->id,
                'space_id' => $this->space->id,
                'period_type' => 'weekly',
                'period_start' => $weekStart->copy()->subWeeks($i),
                'views' => $views,
                'bounce_rate' => 0.30,
                'avg_scroll_depth' => 0.60,
                'composite_score' => 50.0,
            ]);
        }

        $result = $this->service->analyze($content->id);

        $this->assertTrue($result['needs_refresh']);
        $types = array_column($result['reasons'], 'type');
        $this->assertContains('declining_views', $types);
    }

    public function test_generate_suggestions_creates_records(): void
    {
        $content = Content::factory()->create([
            'space_id' => $this->space->id,
            'status' => 'published',
            'updated_at' => Carbon::now()->subDays(120),
        ]);

        $suggestions = $this->service->generateSuggestions($this->space->id);

        $this->assertGreaterThan(0, $suggestions->count());
        $this->assertDatabaseHas('content_refresh_suggestions', [
            'space_id' => $this->space->id,
            'content_id' => $content->id,
            'status' => 'pending',
        ]);
    }

    public function test_generate_suggestions_skips_healthy_content(): void
    {
        Content::factory()->create([
            'space_id' => $this->space->id,
            'status' => 'published',
            'updated_at' => Carbon::now(),
        ]);

        $suggestions = $this->service->generateSuggestions($this->space->id);

        $this->assertEquals(0, $suggestions->count());
    }

    public function test_priority_determination(): void
    {
        $content = Content::factory()->create([
            'space_id' => $this->space->id,
            'status' => 'published',
            'updated_at' => Carbon::now()->subDays(200),
        ]);

        ContentPerformanceSnapshot::factory()->create([
            'content_id' => $content->id,
            'space_id' => $this->space->id,
            'period_type' => 'weekly',
            'period_start' => Carbon::now()->startOfWeek(),
            'bounce_rate' => 0.85,
            'avg_scroll_depth' => 0.10,
            'composite_score' => 10.0,
        ]);

        $result = $this->service->analyze($content->id);

        $this->assertTrue($result['needs_refresh']);
        $this->assertEquals('high', $result['priority']);
        $this->assertGreaterThanOrEqual(50, $result['urgency_score']);
    }

    public function test_factory_smoke_test(): void
    {
        $suggestion = ContentRefreshSuggestion::factory()->create();
        $this->assertNotNull($suggestion->id);
        $this->assertNotNull($suggestion->space_id);
    }
}
