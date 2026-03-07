<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Space;
use App\Models\User;
use App\Services\Authorization\AuthorizationService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PermissionTest extends TestCase
{
    use RefreshDatabase;

    private AuthorizationService $authz;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->authz = app(AuthorizationService::class);
    }

    // ── Basic permission checks ───────────────────────────────────────────

    public function test_user_with_no_roles_has_no_permissions(): void
    {
        $user = User::factory()->create();

        $this->assertFalse($this->authz->can($user, 'content.read'));
        $this->assertFalse($this->authz->can($user, 'content.create'));
    }

    public function test_admin_role_grants_all_permissions_via_wildcard(): void
    {
        $user = User::factory()->create();
        $admin = Role::where('slug', 'admin')->first();

        $user->roles()->attach($admin->id, ['space_id' => null]);
        $this->authz->clearCache();

        $this->assertTrue($this->authz->can($user, 'content.read'));
        $this->assertTrue($this->authz->can($user, 'content.create'));
        $this->assertTrue($this->authz->can($user, 'ai.model.opus'));
        $this->assertTrue($this->authz->can($user, 'settings.system'));
        $this->assertTrue($this->authz->can($user, 'some.future.permission'));
    }

    public function test_editor_role_has_expected_permissions(): void
    {
        $user = User::factory()->create();
        $editor = Role::where('slug', 'editor')->first();

        $user->roles()->attach($editor->id, ['space_id' => null]);
        $this->authz->clearCache();

        $this->assertTrue($this->authz->can($user, 'content.read'));
        $this->assertTrue($this->authz->can($user, 'content.publish'));
        $this->assertTrue($this->authz->can($user, 'ai.generate'));
        $this->assertTrue($this->authz->can($user, 'ai.model.sonnet'));
        $this->assertFalse($this->authz->can($user, 'ai.model.opus'));
        $this->assertFalse($this->authz->can($user, 'settings.system'));
        $this->assertFalse($this->authz->can($user, 'users.roles.manage'));
    }

    public function test_author_role_has_expected_permissions(): void
    {
        $user = User::factory()->create();
        $author = Role::where('slug', 'author')->first();

        $user->roles()->attach($author->id, ['space_id' => null]);
        $this->authz->clearCache();

        $this->assertTrue($this->authz->can($user, 'content.create'));
        $this->assertTrue($this->authz->can($user, 'content.read'));
        $this->assertTrue($this->authz->can($user, 'ai.generate'));
        $this->assertTrue($this->authz->can($user, 'ai.model.haiku'));
        $this->assertFalse($this->authz->can($user, 'content.publish'));
        $this->assertFalse($this->authz->can($user, 'ai.model.sonnet'));
        $this->assertFalse($this->authz->can($user, 'pipeline.approve'));
    }

    public function test_viewer_role_has_readonly_permissions(): void
    {
        $user = User::factory()->create();
        $viewer = Role::where('slug', 'viewer')->first();

        $user->roles()->attach($viewer->id, ['space_id' => null]);
        $this->authz->clearCache();

        $this->assertTrue($this->authz->can($user, 'content.read'));
        $this->assertFalse($this->authz->can($user, 'content.create'));
        $this->assertFalse($this->authz->can($user, 'ai.generate'));
        $this->assertFalse($this->authz->can($user, 'media.upload'));
    }

    // ── Domain wildcard expansion ─────────────────────────────────────────

    public function test_domain_wildcard_grants_all_permissions_in_domain(): void
    {
        $user = User::factory()->create();
        $role = Role::create([
            'name' => 'Content Manager',
            'slug' => 'content-manager',
            'permissions' => ['content.*'],
            'is_system' => false,
        ]);

        $user->roles()->attach($role->id, ['space_id' => null]);
        $this->authz->clearCache();

        $this->assertTrue($this->authz->can($user, 'content.create'));
        $this->assertTrue($this->authz->can($user, 'content.publish'));
        $this->assertTrue($this->authz->can($user, 'content.delete'));
        $this->assertFalse($this->authz->can($user, 'media.upload'));
        $this->assertFalse($this->authz->can($user, 'ai.generate'));
    }

    // ── Space-scoped role assignment ──────────────────────────────────────

    public function test_user_has_permission_in_assigned_space_only(): void
    {
        $user = User::factory()->create();
        $spaceA = Space::factory()->create();
        $spaceB = Space::factory()->create();
        $editor = Role::where('slug', 'editor')->first();

        // Assign editor role only in spaceA
        $user->roles()->attach($editor->id, ['space_id' => $spaceA->id]);
        $this->authz->clearCache();

        $this->assertTrue($this->authz->can($user, 'content.publish', $spaceA));
        $this->assertFalse($this->authz->can($user, 'content.publish', $spaceB));
        $this->assertFalse($this->authz->can($user, 'content.publish')); // global check
    }

    public function test_global_role_grants_permission_in_all_spaces(): void
    {
        $user = User::factory()->create();
        $space = Space::factory()->create();
        $editor = Role::where('slug', 'editor')->first();

        // Assign globally (space_id = null)
        $user->roles()->attach($editor->id, ['space_id' => null]);
        $this->authz->clearCache();

        $this->assertTrue($this->authz->can($user, 'content.publish'));           // global
        $this->assertTrue($this->authz->can($user, 'content.publish', $space));  // space context
    }

    public function test_user_with_different_roles_in_different_spaces(): void
    {
        $user = User::factory()->create();
        $spaceA = Space::factory()->create();
        $spaceB = Space::factory()->create();
        $editor = Role::where('slug', 'editor')->first();
        $viewer = Role::where('slug', 'viewer')->first();

        $user->roles()->attach($editor->id, ['space_id' => $spaceA->id]);
        $user->roles()->attach($viewer->id, ['space_id' => $spaceB->id]);
        $this->authz->clearCache();

        $this->assertTrue($this->authz->can($user, 'content.publish', $spaceA));
        $this->assertFalse($this->authz->can($user, 'content.publish', $spaceB));
        $this->assertTrue($this->authz->can($user, 'content.read', $spaceB));
    }

    // ── Per-request cache ─────────────────────────────────────────────────

    public function test_permission_cache_is_cleared_between_calls(): void
    {
        $user = User::factory()->create();
        $viewer = Role::where('slug', 'viewer')->first();
        $editor = Role::where('slug', 'editor')->first();

        $user->roles()->attach($viewer->id, ['space_id' => null]);
        $this->authz->clearCache();

        $this->assertFalse($this->authz->can($user, 'content.create'));

        // Upgrade to editor
        $user->roles()->detach($viewer->id);
        $user->roles()->attach($editor->id, ['space_id' => null]);

        // Without clearing cache, stale permissions are used
        $this->assertFalse($this->authz->can($user, 'content.create')); // cached

        // After clearing, new permissions apply
        $this->authz->clearCache();
        $this->assertTrue($this->authz->can($user, 'content.create'));
    }

    // ── Role model hasPermission ──────────────────────────────────────────

    public function test_role_has_permission_with_wildcard(): void
    {
        $role = new Role(['permissions' => ['*']]);

        $this->assertTrue($role->hasPermission('content.create'));
        $this->assertTrue($role->hasPermission('ai.model.opus'));
        $this->assertTrue($role->hasPermission('anything.at.all'));
    }

    public function test_role_has_permission_with_domain_wildcard(): void
    {
        $role = new Role(['permissions' => ['content.*', 'media.read']]);

        $this->assertTrue($role->hasPermission('content.create'));
        $this->assertTrue($role->hasPermission('content.publish'));
        $this->assertTrue($role->hasPermission('media.read'));
        $this->assertFalse($role->hasPermission('media.upload'));
        $this->assertFalse($role->hasPermission('ai.generate'));
    }

    // ── Gate integration ──────────────────────────────────────────────────

    public function test_laravel_gate_delegates_to_authorization_service(): void
    {
        $user = User::factory()->create();
        $editor = Role::where('slug', 'editor')->first();

        $user->roles()->attach($editor->id, ['space_id' => null]);
        $this->authz->clearCache();

        $this->actingAs($user);

        $this->assertTrue($user->can('content.read'));
        $this->assertTrue($user->can('content.publish'));
        $this->assertFalse($user->can('ai.model.opus'));
    }

    public function test_is_admin_returns_true_for_admin_role_user(): void
    {
        $user = User::factory()->create();
        $admin = Role::where('slug', 'admin')->first();

        $user->roles()->attach($admin->id, ['space_id' => null]);

        $this->assertTrue($user->isAdmin());
    }

    public function test_is_admin_returns_false_for_non_admin_user(): void
    {
        $user = User::factory()->create();
        $viewer = Role::where('slug', 'viewer')->first();

        $user->roles()->attach($viewer->id, ['space_id' => null]);

        $this->assertFalse($user->isAdmin());
    }
}
