<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Data migration: maps existing users.role string → role_user pivot entries.
 *
 * 'admin'      → Admin role (slug: admin)
 * anything else → Author role (slug: author) — safe default
 *
 * NOTE: Does NOT drop the users.role column — that is handled in a separate
 * cleanup migration once the service layer is proven stable.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Roles must already exist (seeded before or alongside this migration)
        $adminRole = DB::table('roles')->where('slug', 'admin')->whereNull('space_id')->first();
        $authorRole = DB::table('roles')->where('slug', 'author')->whereNull('space_id')->first();

        if (! $adminRole || ! $authorRole) {
            // Seeder not yet run — skip gracefully; the seeder will handle this
            return;
        }

        $users = DB::table('users')->select('id', 'role')->get();

        $now = now();

        foreach ($users as $user) {
            $roleId = $user->role === 'admin' ? $adminRole->id : $authorRole->id;

            DB::table('role_user')->insertOrIgnore([
                'user_id' => $user->id,
                'role_id' => $roleId,
                'space_id' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        // Reversing: remove all role_user entries created from the old role column.
        // We can't easily distinguish them from manually assigned roles, so
        // we simply clear the pivot table on rollback.
        DB::table('role_user')->delete();
    }
};
