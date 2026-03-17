<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('migration_type_mappings')) {
            Schema::create('migration_type_mappings', function (Blueprint $table) {
                $table->char('id', 26)->primary();
                $table->string('migration_session_id', 26)->index();
                $table->string('space_id', 26)->index();
                $table->string('source_type_key', 255);
                $table->string('source_type_label', 255)->nullable();
                $table->string('numen_content_type_id', 26)->nullable()->index();
                $table->string('numen_type_slug', 255)->nullable();
                $table->json('field_map');
                $table->json('ai_suggestions')->nullable();
                $table->enum('status', ['pending', 'approved', 'confirmed', 'skipped'])->default('pending');
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('migration_type_mappings');
    }
};
