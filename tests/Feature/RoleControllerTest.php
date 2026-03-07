<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleControllerTest extends TestCase
{
    use RefreshDatabase;

    // ─── index ────────────────────────────────────────────────────────────────

    public function test_index_returns_roles_for_authorised_user(): void
    {
        $user = $this->adminUser();

        Role::create(['name' => 'Alpha', 'slug' => 'alpha', 'permissions' => ['content.read'], 'is_system' => false]);
        Role::create(['name' => 'Beta',  'slug' => 'beta',  'permissions' => ['pipeline.run'], 'is_system' => false]);

        $response = $this->actingAs($user)->getJson('/api/v1/roles');

        // 3 total: "Test Role" (created by adminUser() helper) + Alpha + Beta
        $response->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_index_requires_roles_manage_permission(): void
    {
        $user = $this->userWithRole(['content.read']);

        $response = $this->actingAs($user)->getJson('/api/v1/roles');

        $response->assertForbidden()
            ->assertJsonFragment(['error' => 'Forbidden', 'required' => 'roles.manage']);
    }

    public function test_index_rejects_unauthenticated(): void
    {
        $response = $this->getJson('/api/v1/roles');

        $response->assertUnauthorized();
    }

    // ─── store ────────────────────────────────────────────────────────────────

    public function test_store_creates_role(): void
    {
        $user = $this->adminUser();

        $response = $this->actingAs($user)->postJson('/api/v1/roles', [
            'name'        => 'Custom Reviewer',
            'permissions' => ['content.read', 'pipeline.approve'],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Custom Reviewer')
            ->assertJsonPath('data.slug', 'custom-reviewer');

        $this->assertDatabaseHas('roles', ['slug' => 'custom-reviewer']);
    }

    public function test_store_requires_roles_manage(): void
    {
        $user = $this->userWithRole(['content.create']);

        $response = $this->actingAs($user)->postJson('/api/v1/roles', [
            'name' => 'Sneaky Role',
        ]);

        $response->assertForbidden();
    }

    public function test_store_validates_required_name(): void
    {
        $user = $this->adminUser();

        $response = $this->actingAs($user)->postJson('/api/v1/roles', [
            'permissions' => ['content.read'],
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    public function test_store_accepts_custom_slug(): void
    {
        $user = $this->adminUser();

        $response = $this->actingAs($user)->postJson('/api/v1/roles', [
            'name' => 'My Role',
            'slug' => 'my-custom-slug',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.slug', 'my-custom-slug');
    }

    public function test_store_marks_new_roles_as_non_system(): void
    {
        $user = $this->adminUser();

        $response = $this->actingAs($user)->postJson('/api/v1/roles', [
            'name' => 'Custom Role',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.is_system', false);
    }

    // ─── update ───────────────────────────────────────────────────────────────

    public function test_update_modifies_role_permissions(): void
    {
        $user = $this->adminUser();
        $role = Role::create([
            'name' => 'Old Role', 'slug' => 'old-role',
            'permissions' => ['content.read'],
            'is_system' => false,
        ]);

        $response = $this->actingAs($user)->putJson("/api/v1/roles/{$role->id}", [
            'permissions' => ['content.read', 'content.create', 'pipeline.run'],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.id', $role->id);

        $this->assertDatabaseHas('roles', ['id' => $role->id]);
        $updated = $role->fresh();
        $this->assertContains('pipeline.run', $updated->permissions);
    }

    public function test_update_requires_roles_manage(): void
    {
        $user = $this->userWithRole(['content.create']);
        $role = Role::create(['name' => 'R', 'slug' => 'r', 'permissions' => [], 'is_system' => false]);

        $response = $this->actingAs($user)->putJson("/api/v1/roles/{$role->id}", [
            'permissions' => ['*'],
        ]);

        $response->assertForbidden();
    }

    public function test_update_returns_404_for_missing_role(): void
    {
        $user = $this->adminUser();

        $response = $this->actingAs($user)->putJson('/api/v1/roles/nonexistent-id', [
            'name' => 'Ghost',
        ]);

        $response->assertNotFound();
    }

    // ─── destroy ──────────────────────────────────────────────────────────────

    public function test_destroy_deletes_non_system_role(): void
    {
        $user = $this->adminUser();
        $role = Role::create([
            'name' => 'Temp Role', 'slug' => 'temp-role',
            'permissions' => [],
            'is_system' => false,
        ]);

        $response = $this->actingAs($user)->deleteJson("/api/v1/roles/{$role->id}");

        $response->assertOk()
            ->assertJsonFragment(['message' => 'Role deleted.']);

        $this->assertDatabaseMissing('roles', ['id' => $role->id]);
    }

    public function test_destroy_prevents_deletion_of_system_roles(): void
    {
        $user = $this->adminUser();
        $role = Role::create([
            'name' => 'System Admin', 'slug' => 'system-admin',
            'permissions' => ['*'],
            'is_system' => true,  // ← system role
        ]);

        $response = $this->actingAs($user)->deleteJson("/api/v1/roles/{$role->id}");

        $response->assertStatus(422)
            ->assertJsonFragment(['error' => 'System roles cannot be deleted.']);

        $this->assertDatabaseHas('roles', ['id' => $role->id]);
    }

    public function test_destroy_requires_roles_manage(): void
    {
        $user = $this->userWithRole(['content.delete']);
        $role = Role::create(['name' => 'R', 'slug' => 'r2', 'permissions' => [], 'is_system' => false]);

        $response = $this->actingAs($user)->deleteJson("/api/v1/roles/{$role->id}");

        $response->assertForbidden();
    }

    public function test_destroy_returns_404_for_missing_role(): void
    {
        $user = $this->adminUser();

        $response = $this->actingAs($user)->deleteJson('/api/v1/roles/does-not-exist');

        $response->assertNotFound();
    }

    // ─── Permission gate integration ──────────────────────────────────────────

    public function test_editor_without_roles_manage_is_forbidden(): void
    {
        $user = $this->userWithRole(['content.*', 'pipeline.run', 'media.*']);

        $this->actingAs($user)->getJson('/api/v1/roles')->assertForbidden();
        $this->actingAs($user)->postJson('/api/v1/roles', ['name' => 'X'])->assertForbidden();
    }

    public function test_admin_wildcard_permits_all_role_operations(): void
    {
        $user = $this->userWithRole(['*']);
        $role = Role::create(['name' => 'X', 'slug' => 'x', 'permissions' => [], 'is_system' => false]);

        $this->actingAs($user)->getJson('/api/v1/roles')->assertOk();
        $this->actingAs($user)->postJson('/api/v1/roles', ['name' => 'New'])->assertCreated();
        $this->actingAs($user)->putJson("/api/v1/roles/{$role->id}", ['name' => 'X2'])->assertOk();
        $this->actingAs($user)->deleteJson("/api/v1/roles/{$role->id}")->assertOk();
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
