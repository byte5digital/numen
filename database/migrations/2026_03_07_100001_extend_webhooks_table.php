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
            $table->json('retry_policy')->nullable()->after('is_active');

            // Custom headers to send with each delivery
            $table->json('headers')->nullable()->after('retry_policy');

            // Batch mode: aggregate events before sending
            $table->boolean('batch_mode')->default(false)->after('headers');

            // Batch timeout in milliseconds (default 5 seconds)
            $table->unsignedInteger('batch_timeout')->default(5000)->after('batch_mode');

            // Soft deletes
            $table->softDeletes();

            // Note: unique index added separately below (raw statement for utf8mb4 prefix support)
        });

        \DB::statement('ALTER TABLE webhooks ADD UNIQUE webhooks_space_id_url_unique (space_id, url(500))');
    }

    public function down(): void
    {
        \DB::statement('ALTER TABLE webhooks DROP INDEX webhooks_space_id_url_unique');

        Schema::table('webhooks', function (Blueprint $table) {
            $table->dropSoftDeletes();
            $table->dropColumn(['retry_policy', 'headers', 'batch_mode', 'batch_timeout']);
            $table->string('url')->change();
        });
    }
};
