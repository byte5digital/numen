<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('search_analytics', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('space_id');
            $table->string('query', 500);
            $table->string('query_normalized', 500);
            $table->string('tier', 16);
            $table->integer('results_count')->default(0);
            $table->ulid('clicked_content_id')->nullable();
            $table->integer('click_position')->nullable();
            $table->integer('response_time_ms')->default(0);
            $table->string('session_id', 64)->nullable();
            $table->string('locale', 10)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('space_id')->references('id')->on('spaces')->cascadeOnDelete();
            $table->index(['space_id', 'query_normalized']);
            $table->index('created_at');
        });

        // Partial index for zero-result queries (PostgreSQL only)
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('
                CREATE INDEX idx_analytics_zero_results ON search_analytics (space_id, results_count)
                WHERE results_count = 0
            ');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('search_analytics');
    }
};
