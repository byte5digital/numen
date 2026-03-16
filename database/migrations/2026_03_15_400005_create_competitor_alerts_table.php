<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('competitor_alerts')) {
            Schema::create('competitor_alerts', function (Blueprint $table) {
                $table->ulid('id')->primary();
                $table->string('space_id', 26)->index();
                $table->string('name');
                $table->enum('type', ['new_competitor_content', 'high_similarity', 'topic_overlap']);
                $table->json('conditions')->nullable();
                $table->boolean('is_active')->default(true);
                $table->json('notify_channels')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('competitor_alerts');
    }
};
