<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media_assets', function (Blueprint $table) {
            // Folder organisation (FK added after media_folders table exists)
            if (! Schema::hasColumn('media_assets', 'folder_id')) {
                $table->unsignedBigInteger('folder_id')->nullable()->after('space_id');
            }

            // Accessibility & editorial
            if (! Schema::hasColumn('media_assets', 'alt_text')) {
                $table->string('alt_text', 500)->nullable()->after('filename');
            }
            if (! Schema::hasColumn('media_assets', 'caption')) {
                $table->text('caption')->nullable()->after('alt_text');
            }

            // Taxonomy
            if (! Schema::hasColumn('media_assets', 'tags')) {
                $table->json('tags')->nullable()->after('caption'); // array of strings (AI + manual)
            }

            // File info (mime_type already exists; size_bytes already exists)
            if (! Schema::hasColumn('media_assets', 'file_size')) {
                $table->unsignedBigInteger('file_size')->nullable()->after('size_bytes'); // explicit bytes alias
            }
            if (! Schema::hasColumn('media_assets', 'width')) {
                $table->unsignedSmallInteger('width')->nullable()->after('file_size');
            }
            if (! Schema::hasColumn('media_assets', 'height')) {
                $table->unsignedSmallInteger('height')->nullable()->after('width');
            }
            if (! Schema::hasColumn('media_assets', 'duration')) {
                $table->unsignedInteger('duration')->nullable()->after('height'); // seconds (video/audio)
            }

            // Extended metadata (EXIF, AI model details, etc.)
            if (! Schema::hasColumn('media_assets', 'metadata')) {
                $table->json('metadata')->nullable()->after('ai_metadata');
            }

            // Visibility
            if (! Schema::hasColumn('media_assets', 'is_public')) {
                $table->boolean('is_public')->default(true)->after('metadata');
            }
        });

        // Add index separately to guard against it already existing
        $existingIndexes = collect(Schema::getIndexes('media_assets'))->pluck('name');
        if (! $existingIndexes->contains('media_assets_folder_id_index')) {
            Schema::table('media_assets', function (Blueprint $table) {
                $table->index('folder_id');
            });
        }
    }

    public function down(): void
    {
        Schema::table('media_assets', function (Blueprint $table) {
            $existingIndexes = collect(Schema::getIndexes('media_assets'))->pluck('name');
            if ($existingIndexes->contains('media_assets_folder_id_index')) {
                $table->dropIndex(['folder_id']);
            }

            $columns = ['folder_id', 'alt_text', 'caption', 'tags', 'file_size', 'width', 'height', 'duration', 'metadata', 'is_public'];
            $toDrop = array_filter($columns, fn ($col) => Schema::hasColumn('media_assets', $col));
            if (! empty($toDrop)) {
                $table->dropColumn(array_values($toDrop));
            }
        });
    }
};
