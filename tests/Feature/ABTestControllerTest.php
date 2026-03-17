<?php

namespace Tests\Feature;

use App\Models\Performance\ContentAbTest;
use App\Models\Performance\ContentAbVariant;
use App\Models\Role;
use App\Models\Space;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ABTestControllerTest extends TestCase
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

    public function test_create_ab_test(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson("/api/v1/spaces/{$this->space->id}/ab-tests", [
            'name' => 'Headline Test',
            'hypothesis' => 'Shorter headlines convert better',
            'metric' => 'conversion_rate',
            'variants' => [
                ['content_id' => strtoupper(Str::ulid()), 'label' => 'Control', 'is_control' => true],
                ['content_id' => strtoupper(Str::ulid()), 'label' => 'Short Headline', 'is_control' => false],
            ],
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Headline Test')
            ->assertJsonPath('data.status', 'draft');
    }

    public function test_list_ab_tests(): void
    {
        Sanctum::actingAs($this->user);

        ContentAbTest::factory()->count(3)->create(['space_id' => $this->space->id]);

        $response = $this->getJson("/api/v1/spaces/{$this->space->id}/ab-tests");

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_show_ab_test_with_results(): void
    {
        Sanctum::actingAs($this->user);

        $test = ContentAbTest::factory()->create([
            'space_id' => $this->space->id,
            'status' => 'running',
        ]);
        ContentAbVariant::factory()->control()->create(['test_id' => $test->id, 'view_count' => 100]);
        ContentAbVariant::factory()->create(['test_id' => $test->id, 'is_control' => false, 'view_count' => 100]);

        $response = $this->getJson("/api/v1/spaces/{$this->space->id}/ab-tests/{$test->id}");

        $response->assertOk()
            ->assertJsonStructure(['data' => ['id', 'name', 'results']]);
    }

    public function test_assign_variant(): void
    {
        Sanctum::actingAs($this->user);

        $test = ContentAbTest::factory()->create([
            'space_id' => $this->space->id,
            'status' => 'running',
        ]);
        ContentAbVariant::factory()->control()->create(['test_id' => $test->id]);
        ContentAbVariant::factory()->create(['test_id' => $test->id, 'is_control' => false]);

        $response = $this->postJson("/api/v1/spaces/{$this->space->id}/ab-tests/{$test->id}/assign", [
            'visitor_id' => 'visitor-abc',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['data' => ['id', 'label', 'is_control']]);
    }

    public function test_record_conversion(): void
    {
        Sanctum::actingAs($this->user);

        $test = ContentAbTest::factory()->create([
            'space_id' => $this->space->id,
            'status' => 'running',
        ]);
        $variant = ContentAbVariant::factory()->create([
            'test_id' => $test->id,
            'view_count' => 50,
            'conversion_rate' => 0.04,
        ]);

        $response = $this->postJson("/api/v1/spaces/{$this->space->id}/ab-tests/{$test->id}/convert", [
            'variant_id' => $variant->id,
            'visitor_id' => 'visitor-abc',
        ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Conversion recorded.');
    }

    public function test_end_test(): void
    {
        Sanctum::actingAs($this->user);

        $test = ContentAbTest::factory()->create([
            'space_id' => $this->space->id,
            'status' => 'running',
            'started_at' => now()->subDay(),
        ]);
        ContentAbVariant::factory()->control()->create([
            'test_id' => $test->id,
            'view_count' => 200,
            'conversion_rate' => 0.05,
        ]);
        ContentAbVariant::factory()->create([
            'test_id' => $test->id,
            'is_control' => false,
            'view_count' => 200,
            'conversion_rate' => 0.08,
        ]);

        $response = $this->postJson("/api/v1/spaces/{$this->space->id}/ab-tests/{$test->id}/end");

        $response->assertOk()
            ->assertJsonStructure(['data' => ['test_id', 'conclusion']]);

        $this->assertEquals('completed', $test->fresh()->status);
    }

    public function test_unauthenticated_request_rejected(): void
    {
        $response = $this->getJson("/api/v1/spaces/{$this->space->id}/ab-tests");

        $response->assertUnauthorized();
    }

    public function test_show_wrong_space_returns_404(): void
    {
        Sanctum::actingAs($this->user);

        $otherSpace = Space::factory()->create();
        $test = ContentAbTest::factory()->create(['space_id' => $otherSpace->id]);

        $response = $this->getJson("/api/v1/spaces/{$this->space->id}/ab-tests/{$test->id}");

        $response->assertNotFound();
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
