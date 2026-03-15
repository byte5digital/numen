<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('repurposed_contents')) {
            Schema::create('repurposed_contents', function (Blueprint $table) {
                $table->id();
                $table->string('space_id', 26)->index();
                $table->string('source_content_id', 26)->index();
                $table->unsignedBigInteger('format_template_id')->nullable();
                $table->string('format_key', 50);
                $table->string('status', 20)->default('pending'); // pending, processing, completed, failed
                $table->longText('output')->nullable(); // the repurposed content
                $table->json('output_parts')->nullable(); // for threaded formats (twitter threads)
                $table->unsignedInteger('tokens_used')->nullable();
                $table->string('persona_id', 26)->nullable();
                $table->text('error_message')->nullable();
                $table->timestamp('repurposed_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('repurposed_contents');
    }
};
