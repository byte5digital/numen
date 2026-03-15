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

        // Add unique index — cross-database compatible (works on MySQL and SQLite)
        if (! Schema::hasIndex('webhooks', 'webhooks_space_id_url_unique')) {
            $driver = \DB::getDriverName();
            if ($driver === 'mysql') {
                // MySQL requires a column prefix for long varchar columns to stay within key length limit
                \DB::statement('CREATE UNIQUE INDEX webhooks_space_id_url_unique ON webhooks (space_id, url(500))');
            } else {
                Schema::table('webhooks', function (Blueprint $table) {
                    $table->unique(['space_id', 'url'], 'webhooks_space_id_url_unique');
                });
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasIndex('webhooks', 'webhooks_space_id_url_unique')) {
            $driver = \DB::getDriverName();
            if ($driver === 'mysql') {
                \DB::statement('ALTER TABLE webhooks DROP INDEX webhooks_space_id_url_unique');
            } else {
                Schema::table('webhooks', function (Blueprint $table) {
                    $table->dropUnique('webhooks_space_id_url_unique');
                });
            }
        }

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
