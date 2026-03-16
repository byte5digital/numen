<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('competitor_sources')) {
            Schema::create('competitor_sources', function (Blueprint $table) {
                $table->ulid('id')->primary();
                $table->string('space_id', 26)->index();
                $table->string('name');
                $table->string('url');
                $table->string('feed_url')->nullable();
                $table->enum('crawler_type', ['rss', 'sitemap', 'scrape', 'api'])->default('rss');
                $table->json('config')->nullable();
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('crawl_interval_minutes')->default(60);
                $table->timestamp('last_crawled_at')->nullable();
                $table->unsignedInteger('error_count')->default(0);
                $table->timestamps();
                $table->softDeletes();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('competitor_sources');
    }
};
