<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('content_ab_variants')) {
            Schema::create('content_ab_variants', function (Blueprint $table) {
                $table->char('id', 26)->primary();
                $table->string('test_id', 26)->index();
                $table->string('content_id', 26)->index();
                $table->string('label');
                $table->boolean('is_control')->default(false);
                $table->json('generation_params')->nullable();
                $table->decimal('composite_score', 5, 2)->nullable();
                $table->unsignedBigInteger('view_count')->default(0);
                $table->decimal('conversion_rate', 5, 4)->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('content_ab_variants');
    }
};
