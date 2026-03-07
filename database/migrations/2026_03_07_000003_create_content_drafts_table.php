<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_drafts', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('content_id')->index();
            $table->foreignId('user_id')->index();

            // The working data (mirrors ContentVersion fields)
            $table->string('title');
            $table->text('excerpt')->nullable();
            $table->longText('body');
            $table->string('body_format')->default('markdown');
            $table->json('structured_fields')->nullable();
            $table->json('seo_data')->nullable();
            $table->json('blocks_snapshot')->nullable(); // serialized ContentBlock array

            // Which version this draft is based on
            $table->ulid('base_version_id')->nullable();

            // Auto-save metadata
            $table->timestamp('last_saved_at');
            $table->unsignedInteger('save_count')->default(0);

            $table->timestamps();

            // One draft per user per content
            $table->unique(['content_id', 'user_id']);

            $table->foreign('content_id')->references('id')->on('contents')->cascadeOnDelete();
            $table->foreign('base_version_id')->references('id')->on('content_versions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_drafts');
    }
};
