<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('content_fingerprints')) {
            Schema::create('content_fingerprints', function (Blueprint $table) {
                $table->ulid('id')->primary();
                $table->string('fingerprintable_type');
                $table->string('fingerprintable_id', 26);
                $table->json('topics')->nullable();
                $table->json('entities')->nullable();
                $table->json('keywords')->nullable();
                $table->text('embedding_vector')->nullable();
                $table->timestamp('fingerprinted_at')->nullable();
                $table->timestamps();

                $table->index(['fingerprintable_type', 'fingerprintable_id'], 'fingerprints_morphable_index');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('content_fingerprints');
    }
};
