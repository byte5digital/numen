<?php

namespace Tests\Feature;

use App\Models\Performance\ContentAbTest;
use App\Models\Performance\ContentAbVariant;
use App\Models\Performance\ContentPerformanceEvent;
use App\Models\Performance\ContentPerformanceSnapshot;
use App\Models\Performance\ContentRefreshSuggestion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PerformanceFeedbackTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_performance_event(): void
    {
        $event = ContentPerformanceEvent::factory()->create();

        $this->assertDatabaseHas('content_performance_events', ['id' => $event->id]);
        $this->assertNotEmpty($event->event_type);
        $this->assertNotEmpty($event->source);
        $this->assertNotNull($event->occurred_at);
    }

    public function test_can_create_snapshot(): void
    {
        $snapshot = ContentPerformanceSnapshot::factory()->create();

        $this->assertDatabaseHas('content_performance_snapshots', ['id' => $snapshot->id]);
        $this->assertNotEmpty($snapshot->period_type);
        $this->assertNotNull($snapshot->period_start);
        $this->assertGreaterThanOrEqual(0, $snapshot->views);
    }

    public function test_can_create_ab_test_with_variants(): void
    {
        $test = ContentAbTest::factory()->create([
            'status' => 'running',
            'started_at' => now()->subDays(3),
            'ended_at' => null,
            'winner_variant_id' => null,
            'conclusion' => null,
        ]);

        $control = ContentAbVariant::factory()->control()->create(['test_id' => $test->id]);
        $variant = ContentAbVariant::factory()->create(['test_id' => $test->id, 'label' => 'Variant A']);

        $this->assertDatabaseHas('content_ab_tests', ['id' => $test->id, 'status' => 'running']);
        $this->assertDatabaseHas('content_ab_variants', ['id' => $control->id, 'is_control' => true]);
        $this->assertDatabaseHas('content_ab_variants', ['id' => $variant->id, 'is_control' => false]);
        $this->assertCount(2, $test->variants);
    }

    public function test_can_create_refresh_suggestion(): void
    {
        $suggestion = ContentRefreshSuggestion::factory()->create([
            'status' => 'pending',
        ]);

        $this->assertDatabaseHas('content_refresh_suggestions', ['id' => $suggestion->id]);
        $this->assertEquals('pending', $suggestion->status);
        $this->assertNotEmpty($suggestion->trigger_type);
        $this->assertNotNull($suggestion->triggered_at);
    }
}
