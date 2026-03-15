<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('content_quality_scores')) {
            Schema::create('content_quality_scores', function (Blueprint $table) {
                $table->ulid('id')->primary();
                $table->string('space_id', 26)->index();
                $table->string('content_id', 26)->index();
                $table->string('content_version_id', 26)->nullable()->index();
                $table->decimal('overall_score', 5, 2);
                $table->decimal('readability_score', 5, 2)->nullable();
                $table->decimal('seo_score', 5, 2)->nullable();
                $table->decimal('brand_score', 5, 2)->nullable();
                $table->decimal('factual_score', 5, 2)->nullable();
                $table->decimal('engagement_score', 5, 2)->nullable();
                $table->string('scoring_model')->nullable();
                $table->integer('scoring_duration_ms')->nullable();
                $table->timestamp('scored_at');
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('content_quality_scores');
    }
};
