<?php

namespace Tests\Feature;

use App\Models\Performance\ContentAbTest;
use App\Models\Performance\ContentAbVariant;
use App\Models\Performance\ContentPerformanceSnapshot;
use App\Models\Role;
use App\Models\Space;
use App\Models\User;
use App\Services\Performance\PerformanceAggregatorService;
use App\Services\Performance\PerformanceIngestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PerformanceFeedbackIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private Space $space;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->space = Space::factory()->create();
        $this->user = $this->adminUser();
    }

    public function test_full_flow_ingest_aggregate_score(): void
    {
        $contentId = strtoupper(Str::ulid());
        $ingestService = app(PerformanceIngestService::class);

        foreach (range(1, 5) as $i) {
            $ingestService->ingestEvent([
                'space_id' => $this->space->id,
                'content_id' => $contentId,
                'event_type' => 'page_view',
                'source' => 'sdk',
                'session_id' => 'session-'.$i,
                'visitor_id' => 'visitor-'.$i,
                'value' => 1,
                'occurred_at' => now()->toISOString(),
            ]);
        }

        $ingestService->ingestEvent([
            'space_id' => $this->space->id,
            'content_id' => $contentId,
            'event_type' => 'conversion',
            'source' => 'sdk',
            'session_id' => 'session-conv',
            'visitor_id' => 'visitor-1',
            'value' => 1,
            'occurred_at' => now()->toISOString(),
        ]);

        $this->assertDatabaseCount('content_performance_events', 6);

        $aggregator = app(PerformanceAggregatorService::class);
        $snapshot = $aggregator->aggregateDaily($contentId, now());

        $this->assertInstanceOf(ContentPerformanceSnapshot::class, $snapshot);
        $this->assertEquals($contentId, $snapshot->content_id);
        $this->assertEquals('daily', $snapshot->period_type);
        $this->assertEquals(5, $snapshot->views);
        $this->assertEquals(1, $snapshot->conversions);
        $this->assertGreaterThan(0, $snapshot->composite_score);
    }

    public function test_ingest_deduplication(): void
    {
        $contentId = strtoupper(Str::ulid());
        $ingestService = app(PerformanceIngestService::class);

        $data = [
            'space_id' => $this->space->id,
            'content_id' => $contentId,
            'event_type' => 'page_view',
            'source' => 'sdk',
            'session_id' => 'session-dup',
            'visitor_id' => 'visitor-dup',
            'value' => 1,
            'occurred_at' => now()->toISOString(),
        ];

        $ingestService->ingestEvent($data);
        $ingestService->ingestEvent($data);

        $this->assertDatabaseCount('content_performance_events', 1);
    }

    public function test_overview_returns_empty_for_new_space(): void
    {
        Sanctum::actingAs($this->user);
        $response = $this->getJson("/api/v1/spaces/{$this->space->id}/performance/overview");
        $response->assertOk()
            ->assertJsonPath('data.space_id', $this->space->id)
            ->assertJsonPath('data.top_performers', [])
            ->assertJsonPath('data.trends', [])
            ->assertJsonPath('data.recent_events_count', 0)
            ->assertJsonPath('data.model', null);
    }

    public function test_snapshots_returns_empty_for_new_space(): void
    {
        Sanctum::actingAs($this->user);
        $response = $this->getJson("/api/v1/spaces/{$this->space->id}/performance/snapshots");
        $response->assertOk()->assertJsonPath('data', []);
    }

    public function test_insights_for_new_space(): void
    {
        Sanctum::actingAs($this->user);
        $response = $this->getJson("/api/v1/spaces/{$this->space->id}/performance/insights");
        $response->assertOk();
    }

    public function test_correlations_returns_empty_for_new_space(): void
    {
        Sanctum::actingAs($this->user);
        $response = $this->getJson("/api/v1/spaces/{$this->space->id}/performance/correlations");
        $response->assertOk()->assertJsonPath('data', []);
    }

    public function test_model_returns_null_for_new_space(): void
    {
        Sanctum::actingAs($this->user);
        $response = $this->getJson("/api/v1/spaces/{$this->space->id}/performance/model");
        $response->assertOk()->assertJsonPath('data', null);
    }

    public function test_aggregate_rejects_invalid_period(): void
    {
        Sanctum::actingAs($this->user);
        $response = $this->postJson("/api/v1/spaces/{$this->space->id}/performance/aggregate", [
            'content_id' => strtoupper(Str::ulid()),
            'period' => 'hourly',
        ]);
        $response->assertUnprocessable();
    }

    public function test_aggregate_rejects_missing_content_id(): void
    {
        Sanctum::actingAs($this->user);
        $response = $this->postJson("/api/v1/spaces/{$this->space->id}/performance/aggregate", [
            'period' => 'daily',
        ]);
        $response->assertUnprocessable();
    }

    public function test_bulk_events_rejects_invalid_event_type(): void
    {
        $response = $this->postJson("/api/v1/spaces/{$this->space->id}/tracking/events", [
            'events' => [
                ['content_id' => strtoupper(Str::ulid()), 'event_type' => 'invalid_type', 'session_id' => 'sess-1'],
            ],
        ]);
        $response->assertUnprocessable();
    }

    public function test_bulk_events_rejects_empty_array(): void
    {
        $response = $this->postJson("/api/v1/spaces/{$this->space->id}/tracking/events", [
            'events' => [],
        ]);
        $response->assertUnprocessable();
    }

    public function test_end_already_ended_test(): void
    {
        Sanctum::actingAs($this->user);
        $test = ContentAbTest::factory()->create([
            'space_id' => $this->space->id,
            'status' => 'completed',
            'ended_at' => now()->subDay(),
            'conclusion' => ['summary' => 'Already ended'],
        ]);
        ContentAbVariant::factory()->control()->create(['test_id' => $test->id]);
        ContentAbVariant::factory()->create(['test_id' => $test->id, 'is_control' => false]);
        $response = $this->postJson("/api/v1/spaces/{$this->space->id}/ab-tests/{$test->id}/end");
        $response->assertOk();
    }

    public function test_assign_to_ended_test(): void
    {
        Sanctum::actingAs($this->user);
        $test = ContentAbTest::factory()->create([
            'space_id' => $this->space->id,
            'status' => 'completed',
            'ended_at' => now()->subDay(),
        ]);
        ContentAbVariant::factory()->control()->create(['test_id' => $test->id]);
        ContentAbVariant::factory()->create(['test_id' => $test->id, 'is_control' => false]);
        $response = $this->postJson("/api/v1/spaces/{$this->space->id}/ab-tests/{$test->id}/assign", [
            'visitor_id' => 'visitor-xyz',
        ]);
        $response->assertOk()->assertJsonStructure(['data' => ['id', 'label']]);
    }

    public function test_create_ab_test_requires_at_least_two_variants(): void
    {
        Sanctum::actingAs($this->user);
        $response = $this->postJson("/api/v1/spaces/{$this->space->id}/ab-tests", [
            'name' => 'Bad Test',
            'variants' => [
                ['content_id' => strtoupper(Str::ulid()), 'label' => 'Only One'],
            ],
        ]);
        $response->assertUnprocessable();
    }

    public function test_create_ab_test_requires_name(): void
    {
        Sanctum::actingAs($this->user);
        $response = $this->postJson("/api/v1/spaces/{$this->space->id}/ab-tests", [
            'variants' => [
                ['content_id' => strtoupper(Str::ulid()), 'label' => 'A', 'is_control' => true],
                ['content_id' => strtoupper(Str::ulid()), 'label' => 'B', 'is_control' => false],
            ],
        ]);
        $response->assertUnprocessable();
    }

    public function test_convert_requires_variant_id_and_visitor_id(): void
    {
        Sanctum::actingAs($this->user);
        $test = ContentAbTest::factory()->create([
            'space_id' => $this->space->id,
            'status' => 'running',
        ]);
        $response = $this->postJson("/api/v1/spaces/{$this->space->id}/ab-tests/{$test->id}/convert", []);
        $response->assertUnprocessable();
    }

    public function test_pixel_returns_gif_without_content_id(): void
    {
        $response = $this->get("/api/v1/spaces/{$this->space->id}/tracking/pixel.gif");
        $response->assertOk()->assertHeader('Content-Type', 'image/gif');
        $this->assertDatabaseCount('content_performance_events', 0);
    }

    public function test_pixel_returns_gif_with_content_id(): void
    {
        $contentId = strtoupper(Str::ulid());
        $response = $this->get("/api/v1/spaces/{$this->space->id}/tracking/pixel.gif?cid={$contentId}&sid=test-session");
        // Pixel always returns GIF regardless of ingest success
        $response->assertOk()->assertHeader('Content-Type', 'image/gif');
    }

    public function test_refresh_suggestions_returns_empty_for_new_space(): void
    {
        Sanctum::actingAs($this->user);
        $response = $this->getJson("/api/v1/spaces/{$this->space->id}/refresh-suggestions");
        $response->assertOk()->assertJsonPath('data', []);
    }

    private function adminUser(): User
    {
        $user = User::factory()->create();
        $role = Role::create([
            'name' => 'Admin',
            'slug' => 'admin-'.uniqid(),
            'permissions' => ['*'],
            'is_system' => false,
        ]);
        $user->roles()->attach($role->id, ['space_id' => null]);
        $user->load('roles');

        return $user;
    }
}
