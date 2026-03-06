<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pages', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('space_id')->index();
            $table->string('slug')->index();
            $table->string('title');
            $table->string('status')->default('draft'); // draft, published
            $table->json('meta')->nullable(); // seo title, description, og:image
            $table->timestamps();

            $table->unique(['space_id', 'slug']);
            $table->foreign('space_id')->references('id')->on('spaces')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pages');
    }
};
