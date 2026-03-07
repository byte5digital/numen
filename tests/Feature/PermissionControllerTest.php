<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PermissionControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_list_permissions(): void
    {
        $user = $this->adminUser();

        $response = $this->actingAs($user)->getJson('/api/v1/permissions');

        $response->assertOk()
            ->assertJsonStructure(['data']);
    }

    public function test_author_cannot_list_permissions(): void
    {
        // Author role has content permissions but NOT roles.manage
        $user = $this->userWithRole(['content.create', 'content.update', 'content.read']);

        $response = $this->actingAs($user)->getJson('/api/v1/permissions');

        $response->assertForbidden()
            ->assertJsonFragment(['error' => 'Forbidden', 'required' => 'roles.manage']);
    }

    public function test_permissions_response_includes_all_categories(): void
    {
        $user = $this->adminUser();

        $response = $this->actingAs($user)->getJson('/api/v1/permissions');

        $response->assertOk();

        $data = $response->json('data');

        $this->assertArrayHasKey('content',  $data);
        $this->assertArrayHasKey('users',    $data);
        $this->assertArrayHasKey('roles',    $data);
        $this->assertArrayHasKey('spaces',   $data);
        $this->assertArrayHasKey('audit',    $data);
        $this->assertArrayHasKey('settings', $data);
        $this->assertArrayHasKey('ai',       $data);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function adminUser(): User
    {
        return $this->userWithRole(['*']);
    }

    private function userWithRole(array $permissions): User
    {
        $user = User::factory()->create();

        $role = Role::create([
            'name'        => 'Test Role',
            'slug'        => 'test-role-' . uniqid(),
            'permissions' => $permissions,
            'is_system'   => false,
        ]);

        $user->roles()->attach($role->id, ['space_id' => null]);
        $user->load('roles');

        return $user;
    }
}
