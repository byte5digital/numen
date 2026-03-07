<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('search_synonyms', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('space_id');
            $table->string('term', 255);
            $table->json('synonyms');
            $table->boolean('is_one_way')->default(false);
            $table->string('source', 32)->default('manual');
            $table->boolean('approved')->default(true);
            $table->timestamps();

            $table->foreign('space_id')->references('id')->on('spaces')->cascadeOnDelete();
            $table->unique(['space_id', 'term']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('search_synonyms');
    }
};
