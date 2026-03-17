<?php

namespace Tests\Feature;

use App\Events\PerformanceEventIngested;
use App\Models\Content;
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
            'event_type' => 'view',
            'source' => 'pixel',
            'session_id' => 'sess-abc-123',
            'visitor_id' => 'visitor-xyz',
            'value' => 1.0,
            'occurred_at' => now()->toISOString(),
        ], $overrides);
    }

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

        // Should still only have one record (deduplicated)
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
            'event_type' => 'view',
            'source' => 'pixel',
            'session_id' => 'sess-123',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['content_id']);
    }

    public function test_track_event_validation_rejects_invalid_event_type(): void
    {
        $content = Content::factory()->create();

        $response = $this->postJson('/api/v1/track', $this->validPayload($content, [
            'event_type' => 'invalid_type',
        ]));

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['event_type']);
    }

    public function test_track_event_validation_rejects_invalid_source(): void
    {
        $content = Content::factory()->create();

        $response = $this->postJson('/api/v1/track', $this->validPayload($content, [
            'source' => 'unknown_source',
        ]));

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['source']);
    }

    public function test_track_event_returns_422_for_nonexistent_content(): void
    {
        $response = $this->postJson('/api/v1/track', [
            'content_id' => '01NONEXISTENTULID00000000',
            'event_type' => 'view',
            'source' => 'pixel',
            'session_id' => 'sess-abc',
        ]);

        $response->assertUnprocessable();
    }
}
