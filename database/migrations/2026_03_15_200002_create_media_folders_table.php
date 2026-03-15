<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_folders', function (Blueprint $table) {
            $table->id();
            $table->string('space_id', 26)->index();
            $table->unsignedBigInteger('parent_id')->nullable(); // adjacency list (self-referential FK added below)
            $table->string('name', 255);
            $table->string('slug', 255);
            $table->text('description')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index(['space_id', 'parent_id']);
            $table->index(['space_id', 'slug', 'parent_id']); // uniqueness enforced at app layer (SQLite-safe)
        });

        // Add self-referential FK after table exists
        Schema::table('media_folders', function (Blueprint $table) {
            $table->foreign('parent_id')
                ->references('id')
                ->on('media_folders')
                ->nullOnDelete();
        });

        // Wire media_assets.folder_id FK now that media_folders exists
        Schema::table('media_assets', function (Blueprint $table) {
            $table->foreign('folder_id')
                ->references('id')
                ->on('media_folders')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('media_assets', function (Blueprint $table) {
            $table->dropForeign(['folder_id']);
        });

        Schema::table('media_folders', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
        });

        Schema::dropIfExists('media_folders');
    }
};
