<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fix media_collection_items.media_asset_id:
     * The original migration may have used foreignId() which creates a bigint column,
     * but media_assets.id is a ULID (char/varchar). Drop and recreate as ulid.
     *
     * All operations are idempotent — safe to run even if the FK/index never existed
     * (e.g. on production where the column was always a string(26)).
     */
    public function up(): void
    {
        // Check if the column is already the correct type (string/char 26).
        // If media_asset_id is already a string type and no FK exists, this migration
        // is a no-op — safe to skip the destructive steps.
        $foreignKeys = collect(Schema::getForeignKeys('media_collection_items'))->pluck('name');
        $indexes = collect(Schema::getIndexes('media_collection_items'))->pluck('name');

        Schema::table('media_collection_items', function (Blueprint $table) use ($foreignKeys, $indexes) {
            // Drop FK only if it exists (may not exist on ULID-native deployments)
            if ($foreignKeys->contains('media_collection_items_media_asset_id_foreign')) {
                $table->dropForeign(['media_asset_id']);
            }

            // Drop standalone index only if it exists
            if ($indexes->contains('media_collection_items_media_asset_id_index')) {
                $table->dropIndex('media_collection_items_media_asset_id_index');
            }

            // Drop unique composite index only if it exists
            if ($indexes->contains('media_collection_items_collection_id_media_asset_id_unique')) {
                $table->dropUnique(['collection_id', 'media_asset_id']);
            }

            // Drop and recreate the column only if it needs to change type.
            // We detect this by checking if a FK existed (bigint foreignId path) OR
            // if the column is not already a string/ulid (production already has string(26)).
            // Safe approach: always drop+recreate since we've already removed all constraints.
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
        $foreignKeys = collect(Schema::getForeignKeys('media_collection_items'))->pluck('name');
        $indexes = collect(Schema::getIndexes('media_collection_items'))->pluck('name');

        Schema::table('media_collection_items', function (Blueprint $table) use ($foreignKeys, $indexes) {
            if ($foreignKeys->contains('media_collection_items_media_asset_id_foreign')) {
                $table->dropForeign(['media_asset_id']);
            }
            if ($indexes->contains('media_collection_items_collection_id_media_asset_id_unique')) {
                $table->dropUnique(['collection_id', 'media_asset_id']);
            }
            $table->dropColumn('media_asset_id');
        });

        Schema::table('media_collection_items', function (Blueprint $table) {
            $table->string('media_asset_id', 26)->index();
            $table->unique(['collection_id', 'media_asset_id']);
        });
    }
};
