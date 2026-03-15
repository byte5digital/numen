<?php

namespace App\Services;

use App\Models\Content;
use App\Models\Space;
use App\Models\SpaceLocale;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class LocaleService
{
    private const SUPPORTED_LOCALES = [
        'af' => 'Afrikaans', 'ar' => 'Arabic', 'az' => 'Azerbaijani',
        'be' => 'Belarusian', 'bg' => 'Bulgarian', 'bn' => 'Bengali',
        'bs' => 'Bosnian', 'ca' => 'Catalan', 'cs' => 'Czech',
        'cy' => 'Welsh', 'da' => 'Danish', 'de' => 'German',
        'de-AT' => 'German (Austria)', 'de-CH' => 'German (Switzerland)',
        'el' => 'Greek', 'en' => 'English', 'en-AU' => 'English (Australia)',
        'en-CA' => 'English (Canada)', 'en-GB' => 'English (United Kingdom)',
        'en-US' => 'English (United States)', 'eo' => 'Esperanto',
        'es' => 'Spanish', 'es-419' => 'Spanish (Latin America)',
        'es-AR' => 'Spanish (Argentina)', 'es-MX' => 'Spanish (Mexico)',
        'et' => 'Estonian', 'eu' => 'Basque', 'fa' => 'Persian',
        'fi' => 'Finnish', 'fil' => 'Filipino', 'fr' => 'French',
        'fr-BE' => 'French (Belgium)', 'fr-CA' => 'French (Canada)',
        'fr-CH' => 'French (Switzerland)', 'ga' => 'Irish',
        'gl' => 'Galician', 'gu' => 'Gujarati', 'he' => 'Hebrew',
        'hi' => 'Hindi', 'hr' => 'Croatian', 'hu' => 'Hungarian',
        'hy' => 'Armenian', 'id' => 'Indonesian', 'is' => 'Icelandic',
        'it' => 'Italian', 'it-CH' => 'Italian (Switzerland)', 'ja' => 'Japanese',
        'ka' => 'Georgian', 'kk' => 'Kazakh', 'km' => 'Khmer',
        'kn' => 'Kannada', 'ko' => 'Korean', 'ky' => 'Kyrgyz',
        'lo' => 'Lao', 'lt' => 'Lithuanian', 'lv' => 'Latvian',
        'mk' => 'Macedonian', 'ml' => 'Malayalam', 'mn' => 'Mongolian',
        'mr' => 'Marathi', 'ms' => 'Malay', 'mt' => 'Maltese',
        'my' => 'Burmese', 'nb' => 'Norwegian Bokmål', 'ne' => 'Nepali',
        'nl' => 'Dutch', 'nl-BE' => 'Dutch (Belgium)', 'nn' => 'Norwegian Nynorsk',
        'pa' => 'Punjabi', 'pl' => 'Polish', 'pt' => 'Portuguese',
        'pt-BR' => 'Portuguese (Brazil)', 'pt-PT' => 'Portuguese (Portugal)',
        'ro' => 'Romanian', 'ru' => 'Russian', 'si' => 'Sinhala',
        'sk' => 'Slovak', 'sl' => 'Slovenian', 'sq' => 'Albanian',
        'sr' => 'Serbian', 'sv' => 'Swedish', 'sw' => 'Swahili',
        'ta' => 'Tamil', 'te' => 'Telugu', 'th' => 'Thai',
        'tr' => 'Turkish', 'uk' => 'Ukrainian', 'ur' => 'Urdu',
        'uz' => 'Uzbek', 'vi' => 'Vietnamese', 'zh' => 'Chinese',
        'zh-CN' => 'Chinese (Simplified)', 'zh-HK' => 'Chinese (Hong Kong)',
        'zh-TW' => 'Chinese (Traditional)', 'zu' => 'Zulu',
    ];

    /**
     * Get all active locales configured for a space.
     *
     * @return Collection<int, SpaceLocale>
     */
    public function getLocalesForSpace(Space $space): Collection
    {
        return SpaceLocale::where('space_id', $space->id)
            ->active()
            ->orderBy('sort_order')
            ->orderBy('locale')
            ->get();
    }

    /**
     * Get the default locale for a space, or null if none is configured.
     */
    public function getDefaultLocale(Space $space): ?SpaceLocale
    {
        return SpaceLocale::where('space_id', $space->id)
            ->active()
            ->default()
            ->first();
    }

    /**
     * Add a new locale to the space.
     *
     * If $isDefault is true, the existing default locale is unset first.
     *
     * @throws \InvalidArgumentException When the locale code is not in the supported list.
     */
    public function addLocale(
        Space $space,
        string $locale,
        string $label,
        bool $isDefault = false,
    ): SpaceLocale {
        if (! array_key_exists($locale, self::SUPPORTED_LOCALES)) {
            throw new \InvalidArgumentException("Locale '{$locale}' is not supported.");
        }

        return DB::transaction(function () use ($space, $locale, $label, $isDefault): SpaceLocale {
            if ($isDefault) {
                SpaceLocale::where('space_id', $space->id)
                    ->where('is_default', true)
                    ->update(['is_default' => false]);
            }

            return SpaceLocale::firstOrCreate(
                ['space_id' => $space->id, 'locale' => $locale],
                [
                    'label' => $label,
                    'is_default' => $isDefault,
                    'is_active' => true,
                    'sort_order' => $this->nextSortOrder($space),
                ],
            );
        });
    }

    /**
     * Set the given locale as the default for a space, unsetting all others.
     *
     * @throws \RuntimeException When the locale is not active for the space.
     */
    public function setDefaultLocale(Space $space, string $locale): void
    {
        DB::transaction(function () use ($space, $locale): void {
            $target = SpaceLocale::where('space_id', $space->id)
                ->where('locale', $locale)
                ->where('is_active', true)
                ->first();

            if (! $target) {
                throw new \RuntimeException(
                    "Locale '{$locale}' is not active for space #{$space->id}.",
                );
            }

            SpaceLocale::where('space_id', $space->id)
                ->where('is_default', true)
                ->update(['is_default' => false]);

            $target->update(['is_default' => true]);
        });
    }

    /**
     * Remove a locale from a space.
     *
     * Soft check — refuses removal when content still exists in that locale.
     *
     * @throws \RuntimeException When content exists in the locale or it is the only active locale.
     */
    public function removeLocale(Space $space, string $locale): void
    {
        $contentExists = Content::where('space_id', $space->id)
            ->where('locale', $locale)
            ->whereNull('deleted_at')
            ->exists();

        if ($contentExists) {
            throw new \RuntimeException(
                "Cannot remove locale '{$locale}': content still exists in that locale for space #{$space->id}.",
            );
        }

        $activeCount = SpaceLocale::where('space_id', $space->id)->active()->count();

        if ($activeCount <= 1) {
            throw new \RuntimeException(
                "Cannot remove the only active locale for space #{$space->id}.",
            );
        }

        DB::transaction(function () use ($space, $locale): void {
            $record = SpaceLocale::where('space_id', $space->id)
                ->where('locale', $locale)
                ->first();

            if (! $record) {
                return;
            }

            $wasDefault = $record->is_default;
            $record->delete();

            if ($wasDefault) {
                SpaceLocale::where('space_id', $space->id)
                    ->active()
                    ->orderBy('sort_order')
                    ->first()
                    ?->update(['is_default' => true]);
            }
        });
    }

    /**
     * Check whether a given locale is active for a space.
     */
    public function isLocaleActive(Space $space, string $locale): bool
    {
        return SpaceLocale::where('space_id', $space->id)
            ->where('locale', $locale)
            ->where('is_active', true)
            ->exists();
    }

    /**
     * Return the full list of IETF locales supported by this application.
     *
     * @return array<string, string> locale code => human-readable label
     */
    public function getSupportedLocales(): array
    {
        return self::SUPPORTED_LOCALES;
    }

    /**
     * Resolve the best matching locale for a space given a requested code.
     *
     * Fallback chain:
     *   1. Exact match (e.g. "fr-CA")
     *   2. Language-prefix match (e.g. "fr" when "fr-CA" is not active)
     *   3. SpaceLocale.fallback_locale, if set on any active locale
     *   4. Space default locale
     *   5. "en" (hard fallback)
     */
    public function resolveLocale(Space $space, string $requested): string
    {
        $active = $this->getLocalesForSpace($space);

        // 1. Exact match
        if ($active->firstWhere('locale', $requested)) {
            return $requested;
        }

        // 2. Language prefix (strip region subtag)
        $lang = explode('-', $requested)[0];

        $prefixMatch = $active->first(
            fn (SpaceLocale $sl) => explode('-', $sl->locale)[0] === $lang,
        );

        if ($prefixMatch) {
            return $prefixMatch->locale;
        }

        // 3. Configured fallback on any active locale
        $fallbackLocale = $active->first(
            fn (SpaceLocale $sl) => (bool) $sl->fallback_locale,
        )?->fallback_locale;

        if ($fallbackLocale && $active->firstWhere('locale', $fallbackLocale)) {
            return $fallbackLocale;
        }

        // 4. Space default
        $default = $this->getDefaultLocale($space);

        if ($default) {
            return $default->locale;
        }

        // 5. Hard fallback
        return 'en';
    }

    /**
     * Determine the next available sort_order for a space's locales.
     */
    private function nextSortOrder(Space $space): int
    {
        $max = SpaceLocale::where('space_id', $space->id)->max('sort_order');

        return is_null($max) ? 0 : $max + 1;
    }
}
