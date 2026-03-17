<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('performance_correlations')) {
            Schema::create('performance_correlations', function (Blueprint $table) {
                $table->char('id', 26)->primary();
                $table->string('space_id', 26)->index();
                $table->string('content_id', 26)->index();
                $table->string('attribute_name', 100);
                $table->string('metric_name', 100);
                $table->decimal('correlation_coefficient', 7, 4);
                $table->decimal('p_value', 7, 4)->nullable();
                $table->unsignedInteger('sample_size')->default(0);
                $table->string('insight')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->index(['space_id', 'attribute_name', 'metric_name']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('performance_correlations');
    }
};
