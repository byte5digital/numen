<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_blocks', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('content_version_id')->index();
            $table->string('type'); // paragraph, heading, code_block, quote, callout, divider, image, + any custom type
            $table->unsignedInteger('sort_order')->default(0);
            $table->json('data')->nullable();           // structured fields per type
            $table->text('wysiwyg_override')->nullable(); // raw HTML override
            $table->timestamps();

            $table->foreign('content_version_id')->references('id')->on('content_versions')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_blocks');
    }
};
