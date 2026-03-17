<?php

namespace Tests\Unit\Performance;

use App\Models\Performance\ContentPerformanceEvent;
use App\Models\Performance\ContentPerformanceSnapshot;
use App\Services\Performance\CompositeScoreCalculator;
use App\Services\Performance\PerformanceAggregatorService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PerformanceAggregatorServiceTest extends TestCase
{
    use RefreshDatabase;

    private PerformanceAggregatorService $aggregator;

    private string $contentId;

    private string $spaceId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->aggregator = new PerformanceAggregatorService(new CompositeScoreCalculator);
        $this->contentId = strtoupper(Str::ulid());
        $this->spaceId = strtoupper(Str::ulid());
    }

    public function test_aggregate_daily_creates_snapshot(): void
    {
        $date = Carbon::parse('2026-03-15');
        $this->seedEvents($date, $date);

        $snapshot = $this->aggregator->aggregateDaily($this->contentId, $date);

        $this->assertInstanceOf(ContentPerformanceSnapshot::class, $snapshot);
        $this->assertEquals('daily', $snapshot->period_type);
        $this->assertEquals('2026-03-15', $snapshot->period_start->toDateString());
        $this->assertEquals($this->contentId, $snapshot->content_id);
        $this->assertDatabaseHas('content_performance_snapshots', [
            'content_id' => $this->contentId,
            'period_type' => 'daily',
        ]);
    }

    public function test_aggregate_weekly_creates_snapshot(): void
    {
        $weekStart = Carbon::parse('2026-03-09'); // Monday
        $this->seedEvents($weekStart, $weekStart->copy()->addDays(6));

        $snapshot = $this->aggregator->aggregateWeekly($this->contentId, $weekStart);

        $this->assertEquals('weekly', $snapshot->period_type);
    }

    public function test_aggregate_monthly_creates_snapshot(): void
    {
        $monthStart = Carbon::parse('2026-03-01');
        $this->seedEvents($monthStart, $monthStart->copy()->endOfMonth());

        $snapshot = $this->aggregator->aggregateMonthly($this->contentId, $monthStart);

        $this->assertEquals('monthly', $snapshot->period_type);
        $this->assertEquals('2026-03-01', $snapshot->period_start->toDateString());
    }

    public function test_aggregates_views_and_unique_visitors(): void
    {
        $date = Carbon::parse('2026-03-15');
        $visitor1 = 'visitor-1';
        $visitor2 = 'visitor-2';

        // 3 views from 2 unique visitors
        $this->createEvent('page_view', $date, ['visitor_id' => $visitor1]);
        $this->createEvent('page_view', $date, ['visitor_id' => $visitor1]);
        $this->createEvent('page_view', $date, ['visitor_id' => $visitor2]);

        $snapshot = $this->aggregator->aggregateDaily($this->contentId, $date);

        $this->assertEquals(3, $snapshot->views);
        $this->assertEquals(2, $snapshot->unique_visitors);
    }

    public function test_aggregates_conversions_and_rate(): void
    {
        $date = Carbon::parse('2026-03-15');

        // 10 views, 2 conversions
        for ($i = 0; $i < 10; $i++) {
            $this->createEvent('page_view', $date, ['visitor_id' => "v{$i}"]);
        }
        $this->createEvent('conversion', $date);
        $this->createEvent('conversion', $date);

        $snapshot = $this->aggregator->aggregateDaily($this->contentId, $date);

        $this->assertEquals(2, $snapshot->conversions);
        $this->assertEquals(0.2, (float) $snapshot->conversion_rate);
    }

    public function test_handles_zero_views_gracefully(): void
    {
        $date = Carbon::parse('2026-03-15');

        $snapshot = $this->aggregator->aggregateDaily($this->contentId, $date);

        $this->assertEquals(0, $snapshot->views);
        $this->assertEquals(0, $snapshot->unique_visitors);
        $this->assertNull($snapshot->bounce_rate);
        $this->assertNull($snapshot->conversion_rate);
        // With no events: bounce_rate defaults to 0 in calculator, so engagement bounce component = 100
        // engagement = (0*0.4 + 0*0.3 + 100*0.3) = 30, overall = 30*0.4 = 12.0
        $this->assertEquals(12.0, (float) $snapshot->composite_score);
    }

    public function test_updates_existing_snapshot_on_reaggregation(): void
    {
        $date = Carbon::parse('2026-03-15');

        $this->createEvent('page_view', $date, ['visitor_id' => 'v1']);
        $first = $this->aggregator->aggregateDaily($this->contentId, $date);
        $this->assertEquals(1, $first->views);

        // Add more events and re-aggregate
        $this->createEvent('page_view', $date, ['visitor_id' => 'v2']);
        $this->createEvent('page_view', $date, ['visitor_id' => 'v3']);
        $snapshot = $this->aggregator->aggregateDaily($this->contentId, $date);

        $this->assertEquals(3, $snapshot->views);
        // Only 1 row should exist
        $this->assertEquals(1, ContentPerformanceSnapshot::where('content_id', $this->contentId)->where('period_type', 'daily')->count());
    }

    public function test_composite_score_is_calculated(): void
    {
        $date = Carbon::parse('2026-03-15');

        for ($i = 0; $i < 100; $i++) {
            $this->createEvent('page_view', $date, ['visitor_id' => "v{$i}"]);
        }
        $this->createEvent('time_on_page', $date, ['value' => 120]);
        $this->createEvent('scroll_depth', $date, ['value' => 0.7]);
        $this->createEvent('conversion', $date);

        $snapshot = $this->aggregator->aggregateDaily($this->contentId, $date);

        $this->assertGreaterThan(0, (float) $snapshot->composite_score);
        $this->assertLessThanOrEqual(100, (float) $snapshot->composite_score);
    }

    public function test_factory_smoke_test(): void
    {
        $snapshot = ContentPerformanceSnapshot::factory()->create();

        $this->assertNotNull($snapshot->id);
        $this->assertDatabaseHas('content_performance_snapshots', ['id' => $snapshot->id]);
    }

    private function seedEvents(Carbon $start, Carbon $end): void
    {
        $types = ['page_view', 'page_view', 'page_view', 'time_on_page', 'scroll_depth', 'bounce', 'conversion', 'social_share', 'click'];

        foreach ($types as $i => $type) {
            $occurredAt = $start->copy()->addHours($i % 24);
            if ($occurredAt->greaterThan($end)) {
                $occurredAt = $end->copy()->subHour();
            }
            $this->createEvent($type, $occurredAt, [
                'visitor_id' => "visitor-{$i}",
                'value' => match ($type) {
                    'time_on_page' => 120,
                    'scroll_depth' => 0.65,
                    default => 1,
                },
            ]);
        }
    }

    private function createEvent(string $eventType, Carbon $date, array $overrides = []): ContentPerformanceEvent
    {
        return ContentPerformanceEvent::create(array_merge([
            'space_id' => $this->spaceId,
            'content_id' => $this->contentId,
            'event_type' => $eventType,
            'source' => 'sdk',
            'value' => 1,
            'session_id' => Str::uuid()->toString(),
            'visitor_id' => Str::uuid()->toString(),
            'occurred_at' => $date->copy()->addMinutes(rand(0, 59)),
        ], $overrides));
    }
}
