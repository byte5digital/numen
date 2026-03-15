<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('plugin_settings')) {
            Schema::create('plugin_settings', function (Blueprint $table) {
                $table->string('id', 26)->primary();
                $table->string('plugin_id', 26);
                $table->string('space_id', 26)->nullable();
                $table->string('key', 255);
                $table->json('value');
                $table->boolean('is_secret')->default(false);
                $table->timestamps();

                $table->unique(['plugin_id', 'space_id', 'key']);
                $table->index('plugin_id');
                $table->index('space_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('plugin_settings');
    }
};
