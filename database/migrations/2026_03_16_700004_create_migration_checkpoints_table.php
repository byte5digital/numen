<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('migration_checkpoints')) {
            Schema::create('migration_checkpoints', function (Blueprint $table) {
                $table->char('id', 26)->primary();
                $table->string('migration_session_id', 26)->index();
                $table->string('space_id', 26)->index();
                $table->string('source_type_key', 255);
                $table->string('last_cursor', 255);
                $table->timestamp('last_synced_at')->nullable();
                $table->unsignedInteger('item_count')->default(0);
                $table->timestamps();

                $table->unique(['migration_session_id', 'source_type_key']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('migration_checkpoints');
    }
};
