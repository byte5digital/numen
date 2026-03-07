<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_audit_logs(): void
    {
        $admin = $this->userWithRole(['*']);

        AuditLog::create([
            'user_id'    => $admin->id,
            'action'     => 'content.create',
            'metadata'   => [],
            'created_at' => now(),
        ]);

        $response = $this->actingAs($admin)->getJson('/api/v1/audit-logs');

        $response->assertOk()
            ->assertJsonStructure(['data', 'meta' => ['total', 'per_page', 'current_page']])
            ->assertJsonPath('meta.total', 1);
    }

    public function test_author_cannot_view_audit_logs(): void
    {
        $author = $this->userWithRole(['content.create', 'content.read', 'ai.generate']);

        $response = $this->actingAs($author)->getJson('/api/v1/audit-logs');

        $response->assertForbidden();
    }

    public function test_can_filter_by_action(): void
    {
        $admin = $this->userWithRole(['*']);

        AuditLog::create(['user_id' => $admin->id, 'action' => 'content.create', 'metadata' => [], 'created_at' => now()]);
        AuditLog::create(['user_id' => $admin->id, 'action' => 'role.assign',    'metadata' => [], 'created_at' => now()]);

        $response = $this->actingAs($admin)->getJson('/api/v1/audit-logs?action=role.assign');

        $response->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.action', 'role.assign');
    }

    public function test_can_filter_by_user_id(): void
    {
        $admin = $this->userWithRole(['*']);
        $other = User::factory()->create();

        AuditLog::create(['user_id' => $admin->id, 'action' => 'content.create', 'metadata' => [], 'created_at' => now()]);
        AuditLog::create(['user_id' => $other->id, 'action' => 'content.create', 'metadata' => [], 'created_at' => now()]);

        $response = $this->actingAs($admin)->getJson("/api/v1/audit-logs?user_id={$other->id}");

        $response->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.user_id', $other->id);
    }

    public function test_audit_log_created_on_role_assign(): void
    {
        $admin  = $this->userWithRole(['*']);
        $target = User::factory()->create();
        $role   = Role::create([
            'name'        => 'Some Role',
            'slug'        => 'some-role-audit',
            'permissions' => ['content.read'],
            'is_system'   => false,
        ]);

        $this->actingAs($admin)->postJson("/api/v1/users/{$target->id}/roles", [
            'role_id' => $role->id,
        ])->assertCreated();

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $admin->id,
            'action'  => 'role.assign',
        ]);
    }

    public function test_audit_log_created_on_content_create(): void
    {
        $admin = $this->userWithRole(['*']);

        $space = \App\Models\Space::create(['name' => 'Test Space', 'slug' => 'test-space']);
        $contentType = \App\Models\ContentType::create([
            'space_id' => $space->id,
            'name'     => 'Article',
            'slug'     => 'article',
            'schema'   => [],
        ]);

        $this->actingAs($admin)->postJson('/api/v1/content', [
            'slug'            => 'test-content-audit',
            'content_type_id' => $contentType->id,
            'space_id'        => $space->id,
        ])->assertCreated();

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $admin->id,
            'action'  => 'content.create',
        ]);
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
}
