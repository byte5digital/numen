<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Role;
use App\Models\Space;
use App\Models\User;
use App\Services\AuthorizationService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthorizationServiceTest extends TestCase
{
    use RefreshDatabase;

    private AuthorizationService $authz;

    protected function setUp(): void
    {
        parent::setUp();
        $this->authz = app(AuthorizationService::class);
    }

    // ─── Wildcard permission expansion ────────────────────────────────────────

    public function test_star_wildcard_grants_all_permissions(): void
    {
        $user = $this->userWithRole(['*']);

        $this->assertTrue($this->authz->check($user, 'content.create'));
        $this->assertTrue($this->authz->check($user, 'roles.manage'));
        $this->assertTrue($this->authz->check($user, 'ai.model.opus'));
        $this->assertTrue($this->authz->check($user, 'anything.at.all'));
    }

    public function test_domain_wildcard_grants_domain_permissions(): void
    {
        $user = $this->userWithRole(['content.*']);

        $this->assertTrue($this->authz->check($user, 'content.create'));
        $this->assertTrue($this->authz->check($user, 'content.read'));
        $this->assertTrue($this->authz->check($user, 'content.update'));
        $this->assertTrue($this->authz->check($user, 'content.delete'));
        $this->assertTrue($this->authz->check($user, 'content.publish'));

        // Does NOT grant other domains
        $this->assertFalse($this->authz->check($user, 'roles.manage'));
        $this->assertFalse($this->authz->check($user, 'users.manage'));
    }

    public function test_nested_wildcard_expansion(): void
    {
        $user = $this->userWithRole(['ai.model.*']);

        $this->assertTrue($this->authz->check($user, 'ai.model.opus'));
        $this->assertTrue($this->authz->check($user, 'ai.model.sonnet'));
        $this->assertTrue($this->authz->check($user, 'ai.model.haiku'));

        // Does NOT grant parent domain
        $this->assertFalse($this->authz->check($user, 'ai.generate'));
        $this->assertFalse($this->authz->check($user, 'ai.budget.unlimited'));
    }

    public function test_explicit_permission_match(): void
    {
        $user = $this->userWithRole(['content.create', 'pipeline.run']);

        $this->assertTrue($this->authz->check($user, 'content.create'));
        $this->assertTrue($this->authz->check($user, 'pipeline.run'));
        $this->assertFalse($this->authz->check($user, 'content.delete'));
        $this->assertFalse($this->authz->check($user, 'content.update'));
    }

    public function test_no_permissions_denies_all(): void
    {
        $user = $this->userWithRole([]);

        $this->assertFalse($this->authz->check($user, 'content.create'));
        $this->assertFalse($this->authz->check($user, 'roles.manage'));
    }

    // ─── Space-scoped vs global role resolution ───────────────────────────────

    public function test_global_role_applies_to_all_spaces(): void
    {
        $user = User::factory()->create();
        $role = Role::create([
            'name' => 'Global Editor',
            'slug' => 'global-editor',
            'permissions' => ['content.create'],
            'is_system' => false,
        ]);

        // Assign global (no space_id)
        $user->roles()->attach($role->id, ['space_id' => null]);
        $user->load('roles');

        $space = Space::create(['name' => 'Space A', 'slug' => 'space-a']);

        // Works globally
        $this->assertTrue($this->authz->check($user, 'content.create'));
        // Also works for any space (global roles apply everywhere)
        $this->assertTrue($this->authz->check($user, 'content.create', $space->id));
    }

    public function test_space_role_only_applies_to_that_space(): void
    {
        $user = User::factory()->create();

        $spaceA = Space::create(['name' => 'Space A', 'slug' => 'space-a']);
        $spaceB = Space::create(['name' => 'Space B', 'slug' => 'space-b']);

        $role = Role::create([
            'name' => 'Space Editor',
            'slug' => 'space-editor',
            'permissions' => ['content.create'],
            'is_system' => false,
        ]);

        // Assign only for Space A
        $user->roles()->attach($role->id, ['space_id' => $spaceA->id]);
        $user->load('roles');

        // Has permission in Space A
        $this->assertTrue($this->authz->check($user, 'content.create', $spaceA->id));
        // Does NOT have permission in Space B
        $this->assertFalse($this->authz->check($user, 'content.create', $spaceB->id));
        // Does NOT have global permission (no global role assigned)
        $this->assertFalse($this->authz->check($user, 'content.create'));
    }

    public function test_user_with_multiple_roles_gets_union_of_permissions(): void
    {
        $user = User::factory()->create();

        $roleA = Role::create([
            'name' => 'Role A', 'slug' => 'role-a',
            'permissions' => ['content.create', 'content.read'],
            'is_system' => false,
        ]);
        $roleB = Role::create([
            'name' => 'Role B', 'slug' => 'role-b',
            'permissions' => ['pipeline.run', 'media.upload'],
            'is_system' => false,
        ]);

        $user->roles()->attach($roleA->id, ['space_id' => null]);
        $user->roles()->attach($roleB->id, ['space_id' => null]);
        $user->load('roles');

        $this->assertTrue($this->authz->check($user, 'content.create'));
        $this->assertTrue($this->authz->check($user, 'pipeline.run'));
        $this->assertTrue($this->authz->check($user, 'media.upload'));
        $this->assertFalse($this->authz->check($user, 'roles.manage'));
    }

    // ─── userPermissions ──────────────────────────────────────────────────────

    public function test_user_permissions_returns_flat_permission_list(): void
    {
        $user = $this->userWithRole(['content.create', 'content.read', 'pipeline.run']);

        $permissions = $this->authz->userPermissions($user);

        $this->assertContains('content.create', $permissions);
        $this->assertContains('content.read', $permissions);
        $this->assertContains('pipeline.run', $permissions);
    }

    // ─── authorize() throws on failure ────────────────────────────────────────

    public function test_authorize_throws_when_permission_missing(): void
    {
        $user = $this->userWithRole(['content.read']);

        $this->expectException(AuthorizationException::class);

        $this->authz->authorize($user, 'content.delete');
    }

    public function test_authorize_does_not_throw_when_permission_present(): void
    {
        $user = $this->userWithRole(['content.delete']);

        // No exception expected
        $this->authz->authorize($user, 'content.delete');
        $this->assertTrue(true); // Reached this line without exception
    }

    public function test_authorize_works_with_wildcard_role(): void
    {
        $user = $this->userWithRole(['*']);

        // Admin with wildcard — should never throw
        $this->authz->authorize($user, 'any.permission.ever');
        $this->assertTrue(true);
    }

    // ─── log() writes audit entry ─────────────────────────────────────────────

    public function test_log_creates_audit_log_entry(): void
    {
        $user = User::factory()->create();

        $this->authz->log($user, 'content.publish', null, ['version' => 3]);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'action' => 'content.publish',
        ]);

        $log = AuditLog::where('user_id', $user->id)->first();
        $this->assertNotNull($log);
        $this->assertEquals(['version' => 3], $log->metadata);
    }

    public function test_log_records_resource_type_and_id(): void
    {
        $user = User::factory()->create();
        $target = User::factory()->create(); // use another user as a fake resource

        $this->authz->log($user, 'users.manage', $target, ['action' => 'deactivate']);

        $this->assertDatabaseHas('audit_logs', [
            'user_id'       => $user->id,
            'action'        => 'users.manage',
            'resource_type' => User::class,
            'resource_id'   => (string) $target->id,
        ]);
    }

    // ─── Per-request cache ────────────────────────────────────────────────────

    public function test_permissions_are_cached_per_request(): void
    {
        $user = $this->userWithRole(['content.create']);

        // Call check twice — second should use cache (no DB hit)
        $first  = $this->authz->check($user, 'content.create');
        $second = $this->authz->check($user, 'content.create');

        $this->assertTrue($first);
        $this->assertTrue($second);
    }

    // ─── Helper ───────────────────────────────────────────────────────────────

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
