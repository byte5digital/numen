<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('media_folders')) {
            Schema::create('media_folders', function (Blueprint $table) {
                $table->id();
                $table->string('space_id', 26)->index();
                $table->unsignedBigInteger('parent_id')->nullable(); // adjacency list (self-referential FK added below)
                $table->string('name', 255);
                $table->string('slug', 255);
                $table->text('description')->nullable();
                $table->integer('sort_order')->default(0);
                $table->timestamps();

                $table->index(['space_id', 'slug', 'parent_id']); // uniqueness enforced at app layer (SQLite-safe)
            });
        }

        // Add self-referential FK after table exists (guard if already present)
        $folderForeignKeys = collect(Schema::getForeignKeys('media_folders'))->pluck('name');
        if (! $folderForeignKeys->contains('media_folders_parent_id_foreign')) {
            Schema::table('media_folders', function (Blueprint $table) {
                $table->foreign('parent_id')
                    ->references('id')
                    ->on('media_folders')
                    ->nullOnDelete();
            });
        }

        // Wire media_assets.folder_id FK now that media_folders exists (guard if already present)
        $assetForeignKeys = collect(Schema::getForeignKeys('media_assets'))->pluck('name');
        if (! $assetForeignKeys->contains('media_assets_folder_id_foreign')) {
            Schema::table('media_assets', function (Blueprint $table) {
                $table->foreign('folder_id')
                    ->references('id')
                    ->on('media_folders')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        $assetForeignKeys = collect(Schema::getForeignKeys('media_assets'))->pluck('name');
        if ($assetForeignKeys->contains('media_assets_folder_id_foreign')) {
            Schema::table('media_assets', function (Blueprint $table) {
                $table->dropForeign(['folder_id']);
            });
        }

        if (Schema::hasTable('media_folders')) {
            $folderForeignKeys = collect(Schema::getForeignKeys('media_folders'))->pluck('name');
            if ($folderForeignKeys->contains('media_folders_parent_id_foreign')) {
                Schema::table('media_folders', function (Blueprint $table) {
                    $table->dropForeign(['parent_id']);
                });
            }
        }

        Schema::dropIfExists('media_folders');
    }
};
