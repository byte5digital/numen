<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Role;
use App\Models\Space;
use App\Models\User;
use App\Services\Authorization\AuditLogger;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    // ── Writing audit logs ────────────────────────────────────────────────

    public function test_audit_logger_writes_entry(): void
    {
        $user = User::factory()->create();
        $space = Space::factory()->create();
        $role = Role::where('slug', 'viewer')->first();

        /** @var AuditLogger $logger */
        $logger = app(AuditLogger::class);

        $log = $logger->log(
            action: 'content.publish',
            metadata: ['version' => 3],
            user: $user,
            space: $space,
        );

        $this->assertInstanceOf(AuditLog::class, $log);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'space_id' => $space->id,
            'action' => 'content.publish',
        ]);
    }

    public function test_audit_logger_static_write(): void
    {
        $user = User::factory()->create();

        AuditLogger::write(
            action: 'auth.login',
            user: $user,
        );

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'action' => 'auth.login',
        ]);
    }

    public function test_audit_log_stores_resource_polymorphic(): void
    {
        $user = User::factory()->create();
        $space = Space::factory()->create();
        $role = Role::where('slug', 'admin')->first();

        AuditLogger::write(
            action: 'role.assign',
            resource: $role,
            metadata: ['target_user_id' => $user->id],
            user: $user,
            space: $space,
        );

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'role.assign',
            'resource_type' => Role::class,
            'resource_id' => $role->id,
        ]);
    }

    public function test_audit_log_can_be_written_without_user_for_system_actions(): void
    {
        AuditLogger::write(action: 'system.cron.ran');

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'system.cron.ran',
            'user_id' => null,
        ]);
    }

    // ── Immutability ──────────────────────────────────────────────────────

    public function test_audit_log_cannot_be_updated(): void
    {
        $log = AuditLogger::write(action: 'test.event');

        $this->expectException(\LogicException::class);
        $log->action = 'modified.event';
        $log->save();
    }

    public function test_audit_log_cannot_be_deleted_via_model(): void
    {
        $log = AuditLogger::write(action: 'test.event.delete');

        $this->expectException(\LogicException::class);
        $log->delete();
    }

    // ── API querying ──────────────────────────────────────────────────────

    public function test_admin_can_query_audit_logs(): void
    {
        $admin = User::factory()->create();
        $role = Role::where('slug', 'admin')->first();
        $admin->roles()->attach($role->id, ['space_id' => null]);

        AuditLogger::write(action: 'content.publish', user: $admin);
        AuditLogger::write(action: 'content.delete', user: $admin);

        $this->actingAs($admin)
            ->getJson('/api/v1/audit-logs')
            ->assertOk()
            ->assertJsonStructure(['data', 'total', 'per_page', 'current_page']);
    }

    public function test_non_admin_cannot_query_audit_logs(): void
    {
        $viewer = User::factory()->create();
        $viewerRole = Role::where('slug', 'viewer')->first();
        $viewer->roles()->attach($viewerRole->id, ['space_id' => null]);

        $this->actingAs($viewer)
            ->getJson('/api/v1/audit-logs')
            ->assertForbidden();
    }

    public function test_audit_logs_can_be_filtered_by_action(): void
    {
        $admin = User::factory()->create();
        $role = Role::where('slug', 'admin')->first();
        $admin->roles()->attach($role->id, ['space_id' => null]);

        AuditLogger::write(action: 'content.publish');
        AuditLogger::write(action: 'content.delete');
        AuditLogger::write(action: 'role.assign');

        $this->actingAs($admin)
            ->getJson('/api/v1/audit-logs?action=content')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    // ── Prune command ─────────────────────────────────────────────────────

    public function test_prune_command_removes_old_logs(): void
    {
        // Create old log manually via DB to bypass model restrictions
        \Illuminate\Support\Facades\DB::table('audit_logs')->insert([
            'id' => \Illuminate\Support\Str::ulid()->toBase32(),
            'action' => 'old.event',
            'created_at' => now()->subDays(100),
        ]);

        AuditLogger::write(action: 'new.event');

        $this->artisan('numen:audit:prune', ['--days' => 90])
            ->assertSuccessful();

        $this->assertDatabaseMissing('audit_logs', ['action' => 'old.event']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'new.event']);
    }

    public function test_prune_command_dry_run_does_not_delete(): void
    {
        \Illuminate\Support\Facades\DB::table('audit_logs')->insert([
            'id' => \Illuminate\Support\Str::ulid()->toBase32(),
            'action' => 'old.event.dryrun',
            'created_at' => now()->subDays(100),
        ]);

        $this->artisan('numen:audit:prune', ['--days' => 90, '--dry-run' => true])
            ->assertSuccessful();

        $this->assertDatabaseHas('audit_logs', ['action' => 'old.event.dryrun']);
    }
}
