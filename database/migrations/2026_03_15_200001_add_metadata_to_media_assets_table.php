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
            $table->unsignedBigInteger('folder_id')->nullable()->after('space_id');

            // Accessibility & editorial
            $table->string('alt_text', 500)->nullable()->after('filename');
            $table->text('caption')->nullable()->after('alt_text');

            // Taxonomy
            $table->json('tags')->nullable()->after('caption'); // array of strings (AI + manual)

            // File info (mime_type already exists; size_bytes already exists)
            $table->unsignedBigInteger('file_size')->nullable()->after('size_bytes'); // explicit bytes alias
            $table->unsignedSmallInteger('width')->nullable()->after('file_size');
            $table->unsignedSmallInteger('height')->nullable()->after('width');
            $table->unsignedInteger('duration')->nullable()->after('height'); // seconds (video/audio)

            // Extended metadata (EXIF, AI model details, etc.)
            $table->json('metadata')->nullable()->after('ai_metadata');

            // Visibility
            $table->boolean('is_public')->default(true)->after('metadata');

            $table->index('folder_id');
        });
    }

    public function down(): void
    {
        Schema::table('media_assets', function (Blueprint $table) {
            $table->dropIndex(['folder_id']);
            $table->dropColumn([
                'folder_id',
                'alt_text',
                'caption',
                'tags',
                'file_size',
                'width',
                'height',
                'duration',
                'metadata',
                'is_public',
            ]);
        });
    }
};
