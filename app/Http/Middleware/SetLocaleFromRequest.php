<?php

namespace App\Http\Middleware;

use App\Models\Space;
use App\Services\LocaleService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocaleFromRequest
{
    public function __construct(
        private readonly LocaleService $localeService,
    ) {}

    /**
     * Detect the requested locale from the incoming request and configure the application.
     *
     * Detection order:
     *   1. ?locale= query parameter
     *   2. X-Locale request header
     *   3. Accept-Language header (first tag only)
     *   4. Space default locale (resolved via LocaleService)
     *
     * After detection the locale is validated against the space's active locales
     * using LocaleService::resolveLocale() so an unsupported value gracefully
     * falls back through the configured chain.
     *
     * The resolved locale is stored as:
     *   - app()->setLocale()
     *   - $request->attributes->set('locale', ...)
     */
    public function handle(Request $request, Closure $next): Response
    {
        $requested = $this->detectRequested($request);
        $resolved = $this->resolveForRequest($request, $requested);

        app()->setLocale($resolved);
        $request->attributes->set('locale', $resolved);

        return $next($request);
    }

    /**
     * Extract a raw locale string from query param, header, or Accept-Language.
     * Returns null when nothing is specified.
     */
    private function detectRequested(Request $request): ?string
    {
        // 1. Explicit query parameter
        if ($request->query->has('locale')) {
            $locale = (string) $request->query->get('locale');

            if ($locale !== '') {
                return $locale;
            }
        }

        // 2. X-Locale header
        $xLocale = $request->header('X-Locale');

        if (is_string($xLocale) && $xLocale !== '') {
            return $xLocale;
        }

        // 3. Accept-Language header (use first tag, strip quality values)
        $acceptLanguage = $request->header('Accept-Language');

        if (is_string($acceptLanguage) && $acceptLanguage !== '') {
            $parsed = $this->parseAcceptLanguage($acceptLanguage);

            if ($parsed !== null) {
                return $parsed;
            }
        }

        return null;
    }

    /**
     * Resolve the final locale for the request, taking space configuration into account.
     *
     * When the request carries a space binding, LocaleService::resolveLocale() validates
     * and falls back through the configured chain. Without a space, the requested value
     * is used as-is (falling back to config('app.locale')).
     */
    private function resolveForRequest(Request $request, ?string $requested): string
    {
        /** @var Space|null $space */
        $space = $request->route()?->parameter('space');

        if (! $space instanceof Space) {
            // Try to find a space from the request attributes (set by earlier middleware)
            $space = $request->attributes->get('space');
        }

        if ($space instanceof Space) {
            return $this->localeService->resolveLocale(
                $space,
                $requested ?? config('app.locale', 'en'),
            );
        }

        // No space context — honour the detected value or fall back to app default
        return $requested ?? config('app.locale', 'en');
    }

    /**
     * Parse the first locale tag from an Accept-Language header value.
     *
     * Example: "fr-CH, fr;q=0.9, en;q=0.8" → "fr-CH"
     */
    private function parseAcceptLanguage(string $header): ?string
    {
        $parts = explode(',', $header);

        if (empty($parts)) {
            return null;
        }

        // Strip quality value from the first tag
        $first = trim(explode(';', $parts[0])[0]);

        // Normalise separator: Accept-Language uses underscore in some clients
        $first = str_replace('_', '-', $first);

        return $first !== '' ? $first : null;
    }
}
