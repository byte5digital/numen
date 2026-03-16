<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pipeline_templates')) {
            Schema::create('pipeline_templates', function (Blueprint $table) {
                $table->ulid('id')->primary();
                $table->string('space_id', 26)->nullable()->index();
                $table->string('name');
                $table->string('slug')->unique();
                $table->text('description')->nullable();
                $table->string('category')->nullable();
                $table->string('icon')->nullable();
                $table->string('schema_version')->default('1.0');
                $table->boolean('is_published')->default(false);
                $table->string('author_name')->nullable();
                $table->string('author_url')->nullable();
                $table->unsignedBigInteger('downloads_count')->default(0);
                $table->timestamps();
                $table->softDeletes();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('pipeline_templates');
    }
};
