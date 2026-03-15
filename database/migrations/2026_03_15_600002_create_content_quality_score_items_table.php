<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('content_quality_score_items')) {
            Schema::create('content_quality_score_items', function (Blueprint $table) {
                $table->ulid('id')->primary();
                $table->string('score_id', 26)->index();
                $table->string('dimension');
                $table->string('category');
                $table->string('rule_key');
                $table->string('label');
                $table->enum('severity', ['info', 'warning', 'error']);
                $table->decimal('score_impact', 5, 2);
                $table->text('message');
                $table->text('suggestion')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('content_quality_score_items');
    }
};
