<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('content_translation_jobs')) {
            Schema::create('content_translation_jobs', function (Blueprint $table) {
                $table->id();
                $table->string('space_id', 26)->index();
                $table->string('source_content_id', 26)->index();
                $table->string('target_content_id', 26)->nullable()->index();
                $table->string('source_locale', 10);
                $table->string('target_locale', 10);
                $table->string('status', 20)->default('pending'); // pending, processing, completed, failed
                $table->string('persona_id', 26)->nullable();
                $table->text('error_message')->nullable();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('content_translation_jobs');
    }
};
