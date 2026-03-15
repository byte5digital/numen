<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('webhooks', function (Blueprint $table) {
            // Extend url column to 2048 chars
            $table->string('url', 2048)->change();

            // Add retry policy (exponential backoff config)
            if (! Schema::hasColumn('webhooks', 'retry_policy')) {
                $table->json('retry_policy')->nullable()->after('is_active');
            }

            // Custom headers to send with each delivery
            if (! Schema::hasColumn('webhooks', 'headers')) {
                $table->json('headers')->nullable()->after('retry_policy');
            }

            // Batch mode: aggregate events before sending
            if (! Schema::hasColumn('webhooks', 'batch_mode')) {
                $table->boolean('batch_mode')->default(false)->after('headers');
            }

            // Batch timeout in milliseconds (default 5 seconds)
            if (! Schema::hasColumn('webhooks', 'batch_timeout')) {
                $table->unsignedInteger('batch_timeout')->default(5000)->after('batch_mode');
            }

            // Soft deletes
            if (! Schema::hasColumn('webhooks', 'deleted_at')) {
                $table->softDeletes();
            }
        });

        // SQLite-compatible unique index (no url(500) prefix — SQLite doesn't support it)
        // For MySQL: the full url column is used; enforce at app layer if key length is an issue
        \DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS webhooks_space_id_url_unique ON webhooks (space_id, url)');
    }

    public function down(): void
    {
        \DB::statement('DROP INDEX IF EXISTS webhooks_space_id_url_unique');

        Schema::table('webhooks', function (Blueprint $table) {
            if (Schema::hasColumn('webhooks', 'deleted_at')) {
                $table->dropSoftDeletes();
            }

            $cols = ['retry_policy', 'headers', 'batch_mode', 'batch_timeout'];
            $existing = array_filter($cols, fn ($c) => Schema::hasColumn('webhooks', $c));
            if ($existing) {
                $table->dropColumn(array_values($existing));
            }

            $table->string('url')->change();
        });
    }
};
