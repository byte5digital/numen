<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contents', function (Blueprint $table) {
            // Points to the active draft (mutable working copy)
            $table->ulid('draft_version_id')->nullable()->after('current_version_id');

            // Convenience column for the next scheduled publish time
            $table->timestamp('scheduled_publish_at')->nullable()->after('refresh_at');
        });
    }

    public function down(): void
    {
        Schema::table('contents', function (Blueprint $table) {
            $table->dropColumn(['draft_version_id', 'scheduled_publish_at']);
        });
    }
};
