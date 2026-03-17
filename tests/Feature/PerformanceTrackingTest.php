<?php

namespace Tests\Feature;

use App\Events\PerformanceEventIngested;
use App\Models\Content;
use App\Models\Space;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class PerformanceTrackingTest extends TestCase
{
    use RefreshDatabase;

    private function validPayload(Content $content, array $overrides = []): array
    {
        return array_merge([
            'content_id' => $content->id,
            'event_type' => 'page_view',
            'source' => 'pixel',
            'session_id' => 'sess-abc-123',
            'visitor_id' => 'visitor-xyz',
            'value' => 1.0,
            'occurred_at' => now()->toISOString(),
        ], $overrides);
    }

    // --- Legacy /api/v1/track endpoint ---

    public function test_track_event_returns_204(): void
    {
        Event::fake([PerformanceEventIngested::class]);

        $content = Content::factory()->create();

        $response = $this->postJson('/api/v1/track', $this->validPayload($content));

        $response->assertNoContent();
        $this->assertDatabaseCount('content_performance_events', 1);
        Event::assertDispatched(PerformanceEventIngested::class);
    }

    public function test_track_event_resolves_space_from_content(): void
    {
        Event::fake([PerformanceEventIngested::class]);

        $content = Content::factory()->create();

        $this->postJson('/api/v1/track', $this->validPayload($content))->assertNoContent();

        $this->assertDatabaseHas('content_performance_events', [
            'content_id' => $content->id,
            'space_id' => $content->space_id,
        ]);
    }

    public function test_track_event_deduplicates_within_5_minutes(): void
    {
        Event::fake([PerformanceEventIngested::class]);

        $content = Content::factory()->create();
        $occurredAt = Carbon::now()->subMinutes(2)->toISOString();
        $payload = $this->validPayload($content, ['occurred_at' => $occurredAt]);

        $this->postJson('/api/v1/track', $payload)->assertNoContent();
        $this->postJson('/api/v1/track', $payload)->assertNoContent();

        $this->assertDatabaseCount('content_performance_events', 1);
        Event::assertDispatchedTimes(PerformanceEventIngested::class, 1);
    }

    public function test_track_event_does_not_dedup_outside_5_minute_window(): void
    {
        Event::fake([PerformanceEventIngested::class]);

        $content = Content::factory()->create();

        $this->postJson('/api/v1/track', $this->validPayload($content, [
            'occurred_at' => Carbon::now()->subMinutes(10)->toISOString(),
        ]))->assertNoContent();

        $this->postJson('/api/v1/track', $this->validPayload($content, [
            'occurred_at' => Carbon::now()->toISOString(),
        ]))->assertNoContent();

        $this->assertDatabaseCount('content_performance_events', 2);
    }

    public function test_track_event_validation_requires_content_id(): void
    {
        $response = $this->postJson('/api/v1/track', [
            'event_type' => 'page_view',
            'source' => 'pixel',
            'session_id' => 'sess-123',
        ]);

        $response->assertUnprocessable();
    }

    public function test_track_event_validation_rejects_invalid_event_type(): void
    {
        $content = Content::factory()->create();

        $response = $this->postJson('/api/v1/track', $this->validPayload($content, [
            'event_type' => 'invalid_type',
        ]));

        $response->assertUnprocessable();
    }

    public function test_track_event_validation_rejects_invalid_source(): void
    {
        $content = Content::factory()->create();

        $response = $this->postJson('/api/v1/track', $this->validPayload($content, [
            'source' => 'unknown_source',
        ]));

        $response->assertUnprocessable();
    }

    public function test_track_event_returns_422_for_nonexistent_content(): void
    {
        $response = $this->postJson('/api/v1/track', [
            'content_id' => '01NONEXISTENTULID00000000',
            'event_type' => 'page_view',
            'source' => 'pixel',
            'session_id' => 'sess-abc',
        ]);

        $response->assertStatus(422);
    }

    // --- Tracking pixel ---

    public function test_pixel_returns_gif_image(): void
    {
        Event::fake([PerformanceEventIngested::class]);

        $space = Space::factory()->create();
        $content = Content::factory()->create(['space_id' => $space->id]);

        $response = $this->get("/api/v1/spaces/{$space->id}/tracking/pixel.gif?cid={$content->id}&sid=sess-px");

        $response->assertOk();
        $response->assertHeader('Content-Type', 'image/gif');
        $this->assertEquals('GIF89a', substr($response->getContent(), 0, 6));
    }

    public function test_pixel_logs_page_view_event(): void
    {
        Event::fake([PerformanceEventIngested::class]);

        $space = Space::factory()->create();
        $content = Content::factory()->create(['space_id' => $space->id]);

        $this->get("/api/v1/spaces/{$space->id}/tracking/pixel.gif?cid={$content->id}&sid=sess-px");

        $this->assertDatabaseHas('content_performance_events', [
            'content_id' => $content->id,
            'space_id' => $space->id,
            'event_type' => 'page_view',
            'source' => 'pixel',
        ]);
    }

    public function test_pixel_returns_gif_even_without_content_id(): void
    {
        $space = Space::factory()->create();

        $response = $this->get("/api/v1/spaces/{$space->id}/tracking/pixel.gif");

        $response->assertOk();
        $response->assertHeader('Content-Type', 'image/gif');
    }

    // --- Bulk events endpoint ---

    public function test_events_endpoint_accepts_bulk_events(): void
    {
        Event::fake([PerformanceEventIngested::class]);

        $space = Space::factory()->create();
        $content = Content::factory()->create(['space_id' => $space->id]);

        $response = $this->postJson("/api/v1/spaces/{$space->id}/tracking/events", [
            'events' => [
                [
                    'content_id' => $content->id,
                    'event_type' => 'page_view',
                    'session_id' => 'bulk-1',
                ],
                [
                    'content_id' => $content->id,
                    'event_type' => 'scroll_depth',
                    'session_id' => 'bulk-1',
                    'value' => 75,
                    'metadata' => ['threshold' => 75],
                ],
            ],
        ]);

        $response->assertStatus(202);
        $response->assertJson(['ingested' => 2]);
        $this->assertDatabaseCount('content_performance_events', 2);
    }

    public function test_events_endpoint_validates_payload(): void
    {
        $space = Space::factory()->create();

        $response = $this->postJson("/api/v1/spaces/{$space->id}/tracking/events", [
            'events' => [],
        ]);

        $response->assertUnprocessable();
    }

    public function test_events_endpoint_rejects_invalid_event_types(): void
    {
        $space = Space::factory()->create();

        $response = $this->postJson("/api/v1/spaces/{$space->id}/tracking/events", [
            'events' => [
                [
                    'content_id' => 'test',
                    'event_type' => 'invalid',
                    'session_id' => 'sess',
                ],
            ],
        ]);

        $response->assertUnprocessable();
    }

    // --- Performance webhook intake ---

    public function test_webhook_accepts_native_format(): void
    {
        Event::fake([PerformanceEventIngested::class]);

        $content = Content::factory()->create();

        $response = $this->postJson('/api/v1/performance/webhook', [
            'content_id' => $content->id,
            'event_type' => 'page_view',
            'session_id' => 'wh-sess-1',
        ]);

        $response->assertNoContent();
        $this->assertDatabaseHas('content_performance_events', [
            'content_id' => $content->id,
            'source' => 'webhook',
        ]);
    }

    public function test_webhook_accepts_ga_batch_format(): void
    {
        Event::fake([PerformanceEventIngested::class]);

        $content = Content::factory()->create();

        $response = $this->postJson('/api/v1/performance/webhook', [
            'events' => [
                [
                    'name' => 'page_view',
                    'client_id' => 'ga-client-1',
                    'params' => [
                        'content_id' => $content->id,
                        'session_id' => 'ga-sess-1',
                    ],
                ],
            ],
        ]);

        $response->assertNoContent();
        $this->assertDatabaseHas('content_performance_events', [
            'content_id' => $content->id,
            'source' => 'webhook',
        ]);
    }

    public function test_webhook_skips_nonexistent_content(): void
    {
        Event::fake([PerformanceEventIngested::class]);

        $response = $this->postJson('/api/v1/performance/webhook', [
            'content_id' => '01NONEXISTENTULID00000000',
            'event_type' => 'page_view',
            'session_id' => 'wh-sess-1',
        ]);

        $response->assertNoContent();
        $this->assertDatabaseCount('content_performance_events', 0);
    }
}
