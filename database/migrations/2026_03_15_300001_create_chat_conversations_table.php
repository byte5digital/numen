<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('chat_conversations')) {
            Schema::create('chat_conversations', function (Blueprint $table) {
                $table->char('id', 26)->primary();
                $table->string('space_id', 26)->index();
                $table->unsignedBigInteger('user_id')->index();
                $table->string('title', 200)->nullable();
                $table->json('context')->nullable();
                $table->json('pending_action')->nullable();
                $table->timestamp('last_active_at')->nullable();
                $table->timestamps();

                $table->index(['space_id', 'user_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_conversations');
    }
};
