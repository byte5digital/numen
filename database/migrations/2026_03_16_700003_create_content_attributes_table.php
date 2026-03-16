<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('content_attributes')) {
            Schema::create('content_attributes', function (Blueprint $table) {
                $table->char('id', 26)->primary();
                $table->string('space_id', 26)->index();
                $table->string('content_id', 26)->unique();
                $table->string('content_version_id', 26)->nullable();
                $table->string('persona_id', 26)->nullable()->index();
                $table->string('pipeline_run_id', 26)->nullable()->index();
                $table->string('tone', 100)->nullable();
                $table->string('format_type', 100)->nullable();
                $table->unsignedInteger('word_count')->nullable();
                $table->unsignedInteger('heading_count')->nullable();
                $table->unsignedInteger('image_count')->nullable();
                $table->json('topics')->nullable();
                $table->json('target_keywords')->nullable();
                $table->json('taxonomy_terms')->nullable();
                $table->decimal('ai_quality_score', 5, 2)->nullable();
                $table->string('generation_model', 100)->nullable();
                $table->json('generation_params')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('content_attributes');
    }
};
