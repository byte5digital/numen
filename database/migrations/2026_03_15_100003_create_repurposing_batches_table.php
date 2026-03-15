<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('repurposing_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('space_id')->constrained()->cascadeOnDelete();
            $table->string('format_key', 50);
            $table->string('status', 20)->default('pending'); // pending, processing, completed, failed, cancelled
            $table->unsignedInteger('total_items')->default(0);
            $table->unsignedInteger('completed_items')->default(0);
            $table->unsignedInteger('failed_items')->default(0);
            $table->unsignedInteger('total_tokens_used')->nullable();
            $table->foreignId('persona_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['space_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('repurposing_batches');
    }
};
