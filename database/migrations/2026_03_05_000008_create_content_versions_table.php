<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_versions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('content_id')->index();
            $table->unsignedInteger('version_number');

            $table->string('title');
            $table->text('excerpt')->nullable();
            $table->longText('body');
            $table->string('body_format')->default('markdown');

            $table->json('structured_fields')->nullable();
            $table->json('seo_data')->nullable();

            $table->string('author_type');          // ai_agent, human
            $table->string('author_id');
            $table->string('change_reason')->nullable();

            $table->ulid('pipeline_run_id')->nullable();
            $table->json('ai_metadata')->nullable();

            $table->decimal('quality_score', 5, 2)->nullable();
            $table->decimal('seo_score', 5, 2)->nullable();

            $table->timestamps();

            $table->unique(['content_id', 'version_number']);
            $table->foreign('content_id')->references('id')->on('contents')->cascadeOnDelete();
            $table->foreign('pipeline_run_id')->references('id')->on('pipeline_runs');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_versions');
    }
};
