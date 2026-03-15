<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('media_collections')) {
            Schema::create('media_collections', function (Blueprint $table) {
                $table->id();
                $table->string('space_id', 26)->index();
                $table->string('name', 255);
                $table->string('slug', 255);
                $table->text('description')->nullable();
                $table->boolean('is_smart')->default(false); // smart collections use saved search criteria
                $table->json('criteria')->nullable(); // query criteria for smart collections
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('media_collection_items')) {
            Schema::create('media_collection_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('collection_id')->index();
                $table->string('media_asset_id', 26)->index();
                $table->integer('sort_order')->default(0);
                $table->timestamp('added_at')->useCurrent();

                $table->unique(['collection_id', 'media_asset_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('media_collection_items');
        Schema::dropIfExists('media_collections');
    }
};
