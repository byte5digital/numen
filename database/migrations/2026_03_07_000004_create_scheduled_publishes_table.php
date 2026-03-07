<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scheduled_publishes', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('content_id')->index();
            $table->ulid('version_id');
            $table->ulid('scheduled_by'); // user who scheduled it

            $table->timestamp('publish_at');
            $table->string('status')->default('pending'); // pending | published | cancelled | failed
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->foreign('content_id')->references('id')->on('contents')->cascadeOnDelete();
            $table->foreign('version_id')->references('id')->on('content_versions')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scheduled_publishes');
    }
};
