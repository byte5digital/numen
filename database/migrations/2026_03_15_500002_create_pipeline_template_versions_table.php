<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pipeline_template_versions')) {
            Schema::create('pipeline_template_versions', function (Blueprint $table) {
                $table->ulid('id')->primary();
                $table->string('template_id', 26)->index();
                $table->string('version');
                $table->json('definition');
                $table->text('changelog')->nullable();
                $table->boolean('is_latest')->default(false);
                $table->timestamp('published_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('pipeline_template_versions');
    }
};
