<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_translation_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('space_id')->constrained()->cascadeOnDelete();
            $table->foreignId('source_content_id')->constrained('contents')->cascadeOnDelete();
            $table->foreignId('target_content_id')->nullable()->constrained('contents')->nullOnDelete();
            $table->string('source_locale', 10);
            $table->string('target_locale', 10);
            $table->string('status', 20)->default('pending'); // pending, processing, completed, failed
            $table->foreignId('persona_id')->nullable()->constrained()->nullOnDelete();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['space_id', 'status']);
            $table->index(['source_content_id', 'target_locale']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_translation_jobs');
    }
};
