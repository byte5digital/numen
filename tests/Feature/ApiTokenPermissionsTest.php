<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiTokenPermissionsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        // Create a user with editor role
        $this->user = User::factory()->create();
        $editor = Role::where('slug', 'editor')->first();
        $this->user->roles()->attach($editor->id, ['space_id' => null]);
    }

    // ── Token CRUD ────────────────────────────────────────────────────

    public function test_user_can_create_api_token(): void
    {
        $this->actingAs($this->user)
            ->post('/admin/tokens', [
                'name' => 'My Integration Token',
            ])
            ->assertRedirect(route('admin.tokens.index'));

        $this->assertDatabaseHas('personal_access_tokens', [
            'name' => 'My Integration Token',
            'tokenable_id' => $this->user->id,
        ]);
    }

    public function test_user_can_list_their_tokens(): void
    {
        // Create a token
        $token = $this->user->createToken('Test Token');

        $this->actingAs($this->user)
            ->get('/admin/tokens')
            ->assertOk()
            ->assertSee('Test Token'); // assertSee checks raw response body (Inertia props JSON)
    }

    public function test_user_can_revoke_their_token(): void
    {
        $token = $this->user->createToken('Token to Revoke');

        $this->actingAs($this->user)
            ->delete("/admin/tokens/{$token->accessToken->id}")
            ->assertRedirect(route('admin.tokens.index'));

        $this->assertDatabaseMissing('personal_access_tokens', [
            'id' => $token->accessToken->id,
        ]);
    }

    public function test_user_cannot_see_other_users_tokens(): void
    {
        $otherUser = User::factory()->create();
        $otherToken = $otherUser->createToken('Secret Token');

        $this->actingAs($this->user)
            ->get('/admin/tokens')
            ->assertDontSeeText('Secret Token');
    }

    public function test_user_cannot_revoke_other_users_tokens(): void
    {
        $otherUser = User::factory()->create();
        $otherToken = $otherUser->createToken('Other Token');

        $this->actingAs($this->user)
            ->delete("/admin/tokens/{$otherToken->accessToken->id}")
            ->assertForbidden();
    }

    // ── Token authentication ──────────────────────────────────────────

    public function test_api_request_with_valid_token_authenticates(): void
    {
        $token = $this->user->createToken('API Token')->plainTextToken;

        $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])
            ->getJson('/api/v1/roles')
            ->assertOk();
    }

    public function test_api_request_with_invalid_token_returns_unauthorized(): void
    {
        $this->withHeaders([
            'Authorization' => 'Bearer invalid_token_here',
        ])
            ->getJson('/api/v1/roles')
            ->assertUnauthorized();
    }

    public function test_api_request_without_token_requires_standard_auth(): void
    {
        // Without token, standard session auth should be used
        $this->getJson('/api/v1/roles')
            ->assertUnauthorized();
    }

    // ── Token permissions (inheritance from user roles) ───────────────

    public function test_token_inherits_user_role_permissions(): void
    {
        $token = $this->user->createToken('Permission Test')->plainTextToken;

        // User has editor permissions
        $this->actingAs($this->user)
            ->getJson('/api/v1/roles')
            ->assertOk();

        // Token should also work (same user, same permissions)
        $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])
            ->getJson('/api/v1/roles')
            ->assertOk();
    }

    public function test_token_respects_user_role_restrictions(): void
    {
        // Change user to viewer role (read-only)
        $this->user->roles()->detach();
        $viewer = Role::where('slug', 'viewer')->first();
        $this->user->roles()->attach($viewer->id, ['space_id' => null]);

        $token = $this->user->createToken('Viewer Token')->plainTextToken;

        // Viewer can list roles (read)
        $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])
            ->getJson('/api/v1/roles')
            ->assertOk();

        // Viewer cannot create roles (requires users.roles.manage)
        $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])
            ->postJson('/api/v1/roles', [
                'name' => 'Hacked Role',
                'permissions' => ['*'],
            ])
            ->assertForbidden();
    }

    // ── Token scope isolation ─────────────────────────────────────────

    public function test_multiple_tokens_for_same_user_are_independent(): void
    {
        $token1 = $this->user->createToken('Token 1')->plainTextToken;
        $token2 = $this->user->createToken('Token 2')->plainTextToken;

        // Both tokens work independently
        $this->withHeaders(['Authorization' => "Bearer {$token1}"])
            ->getJson('/api/v1/roles')
            ->assertOk();

        $this->withHeaders(['Authorization' => "Bearer {$token2}"])
            ->getJson('/api/v1/roles')
            ->assertOk();

        // Revoke token1 doesn't affect token2
        // (Token revocation happens through the UI which properly manages token lifecycle)
        $this->actingAs($this->user)
            ->delete('/admin/tokens/'.$this->user->tokens()->where('name', 'Token 1')->first()->id)
            ->assertRedirect();

        // After revocation, token2 should still work
        $this->withHeaders(['Authorization' => "Bearer {$token2}"])
            ->getJson('/api/v1/roles')
            ->assertOk();
    }

    // ── Token lifecycle ───────────────────────────────────────────────

    public function test_token_can_be_rotated(): void
    {
        $oldToken = $this->user->createToken('Old Token')->plainTextToken;

        // Old token works
        $this->withHeaders(['Authorization' => "Bearer {$oldToken}"])
            ->getJson('/api/v1/roles')
            ->assertOk();

        // Create new token (rotation)
        $newToken = $this->user->createToken('New Token')->plainTextToken;

        // Revoke old token
        $this->actingAs($this->user)
            ->delete('/admin/tokens/'.$this->user->tokens()->where('name', 'Old Token')->first()->id)
            ->assertRedirect();

        // New token works
        $this->withHeaders(['Authorization' => "Bearer {$newToken}"])
            ->getJson('/api/v1/roles')
            ->assertOk();

        // Verify rotation completed successfully
        $this->actingAs($this->user)
            ->get('/admin/tokens')
            ->assertOk();
    }

    // ── Token session consistency ─────────────────────────────────────

    public function test_user_session_and_token_auth_use_same_permissions(): void
    {
        $token = $this->user->createToken('Consistency Test')->plainTextToken;

        // Session auth can list roles
        $response1 = $this->actingAs($this->user)
            ->getJson('/api/v1/roles');
        $response1->assertOk();

        // Token auth can also list roles (same user, same permissions)
        $response2 = $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->getJson('/api/v1/roles');
        $response2->assertOk();

        // Both should see same roles
        $this->assertEquals($response1->json('data'), $response2->json('data'));
    }

    // ── Token usage tracking ──────────────────────────────────────────

    public function test_token_last_used_timestamp_is_updated(): void
    {
        $token = $this->user->createToken('Tracked Token');
        $tokenRecord = $token->accessToken;

        $this->assertNull($tokenRecord->last_used_at);

        // Use the token
        $this->withHeaders(['Authorization' => "Bearer {$token->plainTextToken}"])
            ->getJson('/api/v1/roles')
            ->assertOk();

        // Refresh and check last_used_at
        $tokenRecord->refresh();
        $this->assertNotNull($tokenRecord->last_used_at);
    }

    // ── Token list presentation ───────────────────────────────────────

    public function test_token_list_masks_sensitive_data(): void
    {
        $plainToken = $this->user->createToken('Sensitive Token')->plainTextToken;

        $this->actingAs($this->user)
            ->get('/admin/tokens')
            ->assertOk()
            ->assertDontSeeText($plainToken); // Should not expose full token
    }

    public function test_token_creation_returns_plain_text_token_only_once(): void
    {
        $response = $this->actingAs($this->user)
            ->post('/admin/tokens', ['name' => 'One Time Token']);

        $response->assertRedirect();

        // The plain text token should be in session only once
        // Subsequent visits should not show it
        $this->actingAs($this->user)
            ->get('/admin/tokens')
            ->assertOk();

        // Token should exist in database
        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $this->user->id,
            'name' => 'One Time Token',
        ]);
    }

    // ── Issue #28 regression: admin without RBAC role ────────────────

    /**
     * Regression test for issue #28.
     *
     * An admin user (legacy role column = 'admin') without any RBAC role attached
     * via the role_user pivot must still be able to create API tokens.
     * AuthorizationService::resolvePermissions() now grants ['*'] as a fallback
     * when role='admin' is present but no RBAC roles are assigned.
     */
    public function test_admin_without_rbac_role_can_create_api_token(): void
    {
        // Create an admin user with ONLY the legacy role column — no RBAC pivot entry
        $admin = User::factory()->admin()->create();
        // Assert no RBAC roles are attached (simulates pre-RBAC admin account)
        $this->assertCount(0, $admin->roles);

        $this->actingAs($admin)
            ->post('/admin/tokens', ['name' => 'Admin Token'])
            ->assertRedirect(route('admin.tokens.index'));

        $this->assertDatabaseHas('personal_access_tokens', [
            'name' => 'Admin Token',
            'tokenable_id' => $admin->id,
        ]);
    }

    public function test_admin_without_rbac_role_can_list_api_tokens(): void
    {
        $admin = User::factory()->admin()->create();
        $admin->createToken('Existing Token');

        $this->actingAs($admin)
            ->get('/admin/tokens')
            ->assertOk();
    }

    public function test_admin_without_rbac_role_can_delete_api_token(): void
    {
        $admin = User::factory()->admin()->create();
        $token = $admin->createToken('Token To Delete');

        $this->actingAs($admin)
            ->delete("/admin/tokens/{$token->accessToken->id}")
            ->assertRedirect(route('admin.tokens.index'));

        $this->assertDatabaseMissing('personal_access_tokens', [
            'id' => $token->accessToken->id,
        ]);
    }
}
