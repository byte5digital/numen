<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('migration_sessions')) {
            Schema::create('migration_sessions', function (Blueprint $table) {
                $table->char('id', 26)->primary();
                $table->string('space_id', 26)->index();
                $table->string('created_by', 26)->index();
                $table->string('name', 255);
                $table->string('source_cms', 50);
                $table->string('source_url', 2048);
                $table->string('source_version', 20)->nullable();
                $table->text('credentials')->nullable();
                $table->enum('status', [
                    'pending', 'detecting', 'mapping', 'preview',
                    'running', 'paused', 'completed', 'failed', 'rolled_back',
                ])->default('pending');
                $table->unsignedInteger('total_items')->default(0);
                $table->unsignedInteger('processed_items')->default(0);
                $table->unsignedInteger('failed_items')->default(0);
                $table->unsignedInteger('skipped_items')->default(0);
                $table->json('options')->nullable();
                $table->text('error_message')->nullable();
                $table->json('schema_snapshot')->nullable();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('migration_sessions');
    }
};
