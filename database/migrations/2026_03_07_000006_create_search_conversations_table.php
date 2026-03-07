<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('search_conversations', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('space_id');
            $table->string('session_id', 64);
            $table->json('messages');
            $table->timestamps();
            $table->timestamp('expires_at');

            $table->foreign('space_id')->references('id')->on('spaces')->cascadeOnDelete();
            $table->index('session_id');
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('search_conversations');
    }
};
