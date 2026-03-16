<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('content_ab_tests')) {
            Schema::create('content_ab_tests', function (Blueprint $table) {
                $table->char('id', 26)->primary();
                $table->string('space_id', 26)->index();
                $table->string('name');
                $table->text('hypothesis')->nullable();
                $table->string('status', 50)->default('draft');
                $table->string('metric', 100);
                $table->decimal('traffic_split', 5, 4)->default(0.5);
                $table->unsignedInteger('min_sample_size')->default(100);
                $table->decimal('significance_threshold', 5, 4)->default(0.95);
                $table->timestamp('started_at')->nullable();
                $table->timestamp('ended_at')->nullable();
                $table->string('winner_variant_id', 26)->nullable();
                $table->json('conclusion')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('content_ab_tests');
    }
};
