<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('component_definitions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('type')->unique();       // e.g. "comparison_table", "timeline"
            $table->string('label');                // human-readable
            $table->text('description')->nullable();// what it does / when to use it
            $table->json('schema');                 // field definitions (same format as PageComponent::typeSchema)
            $table->text('vue_template')->nullable();// optional custom render template (v-html fallback if null)
            $table->boolean('is_builtin')->default(false);
            $table->string('created_by')->default('human'); // 'human' | 'ai_agent'
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('component_definitions');
    }
};
