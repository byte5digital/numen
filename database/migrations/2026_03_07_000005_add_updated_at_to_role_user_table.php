<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add the missing updated_at column to role_user pivot table.
 *
 * The original migration only created created_at. The BelongsToMany
 * relationships use withTimestamps() which requires both columns.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('role_user', function (Blueprint $table) {
            $table->timestamp('updated_at')->nullable()->after('created_at');
        });
    }

    public function down(): void
    {
        Schema::table('role_user', function (Blueprint $table) {
            $table->dropColumn('updated_at');
        });
    }
};
