<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('media_usage')) {
            Schema::create('media_usage', function (Blueprint $table) {
                $table->id();
                $table->string('space_id', 26)->index();
                $table->string('media_asset_id', 26); // ULID — no FK constraint (SQLite compatible)
                $table->string('useable_type', 255); // e.g. App\Models\Content
                $table->string('useable_id', 26); // ULID or bigint as string
                $table->string('context', 50)->nullable(); // 'hero_image', 'body', 'thumbnail'
                $table->timestamps();
                $table->index(['media_asset_id']);
                $table->index(['useable_type', 'useable_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('media_usage');
    }
};
