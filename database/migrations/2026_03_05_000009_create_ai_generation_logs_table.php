<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_generation_logs', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('pipeline_run_id')->nullable()->index();
            $table->ulid('persona_id')->nullable();

            $table->string('model');
            $table->string('purpose');              // content_generation, seo_optimization, quality_review

            $table->json('messages');               // full prompt
            $table->longText('response');

            $table->unsignedInteger('input_tokens');
            $table->unsignedInteger('output_tokens');
            $table->unsignedInteger('cache_read_tokens')->default(0);
            $table->decimal('cost_usd', 8, 6);
            $table->unsignedInteger('latency_ms');

            $table->string('stop_reason')->nullable();
            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->index(['purpose', 'created_at']);
            $table->foreign('pipeline_run_id')->references('id')->on('pipeline_runs');
            $table->foreign('persona_id')->references('id')->on('personas');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_generation_logs');
    }
};
