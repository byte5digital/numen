<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contents', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('space_id')->index();
            $table->ulid('content_type_id');
            $table->ulid('current_version_id')->nullable();

            $table->string('slug')->index();
            $table->string('status')->default('draft'); // draft, in_pipeline, review, scheduled, published, archived
            $table->string('locale', 10)->default('en');
            $table->ulid('canonical_id')->nullable();   // for translations

            $table->json('taxonomy')->nullable();
            $table->json('metadata')->nullable();

            $table->timestamp('published_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('refresh_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['space_id', 'slug', 'locale']);
            $table->foreign('space_id')->references('id')->on('spaces')->cascadeOnDelete();
            $table->foreign('content_type_id')->references('id')->on('content_types');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contents');
    }
};
