<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('format_templates')) {
            Schema::create('format_templates', function (Blueprint $table) {
                $table->id();
                $table->string('space_id', 26)->nullable()->index(); // null = global default
                $table->string('format_key', 50); // twitter_thread, linkedin_post, newsletter_section, etc.
                $table->string('name', 100);
                $table->text('description')->nullable();
                $table->text('system_prompt'); // LLM system instruction
                $table->text('user_prompt_template'); // uses {{title}}, {{body}}, {{tone}} placeholders
                $table->json('output_schema')->nullable(); // expected output structure
                $table->unsignedSmallInteger('max_tokens')->default(1000);
                $table->boolean('is_default')->default(false);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->index('format_key');
            });
        }

        // Cross-database compatible unique index guard (works on MySQL and SQLite)
        if (! Schema::hasIndex('format_templates', 'format_templates_space_id_format_key_unique')) {
            Schema::table('format_templates', function (Blueprint $table) {
                $table->unique(['space_id', 'format_key'], 'format_templates_space_id_format_key_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('format_templates');
    }
};
