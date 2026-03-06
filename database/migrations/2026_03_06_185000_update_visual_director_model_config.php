<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Update Visual Director persona model_config to use the new multi-provider schema.
 *
 * Old schema: { primary_model, primary_provider, size, style, quality, temperature, max_tokens }
 * New schema: { prompt_model, prompt_provider, generator_model, generator_provider, size, style, quality }
 *
 * The new keys map directly to ImageManager's provider resolution logic:
 *   - generator_provider + generator_model → which image generation provider/model to use
 *   - prompt_model + prompt_provider       → which LLM to use for prompt crafting
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('personas')
            ->where('role', 'illustrator')
            ->where('name', 'Visual Director')
            ->get()
            ->each(function (object $persona): void {
                /** @var array<string, mixed> $config */
                $config = json_decode($persona->model_config, associative: true) ?? [];

                $updated = [
                    // LLM used to craft the image prompt (cheap + fast)
                    'prompt_model' => $config['prompt_model'] ?? 'claude-haiku-4-5-20251001',
                    'prompt_provider' => $config['prompt_provider'] ?? 'anthropic',
                    // Image generation model
                    'generator_model' => $config['generator_model'] ?? 'gpt-image-1',
                    'generator_provider' => $config['generator_provider'] ?? 'openai',
                    // Image parameters
                    'size' => $config['size'] ?? '1792x1024',
                    'style' => $config['style'] ?? 'vivid',
                    'quality' => $config['quality'] ?? 'standard',
                ];

                DB::table('personas')
                    ->where('id', $persona->id)
                    ->update(['model_config' => json_encode($updated)]);
            });
    }

    public function down(): void
    {
        DB::table('personas')
            ->where('role', 'illustrator')
            ->where('name', 'Visual Director')
            ->get()
            ->each(function (object $persona): void {
                /** @var array<string, mixed> $config */
                $config = json_decode($persona->model_config, associative: true) ?? [];

                // Restore legacy schema
                $restored = [
                    'primary_model' => $config['generator_model'] ?? 'gpt-image-1',
                    'primary_provider' => $config['generator_provider'] ?? 'openai',
                    'size' => $config['size'] ?? '1792x1024',
                    'style' => $config['style'] ?? 'vivid',
                    'quality' => $config['quality'] ?? 'standard',
                    'temperature' => 0.8,
                    'max_tokens' => 500,
                ];

                DB::table('personas')
                    ->where('id', $persona->id)
                    ->update(['model_config' => json_encode($restored)]);
            });
    }
};
