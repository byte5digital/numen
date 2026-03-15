<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('repurposing_batches')) {
            Schema::create('repurposing_batches', function (Blueprint $table) {
                $table->id();
                $table->string('space_id', 26)->index();
                $table->string('format_key', 50);
                $table->string('status', 20)->default('pending'); // pending, processing, completed, failed, cancelled
                $table->unsignedInteger('total_items')->default(0);
                $table->unsignedInteger('completed_items')->default(0);
                $table->unsignedInteger('failed_items')->default(0);
                $table->unsignedInteger('total_tokens_used')->nullable();
                $table->string('persona_id', 26)->nullable();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('repurposing_batches');
    }
};
