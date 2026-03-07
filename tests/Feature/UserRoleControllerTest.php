<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserRoleControllerTest extends TestCase
{
    use RefreshDatabase;

    // ─── assignRole ───────────────────────────────────────────────────────────

    public function test_can_assign_role_to_user(): void
    {
        $admin  = $this->userWithRole(['*']);
        $target = User::factory()->create();
        $role   = $this->makeRole(['content.read', 'content.create']);

        $response = $this->actingAs($admin)->postJson("/api/v1/users/{$target->id}/roles", [
            'role_id' => $role->id,
        ]);

        $response->assertCreated()
            ->assertJsonFragment(['message' => 'Role assigned', 'role_id' => $role->id]);

        $this->assertDatabaseHas('role_user', ['user_id' => $target->id, 'role_id' => $role->id]);
    }

    public function test_cannot_assign_role_without_permission(): void
    {
        $actor  = $this->userWithRole(['content.create']);  // no users.roles.assign
        $target = User::factory()->create();
        $role   = $this->makeRole(['content.read']);

        $response = $this->actingAs($actor)->postJson("/api/v1/users/{$target->id}/roles", [
            'role_id' => $role->id,
        ]);

        $response->assertForbidden();
    }

    public function test_duplicate_assignment_returns_409(): void
    {
        $admin  = $this->userWithRole(['*']);
        $target = User::factory()->create();
        $role   = $this->makeRole(['content.read']);

        // Assign once
        $target->roles()->attach($role->id, ['space_id' => null]);

        $response = $this->actingAs($admin)->postJson("/api/v1/users/{$target->id}/roles", [
            'role_id' => $role->id,
        ]);

        $response->assertStatus(409);
    }

    public function test_can_revoke_role_from_user(): void
    {
        $admin  = $this->userWithRole(['*']);
        $target = User::factory()->create();
        $role   = $this->makeRole(['content.read']);

        $target->roles()->attach($role->id, ['space_id' => null]);

        $response = $this->actingAs($admin)->deleteJson("/api/v1/users/{$target->id}/roles/{$role->id}");

        $response->assertOk()
            ->assertJsonFragment(['message' => 'Role revoked']);

        $this->assertDatabaseMissing('role_user', ['user_id' => $target->id, 'role_id' => $role->id]);
    }

    public function test_revoke_unassigned_role_returns_404(): void
    {
        $admin  = $this->userWithRole(['*']);
        $target = User::factory()->create();
        $role   = $this->makeRole(['content.read']);

        $response = $this->actingAs($admin)->deleteJson("/api/v1/users/{$target->id}/roles/{$role->id}");

        $response->assertNotFound();
    }

    public function test_cannot_escalate_beyond_own_permissions(): void
    {
        // Actor has users.roles.assign but only content.read
        $actor  = $this->userWithRole(['users.roles.assign', 'content.read']);
        $target = User::factory()->create();
        // Role has content.delete which actor does NOT have
        $role   = $this->makeRole(['content.read', 'content.delete']);

        $response = $this->actingAs($actor)->postJson("/api/v1/users/{$target->id}/roles", [
            'role_id' => $role->id,
        ]);

        $response->assertForbidden();
    }

    public function test_can_list_users_in_role(): void
    {
        $admin = $this->userWithRole(['*']);
        $role  = $this->makeRole(['content.read']);

        $u1 = User::factory()->create();
        $u2 = User::factory()->create();
        $u1->roles()->attach($role->id, ['space_id' => null]);
        $u2->roles()->attach($role->id, ['space_id' => null]);

        $response = $this->actingAs($admin)->getJson("/api/v1/roles/{$role->id}/users");

        $response->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonStructure(['data', 'meta' => ['total', 'per_page', 'current_page']]);
    }

    public function test_can_list_roles_for_user(): void
    {
        $admin  = $this->userWithRole(['*']);
        $target = User::factory()->create();
        $role   = $this->makeRole(['content.read']);
        $target->roles()->attach($role->id, ['space_id' => null]);

        $response = $this->actingAs($admin)->getJson("/api/v1/users/{$target->id}/roles");

        $response->assertOk()
            ->assertJsonPath('data.0.id', $role->id);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

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

    private function makeRole(array $permissions): Role
    {
        return Role::create([
            'name'        => 'Some Role',
            'slug'        => 'some-role-' . uniqid(),
            'permissions' => $permissions,
            'is_system'   => false,
        ]);
    }
}
