<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_pipelines', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('space_id')->index();
            $table->string('name');
            $table->json('stages');
            $table->json('trigger_config')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('space_id')->references('id')->on('spaces')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_pipelines');
    }
};
