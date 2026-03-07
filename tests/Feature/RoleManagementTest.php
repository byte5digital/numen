<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    private function adminUser(): User
    {
        $user = User::factory()->create();
        $admin = Role::where('slug', 'admin')->first();
        $user->roles()->attach($admin->id, ['space_id' => null]);

        return $user;
    }

    private function viewerUser(): User
    {
        $user = User::factory()->create();
        $viewer = Role::where('slug', 'viewer')->first();
        $user->roles()->attach($viewer->id, ['space_id' => null]);

        return $user;
    }

    // ── GET /api/v1/roles ─────────────────────────────────────────────────

    public function test_list_roles_requires_authentication(): void
    {
        $this->getJson('/api/v1/roles')
            ->assertStatus(401);
    }

    public function test_authenticated_user_can_list_roles(): void
    {
        $user = $this->viewerUser();

        $this->actingAs($user)
            ->getJson('/api/v1/roles')
            ->assertOk()
            ->assertJsonStructure(['data' => [['id', 'name', 'slug', 'permissions', 'is_system']]]);
    }

    public function test_roles_list_includes_all_four_built_in_roles(): void
    {
        $user = $this->viewerUser();

        $response = $this->actingAs($user)
            ->getJson('/api/v1/roles')
            ->assertOk();

        $slugs = collect($response->json('data'))->pluck('slug')->toArray();

        $this->assertContains('admin', $slugs);
        $this->assertContains('editor', $slugs);
        $this->assertContains('author', $slugs);
        $this->assertContains('viewer', $slugs);
    }

    // ── POST /api/v1/roles ────────────────────────────────────────────────

    public function test_admin_can_create_custom_role(): void
    {
        $admin = $this->adminUser();

        $this->actingAs($admin)
            ->postJson('/api/v1/roles', [
                'name' => 'Marketing Editor',
                'description' => 'Can manage marketing content only',
                'permissions' => ['content.create', 'content.update', 'content.read'],
            ])
            ->assertCreated()
            ->assertJsonPath('data.slug', 'marketing-editor');

        $this->assertDatabaseHas('roles', ['slug' => 'marketing-editor', 'is_system' => false]);
    }

    public function test_non_admin_cannot_create_role(): void
    {
        $viewer = $this->viewerUser();

        $this->actingAs($viewer)
            ->postJson('/api/v1/roles', [
                'name' => 'Evil Role',
                'permissions' => ['*'],
            ])
            ->assertForbidden();
    }

    public function test_create_role_rejects_unknown_permissions(): void
    {
        $admin = $this->adminUser();

        $this->actingAs($admin)
            ->postJson('/api/v1/roles', [
                'name' => 'Bad Role',
                'permissions' => ['content.create', 'doesnt.exist'],
            ])
            ->assertUnprocessable();
    }

    // ── PUT /api/v1/roles/{role} ──────────────────────────────────────────

    public function test_admin_can_update_role_permissions(): void
    {
        $admin = $this->adminUser();
        $viewer = Role::where('slug', 'viewer')->first();

        $this->actingAs($admin)
            ->putJson("/api/v1/roles/{$viewer->id}", [
                'permissions' => ['content.read', 'media.read', 'content.create'],
            ])
            ->assertOk();

        $viewer->refresh();
        $this->assertContains('content.create', $viewer->permissions);
    }

    public function test_non_admin_cannot_update_role(): void
    {
        $viewer = $this->viewerUser();
        $viewerRole = Role::where('slug', 'viewer')->first();

        $this->actingAs($viewer)
            ->putJson("/api/v1/roles/{$viewerRole->id}", [
                'permissions' => ['*'],
            ])
            ->assertForbidden();
    }

    // ── DELETE /api/v1/roles/{role} ───────────────────────────────────────

    public function test_admin_can_delete_custom_role(): void
    {
        $admin = $this->adminUser();
        $custom = Role::create([
            'name' => 'Temp Role',
            'slug' => 'temp-role',
            'permissions' => ['content.read'],
            'is_system' => false,
        ]);

        $this->actingAs($admin)
            ->deleteJson("/api/v1/roles/{$custom->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('roles', ['id' => $custom->id]);
    }

    public function test_admin_cannot_delete_system_role(): void
    {
        $admin = $this->adminUser();
        $viewer = Role::where('slug', 'viewer')->first();

        $this->actingAs($admin)
            ->deleteJson("/api/v1/roles/{$viewer->id}")
            ->assertUnprocessable();

        $this->assertDatabaseHas('roles', ['id' => $viewer->id]);
    }

    // ── Role assignment ───────────────────────────────────────────────────

    public function test_admin_can_assign_role_to_user(): void
    {
        $admin = $this->adminUser();
        $target = User::factory()->create();
        $editor = Role::where('slug', 'editor')->first();

        $this->actingAs($admin)
            ->postJson("/api/v1/users/{$target->id}/roles", [
                'role_id' => $editor->id,
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertTrue(
            $target->roles()->where('roles.id', $editor->id)->exists()
        );
    }

    public function test_admin_can_revoke_role_from_user(): void
    {
        $admin = $this->adminUser();
        $target = User::factory()->create();
        $editor = Role::where('slug', 'editor')->first();

        $target->roles()->attach($editor->id, ['space_id' => null]);

        $this->actingAs($admin)
            ->deleteJson("/api/v1/users/{$target->id}/roles/{$editor->id}")
            ->assertOk();

        $this->assertFalse(
            $target->roles()->where('roles.id', $editor->id)->exists()
        );
    }

    public function test_non_admin_cannot_assign_roles(): void
    {
        $viewer = $this->viewerUser();
        $target = User::factory()->create();
        $editor = Role::where('slug', 'editor')->first();

        $this->actingAs($viewer)
            ->postJson("/api/v1/users/{$target->id}/roles", [
                'role_id' => $editor->id,
            ])
            ->assertForbidden();
    }

    // ── GET /api/v1/permissions ───────────────────────────────────────────

    public function test_authenticated_user_can_list_permissions(): void
    {
        $user = $this->viewerUser();

        $this->actingAs($user)
            ->getJson('/api/v1/permissions')
            ->assertOk()
            ->assertJsonStructure(['data' => ['content', 'pipeline', 'media', 'users', 'settings', 'spaces', 'ai']]);
    }
}
