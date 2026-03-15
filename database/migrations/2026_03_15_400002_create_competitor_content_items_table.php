<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('competitor_content_items')) {
            Schema::create('competitor_content_items', function (Blueprint $table) {
                $table->ulid('id')->primary();
                $table->string('source_id', 26)->index();
                $table->string('external_url');
                $table->string('title')->nullable();
                $table->text('excerpt')->nullable();
                $table->longText('body')->nullable();
                $table->timestamp('published_at')->nullable();
                $table->timestamp('crawled_at')->nullable();
                $table->string('content_hash')->nullable()->index();
                $table->json('metadata')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('competitor_content_items');
    }
};
