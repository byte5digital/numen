<?php

namespace Tests\Unit\Performance;

use App\Events\PerformanceEventIngested;
use App\Models\Performance\ContentPerformanceEvent;
use App\Services\Performance\PerformanceIngestService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PerformanceIngestServiceTest extends TestCase
{
    use RefreshDatabase;

    private PerformanceIngestService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new PerformanceIngestService;
    }

    private function validData(array $overrides = []): array
    {
        return array_merge([
            'space_id' => 'SPACE01',
            'content_id' => 'CONTENT01',
            'event_type' => 'page_view',
            'source' => 'api',
            'session_id' => 'session-001',
            'visitor_id' => 'visitor-001',
            'value' => 1.0,
            'occurred_at' => now()->toISOString(),
        ], $overrides);
    }

    public function test_ingest_creates_event(): void
    {
        Event::fake([PerformanceEventIngested::class]);

        $event = $this->service->ingestEvent($this->validData());

        $this->assertInstanceOf(ContentPerformanceEvent::class, $event);
        $this->assertDatabaseHas('content_performance_events', [
            'content_id' => 'CONTENT01',
            'event_type' => 'page_view',
            'source' => 'api',
        ]);
    }

    public function test_ingest_fires_performance_event_ingested(): void
    {
        Event::fake([PerformanceEventIngested::class]);

        $this->service->ingestEvent($this->validData());

        Event::assertDispatched(PerformanceEventIngested::class, function (PerformanceEventIngested $e) {
            return $e->event->content_id === 'CONTENT01';
        });
    }

    public function test_ingest_deduplicates_within_5_min_window(): void
    {
        Event::fake([PerformanceEventIngested::class]);

        $data = $this->validData(['occurred_at' => Carbon::now()->subMinutes(2)->toISOString()]);

        $first = $this->service->ingestEvent($data);
        $second = $this->service->ingestEvent($data);

        $this->assertEquals($first->id, $second->id);
        $this->assertDatabaseCount('content_performance_events', 1);
        Event::assertDispatchedTimes(PerformanceEventIngested::class, 1);
    }

    public function test_ingest_does_not_dedup_different_session(): void
    {
        Event::fake([PerformanceEventIngested::class]);

        $this->service->ingestEvent($this->validData(['session_id' => 'sess-A']));
        $this->service->ingestEvent($this->validData(['session_id' => 'sess-B']));

        $this->assertDatabaseCount('content_performance_events', 2);
    }

    public function test_ingest_does_not_dedup_different_event_type(): void
    {
        Event::fake([PerformanceEventIngested::class]);

        $this->service->ingestEvent($this->validData(['event_type' => 'page_view']));
        $this->service->ingestEvent($this->validData(['event_type' => 'conversion']));

        $this->assertDatabaseCount('content_performance_events', 2);
    }

    public function test_ingest_throws_on_invalid_event_type(): void
    {
        $this->expectException(ValidationException::class);

        $this->service->ingestEvent($this->validData(['event_type' => 'bad_type']));
    }

    public function test_ingest_throws_on_missing_required_fields(): void
    {
        $this->expectException(ValidationException::class);

        $this->service->ingestEvent(['event_type' => 'page_view']);
    }

    public function test_ingest_throws_on_invalid_source(): void
    {
        $this->expectException(ValidationException::class);

        $this->service->ingestEvent($this->validData(['source' => 'fax_machine']));
    }

    public function test_batch_ingest_creates_multiple_events(): void
    {
        Event::fake([PerformanceEventIngested::class]);

        $events = [
            $this->validData(['session_id' => 'batch-1', 'event_type' => 'page_view']),
            $this->validData(['session_id' => 'batch-2', 'event_type' => 'click']),
            $this->validData(['session_id' => 'batch-3', 'event_type' => 'conversion']),
        ];

        $result = $this->service->ingestBatch($events);

        $this->assertCount(3, $result);
        $this->assertDatabaseCount('content_performance_events', 3);
        Event::assertDispatchedTimes(PerformanceEventIngested::class, 3);
    }

    public function test_batch_ingest_skips_invalid_events(): void
    {
        Event::fake([PerformanceEventIngested::class]);

        $events = [
            $this->validData(['session_id' => 'good-1']),
            ['event_type' => 'bad_missing_fields'],  // invalid
            $this->validData(['session_id' => 'good-2']),
        ];

        $result = $this->service->ingestBatch($events);

        $this->assertCount(2, $result);
        $this->assertDatabaseCount('content_performance_events', 2);
    }

    public function test_batch_ingest_deduplicates(): void
    {
        Event::fake([PerformanceEventIngested::class]);

        $data = $this->validData();
        $events = [$data, $data, $data];

        $result = $this->service->ingestBatch($events);

        $this->assertCount(3, $result); // returns existing on dedup
        $this->assertDatabaseCount('content_performance_events', 1);
        Event::assertDispatchedTimes(PerformanceEventIngested::class, 1);
    }

    public function test_accepts_all_event_types(): void
    {
        Event::fake([PerformanceEventIngested::class]);

        $types = ['page_view', 'click', 'scroll_depth', 'time_on_page', 'bounce', 'conversion', 'social_share'];

        foreach ($types as $i => $type) {
            $this->service->ingestEvent($this->validData([
                'event_type' => $type,
                'session_id' => "sess-type-{$i}",
            ]));
        }

        $this->assertDatabaseCount('content_performance_events', count($types));
    }
}
