<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests token-level permission scoping (Sanctum PAT abilities).
 *
 * Strategy: uses the /api/v1/permissions endpoint (requires roles.manage)
 * so tests are self-contained with no FK dependencies (space, content_type, etc.).
 */
class TokenScopingTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A user with roles.manage permission, authenticated via a token
     * that includes the roles.manage ability, can access the permissions list.
     */
    public function test_token_with_matching_ability_allows_access(): void
    {
        $user = $this->userWithRole(['roles.manage']);

        // Token explicitly includes the required ability
        $token = $user->createToken('admin-client', ['roles.manage'])->plainTextToken;

        $response = $this->withToken($token)->getJson('/api/v1/permissions');

        // Role has permission AND token allows it → 200
        $response->assertOk()
            ->assertJsonStructure(['data']);
    }

    /**
     * A user with roles.manage role permission, but authenticated via a token
     * that does NOT include the roles.manage ability, is denied.
     */
    public function test_token_without_ability_denies_access(): void
    {
        $user = $this->userWithRole(['roles.manage']);

        // Token has a different ability — NOT roles.manage
        $token = $user->createToken('limited-client', ['content.read'])->plainTextToken;

        $response = $this->withToken($token)->getJson('/api/v1/permissions');

        // Token scope restricts — denied despite role having the permission
        $response->assertForbidden();
    }

    /**
     * Session auth (actingAs, no Sanctum PAT) bypasses token scoping entirely.
     * Role gates are the only check — and they should pass normally.
     */
    public function test_session_auth_without_token_unaffected(): void
    {
        $user = $this->userWithRole(['roles.manage']);

        // actingAs uses session auth; no PAT token → token scoping is a no-op
        $response = $this->actingAs($user)->getJson('/api/v1/permissions');

        $response->assertOk()
            ->assertJsonStructure(['data']);
    }

    /**
     * A wildcard token ability '*' grants access to all permissions,
     * provided the user's roles also grant the permission.
     */
    public function test_wildcard_token_allows_all_permissions(): void
    {
        $user = $this->userWithRole(['roles.manage', 'audit.view']);

        // Wildcard token — should allow everything
        $token = $user->createToken('superadmin-client', ['*'])->plainTextToken;

        // roles.manage endpoint
        $response = $this->withToken($token)->getJson('/api/v1/permissions');

        $response->assertOk()
            ->assertJsonStructure(['data']);
    }

    /**
     * Regression: namespaced wildcard token ability (e.g. 'roles.*') must grant
     * access to child permissions like 'roles.manage'.
     * Previously checkTokenScope() used tokenCan() which did exact matching only,
     * so 'roles.*' never matched 'roles.manage'. Fixed by expanding wildcards directly
     * from the token abilities array.
     */
    public function test_namespaced_wildcard_token_allows_child_permissions(): void
    {
        $user = $this->userWithRole(['roles.manage']);

        // Token has 'roles.*' — should allow 'roles.manage'
        $token = $user->createToken('role-scoped-client', ['roles.*'])->plainTextToken;

        $response = $this->withToken($token)->getJson('/api/v1/permissions');

        // Role has permission AND token wildcard covers it → 200
        $response->assertOk()
            ->assertJsonStructure(['data']);
    }

    /**
     * Regression: a token with 'content.*' must NOT grant access to 'roles.manage'.
     * The wildcard only covers its own namespace.
     */
    public function test_namespaced_wildcard_does_not_cross_namespace(): void
    {
        $user = $this->userWithRole(['roles.manage']);

        // Token has 'content.*' — should NOT allow 'roles.manage'
        $token = $user->createToken('content-only-client', ['content.*'])->plainTextToken;

        $response = $this->withToken($token)->getJson('/api/v1/permissions');

        // Token scope blocks even though role has the permission
        $response->assertForbidden();
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function userWithRole(array $permissions): User
    {
        $user = User::factory()->create();

        $role = Role::create([
            'name' => 'Test Role',
            'slug' => 'test-role-'.uniqid(),
            'permissions' => $permissions,
            'is_system' => false,
        ]);

        $user->roles()->attach($role->id, ['space_id' => null]);
        $user->load('roles');

        return $user;
    }
}
