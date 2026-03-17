<?php

namespace Tests\Feature;

use App\Models\Performance\ContentPerformanceEvent;
use App\Models\Performance\ContentPerformanceSnapshot;
use App\Models\Performance\SpacePerformanceModel;
use App\Models\Role;
use App\Models\Space;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PerformanceOverviewControllerTest extends TestCase
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

    public function test_overview_returns_structure(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson("/api/v1/spaces/{$this->space->id}/performance/overview");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'space_id',
                    'top_performers',
                    'trends',
                    'recent_events_count',
                    'model',
                ],
            ]);
    }

    public function test_overview_includes_top_performers(): void
    {
        Sanctum::actingAs($this->user);

        ContentPerformanceSnapshot::factory()->count(3)->create([
            'space_id' => $this->space->id,
            'period_type' => 'daily',
            'period_start' => Carbon::now()->subDays(5),
        ]);

        $response = $this->getJson("/api/v1/spaces/{$this->space->id}/performance/overview");

        $response->assertOk();
        $this->assertNotEmpty($response->json('data.top_performers'));
    }

    public function test_overview_includes_recent_events_count(): void
    {
        Sanctum::actingAs($this->user);

        ContentPerformanceEvent::factory()->count(5)->create([
            'space_id' => $this->space->id,
            'occurred_at' => Carbon::now()->subHours(2),
        ]);

        ContentPerformanceEvent::factory()->count(3)->create([
            'space_id' => $this->space->id,
            'occurred_at' => Carbon::now()->subDays(3),
        ]);

        $response = $this->getJson("/api/v1/spaces/{$this->space->id}/performance/overview");

        $response->assertOk()
            ->assertJsonPath('data.recent_events_count', 5);
    }

    public function test_overview_includes_model_when_present(): void
    {
        Sanctum::actingAs($this->user);

        SpacePerformanceModel::factory()->create([
            'space_id' => $this->space->id,
        ]);

        $response = $this->getJson("/api/v1/spaces/{$this->space->id}/performance/overview");

        $response->assertOk();
        $this->assertNotNull($response->json('data.model'));
        $this->assertArrayHasKey('model_confidence', $response->json('data.model'));
    }

    public function test_overview_model_null_when_absent(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson("/api/v1/spaces/{$this->space->id}/performance/overview");

        $response->assertOk()
            ->assertJsonPath('data.model', null);
    }

    public function test_unauthenticated_overview_denied(): void
    {
        $response = $this->getJson("/api/v1/spaces/{$this->space->id}/performance/overview");

        $response->assertUnauthorized();
    }

    public function test_factory_creates_event(): void
    {
        $event = ContentPerformanceEvent::factory()->create();

        $this->assertNotNull($event->id);
        $this->assertNotNull($event->event_type);
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
