<?php

namespace Tests\Feature;

use App\Models\Performance\ContentPerformanceSnapshot;
use App\Models\Role;
use App\Models\Space;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PerformanceSnapshotControllerTest extends TestCase
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

    public function test_list_snapshots(): void
    {
        Sanctum::actingAs($this->user);

        ContentPerformanceSnapshot::factory()->count(3)->create([
            'space_id' => $this->space->id,
        ]);

        $response = $this->getJson("/api/v1/spaces/{$this->space->id}/performance/snapshots");

        $response->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonStructure([
                'data' => [['id', 'space_id', 'content_id', 'period_type', 'views', 'composite_score']],
                'meta',
                'links',
            ]);
    }

    public function test_filter_snapshots_by_content_id(): void
    {
        Sanctum::actingAs($this->user);

        $targetContentId = strtoupper(Str::ulid());
        ContentPerformanceSnapshot::factory()->count(2)->create([
            'space_id' => $this->space->id,
            'content_id' => $targetContentId,
        ]);
        ContentPerformanceSnapshot::factory()->create([
            'space_id' => $this->space->id,
        ]);

        $response = $this->getJson("/api/v1/spaces/{$this->space->id}/performance/snapshots?content_id={$targetContentId}");

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_filter_snapshots_by_period(): void
    {
        Sanctum::actingAs($this->user);

        ContentPerformanceSnapshot::factory()->create([
            'space_id' => $this->space->id,
            'period_type' => 'daily',
        ]);
        ContentPerformanceSnapshot::factory()->create([
            'space_id' => $this->space->id,
            'period_type' => 'weekly',
        ]);

        $response = $this->getJson("/api/v1/spaces/{$this->space->id}/performance/snapshots?period_type=daily");

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_show_snapshot(): void
    {
        Sanctum::actingAs($this->user);

        $snapshot = ContentPerformanceSnapshot::factory()->create([
            'space_id' => $this->space->id,
        ]);

        $response = $this->getJson("/api/v1/spaces/{$this->space->id}/performance/snapshots/{$snapshot->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $snapshot->id)
            ->assertJsonPath('data.space_id', $this->space->id);
    }

    public function test_show_snapshot_wrong_space_returns_404(): void
    {
        Sanctum::actingAs($this->user);

        $otherSpace = Space::factory()->create();
        $snapshot = ContentPerformanceSnapshot::factory()->create([
            'space_id' => $otherSpace->id,
        ]);

        $response = $this->getJson("/api/v1/spaces/{$this->space->id}/performance/snapshots/{$snapshot->id}");

        $response->assertNotFound();
    }

    public function test_unauthenticated_access_denied(): void
    {
        $response = $this->getJson("/api/v1/spaces/{$this->space->id}/performance/snapshots");

        $response->assertUnauthorized();
    }

    public function test_factory_creates_snapshot(): void
    {
        $snapshot = ContentPerformanceSnapshot::factory()->create();

        $this->assertNotNull($snapshot->id);
        $this->assertNotNull($snapshot->space_id);
        $this->assertNotNull($snapshot->content_id);
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
