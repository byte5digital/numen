<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('media_assets')) {
            Schema::create('media_assets', function (Blueprint $table) {
                $table->ulid('id')->primary();
                $table->ulid('space_id')->index();
                $table->string('filename');
                $table->string('disk')->default('local');
                $table->string('path');
                $table->string('mime_type');
                $table->unsignedBigInteger('size_bytes');
                $table->string('source')->default('upload'); // upload, ai_generated, stock_api
                $table->json('ai_metadata')->nullable();
                $table->json('variants')->nullable();
                $table->timestamps();

                $table->foreign('space_id')->references('id')->on('spaces')->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('content_media')) {
            Schema::create('content_media', function (Blueprint $table) {
                $table->ulid('content_id');
                $table->ulid('media_asset_id');
                $table->string('role')->default('inline'); // featured, inline, gallery
                $table->unsignedInteger('sort_order')->default(0);
                $table->primary(['content_id', 'media_asset_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('content_media');
        Schema::dropIfExists('media_assets');
    }
};
