<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

class Setting extends Model
{
    protected $primaryKey = 'key';
    public $incrementing  = false;
    protected $keyType    = 'string';

    protected $fillable = ['key', 'value', 'encrypted', 'group'];

    protected $casts = [
        'encrypted' => 'boolean',
    ];

    // ── Static helpers ──────────────────────────────────────────────────────

    /**
     * Get a setting value by key, falling back to a default.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $setting = Cache::remember(
            "setting:{$key}",
            now()->addMinutes(5),
            fn () => static::find($key),
        );

        if (!$setting) return $default;

        $value = $setting->encrypted
            ? static::decryptSafe($setting->value)
            : $setting->value;

        return $value ?? $default;
    }

    /**
     * Set a setting value. Automatically encrypts API keys.
     */
    public static function set(string $key, mixed $value, string $group = 'general'): void
    {
        $shouldEncrypt = static::shouldEncrypt($key);

        static::updateOrCreate(
            ['key' => $key],
            [
                'value'     => $shouldEncrypt && $value ? Crypt::encryptString((string) $value) : (string) $value,
                'encrypted' => $shouldEncrypt && !empty($value),
                'group'     => $group,
            ],
        );

        Cache::forget("setting:{$key}");
        Cache::forget('settings:all');
    }

    /**
     * Bulk-set multiple settings at once.
     */
    public static function setMany(array $settings, string $group = 'general'): void
    {
        foreach ($settings as $key => $value) {
            static::set($key, $value, $group);
        }
    }

    /**
     * Load all settings from the database and push them into config.
     * Called from AppServiceProvider::boot().
     */
    public static function loadIntoConfig(): void
    {
        try {
            $rows = Cache::remember('settings:all', now()->addMinutes(5), fn () => static::all());
        } catch (\Exception $e) {
            // Table may not exist yet (before migration)
            return;
        }

        foreach ($rows as $setting) {
            $value = $setting->encrypted
                ? static::decryptSafe($setting->value)
                : $setting->value;

            if ($value === null) continue;

            // Map setting key → config key
            // e.g. "ai.providers.anthropic.api_key" → config('numen.providers.anthropic.api_key')
            // Strip only the leading "ai." prefix (str_replace would corrupt keys like "openai.api_key")
            $configKey = 'numen.' . (str_starts_with($setting->key, 'ai.')
                ? substr($setting->key, 3)
                : $setting->key);

            // fallback_chain is stored as comma-separated string but must be an array in config
            if ($configKey === 'numen.fallback_chain') {
                $value = array_filter(array_map('trim', explode(',', $value)));
            }

            // Only override config if the DB value is non-empty.
            // This prevents saved empty strings from blanking out .env values.
            if ($value !== '' && $value !== null) {
                config([$configKey => $value]);
            }
        }
    }

    /**
     * Get all settings grouped, masking encrypted values.
     * Used for the admin UI.
     */
    public static function allGrouped(): array
    {
        $rows = static::all()->groupBy('group');

        return $rows->map(function ($items) {
            return $items->mapWithKeys(function ($setting) {
                $value = $setting->encrypted
                    ? (empty($setting->value) ? '' : '••••••••')  // Masked
                    : $setting->value;

                return [$setting->key => $value];
            });
        })->toArray();
    }

    // ── Private helpers ──────────────────────────────────────────────────────

    private static function shouldEncrypt(string $key): bool
    {
        return str_contains($key, 'api_key') || str_contains($key, 'secret');
    }

    private static function decryptSafe(?string $value): ?string
    {
        if (empty($value)) return null;
        try {
            return Crypt::decryptString($value);
        } catch (\Exception) {
            return null; // Corrupted or not encrypted
        }
    }
}
