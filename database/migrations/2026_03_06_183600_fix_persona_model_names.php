<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Fix incorrect Anthropic model identifiers in persona model_config.
     */
    public function up(): void
    {
        $replacements = [
            'claude-sonnet-4-6' => 'claude-sonnet-4-20250514',
            'claude-opus-4-6' => 'claude-opus-4-20250514',
            'claude-haiku-4-5-20251001' => 'claude-haiku-3-5-20241022',
        ];

        $personas = DB::table('personas')->get();

        foreach ($personas as $persona) {
            $config = json_decode($persona->model_config, true);
            if (! $config) {
                continue;
            }

            $changed = false;
            foreach ($replacements as $old => $new) {
                foreach (['model', 'primary_model', 'fallback_model'] as $key) {
                    if (isset($config[$key]) && $config[$key] === $old) {
                        $config[$key] = $new;
                        $changed = true;
                    }
                }
            }

            if ($changed) {
                DB::table('personas')
                    ->where('id', $persona->id)
                    ->update(['model_config' => json_encode($config)]);
            }
        }
    }

    public function down(): void
    {
        // Intentionally left empty — original model names were incorrect
    }
};
