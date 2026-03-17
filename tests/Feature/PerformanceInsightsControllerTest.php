<?php

namespace Tests\Feature;

use App\Models\Performance\SpacePerformanceModel;
use App\Models\Role;
use App\Models\Space;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PerformanceInsightsControllerTest extends TestCase
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

    public function test_get_space_insights(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson("/api/v1/spaces/{$this->space->id}/performance/insights");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => ['space_id', 'has_model'],
            ]);
    }

    public function test_get_content_insights(): void
    {
        Sanctum::actingAs($this->user);

        $contentId = strtoupper(Str::ulid());

        $response = $this->getJson("/api/v1/spaces/{$this->space->id}/performance/insights/{$contentId}");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => ['space_id', 'has_model'],
            ]);
    }

    public function test_get_model_when_none_exists(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson("/api/v1/spaces/{$this->space->id}/performance/model");

        $response->assertOk()
            ->assertJsonPath('data', null)
            ->assertJsonPath('message', 'No performance model exists for this space yet.');
    }

    public function test_get_model_when_exists(): void
    {
        Sanctum::actingAs($this->user);

        SpacePerformanceModel::factory()->create([
            'space_id' => $this->space->id,
        ]);

        $response = $this->getJson("/api/v1/spaces/{$this->space->id}/performance/model");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => ['id', 'space_id', 'attribute_weights', 'model_confidence'],
                'recommendations',
            ]);
    }

    public function test_unauthenticated_insights_denied(): void
    {
        $response = $this->getJson("/api/v1/spaces/{$this->space->id}/performance/insights");

        $response->assertUnauthorized();
    }

    public function test_factory_creates_model(): void
    {
        $model = SpacePerformanceModel::factory()->create();

        $this->assertNotNull($model->id);
        $this->assertNotNull($model->space_id);
        $this->assertIsArray($model->attribute_weights);
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
