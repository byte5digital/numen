<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('repurposed_contents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('space_id')->constrained()->cascadeOnDelete();
            $table->foreignId('source_content_id')->constrained('contents')->cascadeOnDelete();
            $table->foreignId('format_template_id')->nullable()->constrained()->nullOnDelete();
            $table->string('format_key', 50);
            $table->string('status', 20)->default('pending'); // pending, processing, completed, failed
            $table->longText('output')->nullable(); // the repurposed content
            $table->json('output_parts')->nullable(); // for threaded formats (twitter threads)
            $table->unsignedInteger('tokens_used')->nullable();
            $table->foreignId('persona_id')->nullable()->constrained()->nullOnDelete();
            $table->text('error_message')->nullable();
            $table->timestamp('repurposed_at')->nullable();
            $table->timestamps();
            $table->index(['source_content_id', 'format_key']);
            $table->index(['space_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('repurposed_contents');
    }
};
