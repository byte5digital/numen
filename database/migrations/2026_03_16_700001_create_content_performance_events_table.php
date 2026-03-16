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
                $table->char('id', 26)->primary();
                $table->string('space_id', 26)->index();
                $table->string('content_id', 26)->index();
                $table->string('content_version_id', 26)->nullable()->index();
                $table->string('variant_id', 26)->nullable()->index();
                $table->string('event_type', 50);
                $table->string('source', 50);
                $table->decimal('value', 10, 4)->default(1);
                $table->json('metadata')->nullable();
                $table->string('session_id')->nullable()->index();
                $table->string('visitor_id')->nullable()->index();
                $table->timestamp('occurred_at')->index();
                $table->timestamps();

                $table->index(['content_id', 'event_type', 'occurred_at']);
                $table->index(['space_id', 'occurred_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('content_performance_events');
    }
};
