<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('content_performance_events')) {
            Schema::create('content_performance_events', function (Blueprint $table) {
                $table->ulid('id')->primary();
                $table->string('space_id', 26)->index();
                $table->string('content_id', 26)->index();
                $table->string('event_type', 50)->index();
                $table->string('source', 50);
                $table->decimal('value', 16, 4)->nullable();
                $table->json('metadata')->nullable();
                $table->string('session_id')->index();
                $table->string('visitor_id')->nullable()->index();
                $table->timestamp('occurred_at')->index();
                $table->timestamps();

                $table->index(['session_id', 'content_id', 'event_type', 'occurred_at'], 'cpe_dedup_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('content_performance_events');
    }
};
