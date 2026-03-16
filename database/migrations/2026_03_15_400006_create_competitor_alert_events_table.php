<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('competitor_alert_events')) {
            Schema::create('competitor_alert_events', function (Blueprint $table) {
                $table->ulid('id')->primary();
                $table->string('alert_id', 26)->index();
                $table->string('competitor_content_id', 26)->index();
                $table->json('trigger_data')->nullable();
                $table->timestamp('notified_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('competitor_alert_events');
    }
};
