<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('space_locales')) {
            Schema::create('space_locales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('space_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 10); // e.g. en, fr, de, zh-TW
            $table->string('label', 100); // e.g. "English", "Français"
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->string('fallback_locale', 10)->nullable(); // e.g. en-AU falls back to en
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['space_id', 'locale']);
        });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('space_locales');
    }
};
