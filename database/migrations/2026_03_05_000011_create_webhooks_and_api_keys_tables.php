<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhooks', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('space_id')->index();
            $table->string('url');
            $table->json('events');
            $table->string('secret');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('space_id')->references('id')->on('spaces')->cascadeOnDelete();
        });

        Schema::create('api_keys', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('space_id')->index();
            $table->string('name');
            $table->string('key_hash')->unique();
            $table->json('scopes');
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->foreign('space_id')->references('id')->on('spaces')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_keys');
        Schema::dropIfExists('webhooks');
    }
};
