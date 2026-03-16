<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pipeline_template_ratings')) {
            Schema::create('pipeline_template_ratings', function (Blueprint $table) {
                $table->ulid('id')->primary();
                $table->string('template_id', 26)->index();
                $table->string('user_id', 26)->index();
                $table->tinyInteger('rating')->unsigned();
                $table->text('review')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('pipeline_template_ratings');
    }
};
