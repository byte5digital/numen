<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Security hardening migration:
 * - Adds index on content_versions.status for faster scope queries
 * - Adds index on scheduled_publishes.version_id (missing FK index)
 * - Adds index on scheduled_publishes.status for fast pending lookups
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('content_versions', function (Blueprint $table) {
            $table->index('status');
            $table->index(['content_id', 'status']);
        });

        Schema::table('scheduled_publishes', function (Blueprint $table) {
            $table->index('version_id');
            $table->index(['status', 'publish_at']); // for due() scope efficiency
        });
    }

    public function down(): void
    {
        Schema::table('content_versions', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropIndex(['content_id', 'status']);
        });

        Schema::table('scheduled_publishes', function (Blueprint $table) {
            $table->dropIndex(['version_id']);
            $table->dropIndex(['status', 'publish_at']);
        });
    }
};
