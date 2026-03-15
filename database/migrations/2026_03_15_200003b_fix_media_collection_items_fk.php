<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fix media_collection_items.media_asset_id:
     * The original migration used foreignId() which creates a bigint column,
     * but media_assets.id is a ULID (char/varchar). Drop and recreate as ulid.
     */
    public function up(): void
    {
        Schema::table('media_collection_items', function (Blueprint $table) {
            // Drop the wrong FK constraint and indexes first (SQLite requires index drop before column drop)
            $table->dropForeign(['media_asset_id']);
            $table->dropIndex('media_collection_items_media_asset_id_index');
            $table->dropUnique(['collection_id', 'media_asset_id']);
            $table->dropColumn('media_asset_id');
        });

        Schema::table('media_collection_items', function (Blueprint $table) {
            // Recreate as ulid to match media_assets.id primary key type
            $table->ulid('media_asset_id')->after('collection_id');
            $table->foreign('media_asset_id')
                ->references('id')
                ->on('media_assets')
                ->cascadeOnDelete();
            $table->unique(['collection_id', 'media_asset_id']);
        });
    }

    public function down(): void
    {
        Schema::table('media_collection_items', function (Blueprint $table) {
            $table->dropForeign(['media_asset_id']);
            $table->dropUnique(['collection_id', 'media_asset_id']);
            $table->dropColumn('media_asset_id');
        });

        Schema::table('media_collection_items', function (Blueprint $table) {
            $table->string('media_asset_id', 26)->index();
            $table->unique(['collection_id', 'media_asset_id']);
        });
    }
};
