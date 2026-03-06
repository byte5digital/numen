<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pipeline_runs', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('pipeline_id');
            $table->ulid('content_id')->nullable();
            $table->ulid('content_brief_id')->nullable();

            $table->string('status')->default('pending'); // pending, running, paused_for_review, paused_budget, completed, failed
            $table->string('current_stage')->nullable();
            $table->json('stage_results')->nullable();
            $table->json('context')->nullable();

            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->foreign('pipeline_id')->references('id')->on('content_pipelines');
            $table->foreign('content_id')->references('id')->on('contents');
            $table->foreign('content_brief_id')->references('id')->on('content_briefs');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pipeline_runs');
    }
};
