<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_types', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('space_id')->index();
            $table->string('name');
            $table->string('slug');
            $table->json('schema');
            $table->json('generation_config')->nullable();
            $table->json('seo_config')->nullable();
            $table->timestamps();

            $table->unique(['space_id', 'slug']);
            $table->foreign('space_id')->references('id')->on('spaces')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_types');
    }
};
