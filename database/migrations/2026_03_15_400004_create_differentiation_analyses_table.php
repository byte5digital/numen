<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('differentiation_analyses')) {
            Schema::create('differentiation_analyses', function (Blueprint $table) {
                $table->ulid('id')->primary();
                $table->string('space_id', 26)->index();
                $table->string('content_id', 26)->nullable()->index();
                $table->string('brief_id', 26)->nullable()->index();
                $table->string('competitor_content_id', 26)->index();
                $table->decimal('similarity_score', 5, 4)->default(0);
                $table->decimal('differentiation_score', 5, 4)->default(0);
                $table->json('angles')->nullable();
                $table->json('gaps')->nullable();
                $table->json('recommendations')->nullable();
                $table->timestamp('analyzed_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('differentiation_analyses');
    }
};
