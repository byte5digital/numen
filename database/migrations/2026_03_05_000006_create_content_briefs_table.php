<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_briefs', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('space_id')->index();
            $table->ulid('pipeline_id')->nullable();

            $table->string('title');
            $table->text('description')->nullable();
            $table->json('requirements')->nullable();
            $table->json('reference_urls')->nullable();
            $table->json('target_keywords')->nullable();

            $table->string('content_type_slug');
            $table->string('target_locale')->default('en');
            $table->ulid('persona_id')->nullable();

            $table->string('source')->default('manual');    // manual, scheduled, ai_suggested
            $table->string('priority')->default('normal');   // low, normal, high, urgent
            $table->string('status')->default('pending');    // pending, processing, completed, cancelled

            $table->timestamp('due_at')->nullable();
            $table->timestamps();

            $table->foreign('space_id')->references('id')->on('spaces')->cascadeOnDelete();
            $table->foreign('pipeline_id')->references('id')->on('content_pipelines');
            $table->foreign('persona_id')->references('id')->on('personas');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_briefs');
    }
};
