<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('personas', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('space_id')->index();
            $table->string('name');
            $table->string('role');              // creator, optimizer, reviewer, editor
            $table->text('system_prompt');
            $table->json('capabilities');        // ['content_generation', 'seo_analysis', ...]
            $table->json('model_config');         // model, temperature, max_tokens
            $table->json('voice_guidelines')->nullable();
            $table->json('constraints')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('space_id')->references('id')->on('spaces')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personas');
    }
};
