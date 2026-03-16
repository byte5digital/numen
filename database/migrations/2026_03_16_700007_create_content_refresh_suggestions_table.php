<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('content_refresh_suggestions')) {
            Schema::create('content_refresh_suggestions', function (Blueprint $table) {
                $table->char('id', 26)->primary();
                $table->string('space_id', 26)->index();
                $table->string('content_id', 26)->index();
                $table->string('status', 50)->default('pending');
                $table->string('trigger_type', 100);
                $table->json('performance_context')->nullable();
                $table->json('suggestions')->nullable();
                $table->decimal('urgency_score', 5, 2)->nullable();
                $table->string('brief_id', 26)->nullable();
                $table->timestamp('triggered_at');
                $table->timestamp('acted_on_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('content_refresh_suggestions');
    }
};
