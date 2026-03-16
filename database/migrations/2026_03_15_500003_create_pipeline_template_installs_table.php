<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pipeline_template_installs')) {
            Schema::create('pipeline_template_installs', function (Blueprint $table) {
                $table->ulid('id')->primary();
                $table->string('template_id', 26)->index();
                $table->string('version_id', 26)->index();
                $table->string('space_id', 26)->index();
                $table->string('pipeline_id', 26)->nullable()->index();
                $table->timestamp('installed_at')->useCurrent();
                $table->json('config_overrides')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('pipeline_template_installs');
    }
};
