<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('migration_items')) {
            Schema::create('migration_items', function (Blueprint $table) {
                $table->char('id', 26)->primary();
                $table->string('migration_session_id', 26)->index();
                $table->string('space_id', 26)->index();
                $table->string('source_type_key', 255);
                $table->string('source_id', 255);
                $table->char('source_hash', 64)->nullable();
                $table->string('numen_content_id', 26)->nullable()->index();
                $table->json('numen_media_ids')->nullable();
                $table->enum('status', [
                    'pending', 'processing', 'imported', 'failed', 'skipped', 'rolled_back',
                ])->default('pending');
                $table->text('error_message')->nullable();
                $table->unsignedTinyInteger('attempt')->default(0);
                $table->longText('source_payload')->nullable();
                $table->timestamps();

                $table->unique(['migration_session_id', 'source_type_key', 'source_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('migration_items');
    }
};
