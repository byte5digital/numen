<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('space_performance_models')) {
            Schema::create('space_performance_models', function (Blueprint $table) {
                $table->char('id', 26)->primary();
                $table->string('space_id', 26)->unique();
                $table->json('attribute_weights')->nullable();
                $table->json('top_performers')->nullable();
                $table->json('bottom_performers')->nullable();
                $table->json('topic_scores')->nullable();
                $table->json('persona_scores')->nullable();
                $table->unsignedInteger('sample_size')->default(0);
                $table->decimal('model_confidence', 5, 4)->default(0);
                $table->string('model_version', 20)->default('v1');
                $table->timestamp('computed_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('space_performance_models');
    }
};
