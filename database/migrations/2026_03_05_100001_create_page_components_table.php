<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('page_components', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('page_id')->index();
            $table->string('type'); // hero, stats_row, feature_grid, pipeline_steps, content_list, cta_block, tech_stack, rich_text
            $table->unsignedInteger('sort_order')->default(0);
            $table->json('data')->nullable();           // structured fields per component type
            $table->text('wysiwyg_override')->nullable(); // raw HTML; when set, rendered instead of data
            $table->boolean('ai_generated')->default(false);
            $table->boolean('locked')->default(false);  // prevents AI pipeline from overwriting
            $table->ulid('ai_brief_id')->nullable();    // FK to content_briefs (loose reference)
            $table->timestamps();

            $table->foreign('page_id')->references('id')->on('pages')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('page_components');
    }
};
