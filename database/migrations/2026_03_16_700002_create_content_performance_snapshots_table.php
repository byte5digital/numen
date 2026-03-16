<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('content_performance_snapshots')) {
            Schema::create('content_performance_snapshots', function (Blueprint $table) {
                $table->char('id', 26)->primary();
                $table->string('space_id', 26)->index();
                $table->string('content_id', 26)->index();
                $table->string('content_version_id', 26)->nullable();
                $table->string('period_type', 20);
                $table->date('period_start')->index();
                $table->unsignedBigInteger('views')->default(0);
                $table->unsignedBigInteger('unique_visitors')->default(0);
                $table->decimal('avg_time_on_page_s', 8, 2)->nullable();
                $table->decimal('bounce_rate', 5, 4)->nullable();
                $table->decimal('avg_scroll_depth', 5, 4)->nullable();
                $table->unsignedBigInteger('engagement_events')->default(0);
                $table->unsignedBigInteger('conversions')->default(0);
                $table->decimal('conversion_rate', 5, 4)->nullable();
                $table->decimal('composite_score', 5, 2)->nullable();
                $table->timestamps();

                $table->unique(['content_id', 'period_type', 'period_start']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('content_performance_snapshots');
    }
};
