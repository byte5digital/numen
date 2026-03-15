<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_deliveries', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->ulid('webhook_id');
            $table->foreign('webhook_id')->references('id')->on('webhooks')->cascadeOnDelete();

            // ULID of the event that triggered this delivery
            $table->ulid('event_id');

            // e.g. "content.published", "pipeline.completed"
            $table->string('event_type', 64);

            // SHA256 of payload — used for deduplication
            $table->string('payload_hash', 64)->nullable();

            $table->unsignedTinyInteger('attempt_number')->default(1);

            // pending | delivered | failed | abandoned
            $table->string('status', 32);

            $table->unsignedSmallInteger('http_status')->nullable();
            $table->longText('response_body')->nullable();
            $table->text('error_message')->nullable();

            // When the delivery is scheduled to be (re)attempted
            $table->timestamp('scheduled_at')->nullable();

            // When the delivery was successfully received
            $table->timestamp('delivered_at')->nullable();

            // Immutable log — only created_at, no updated_at
            $table->timestamp('created_at')->useCurrent();

            // Indexes for efficient querying
            $table->index(['webhook_id', 'status']);
            $table->index('event_id');
            $table->index('scheduled_at');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_deliveries');
    }
};
