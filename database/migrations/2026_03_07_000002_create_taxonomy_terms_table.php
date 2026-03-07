<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('taxonomy_terms', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('vocabulary_id')->index();
            $table->ulid('parent_id')->nullable()->index();
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->string('path', 1000)->nullable();
            $table->integer('depth')->default(0);
            $table->integer('sort_order')->default(0);
            $table->json('metadata')->nullable();
            $table->integer('content_count')->default(0);
            $table->timestamps();

            $table->unique(['vocabulary_id', 'slug']);
            $table->index(['vocabulary_id', 'parent_id']);
            if (DB::getDriverName() === 'mysql') {
                $table->rawIndex('`path`(768)', 'taxonomy_terms_path_index');
            } else {
                $table->index('path', 'taxonomy_terms_path_index');
            }
            $table->foreign('vocabulary_id')->references('id')->on('vocabularies')->cascadeOnDelete();
            $table->foreign('parent_id')->references('id')->on('taxonomy_terms')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('taxonomy_terms');
    }
};
