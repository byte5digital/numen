<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('content_quality_configs')) {
            Schema::create('content_quality_configs', function (Blueprint $table) {
                $table->ulid('id')->primary();
                $table->string('space_id', 26)->index();
                $table->json('dimension_weights');
                $table->json('thresholds');
                $table->json('enabled_dimensions');
                $table->boolean('auto_score_on_publish')->default(true);
                $table->boolean('pipeline_gate_enabled')->default(false);
                $table->decimal('pipeline_gate_min_score', 5, 2)->default(70);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('content_quality_configs');
    }
};
