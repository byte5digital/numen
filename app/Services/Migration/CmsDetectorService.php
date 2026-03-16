<?php

declare(strict_types=1);

namespace App\Services\Migration;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CmsDetectorService
{
    private const TIMEOUT = 5;

    /**
     * Probe a URL and detect which CMS is running.
     *
     * @return array{cms: string, version: ?string, confidence: float}
     */
    public function detect(string $url): array
    {
        $url = rtrim($url, '/');

        $detectors = [
            'wordpress' => fn () => $this->detectWordPress($url),
            'strapi' => fn () => $this->detectStrapi($url),
            'payload' => fn () => $this->detectPayload($url),
            'contentful' => fn () => $this->detectContentful($url),
            'ghost' => fn () => $this->detectGhost($url),
            'directus' => fn () => $this->detectDirectus($url),
        ];

        $best = ['cms' => 'unknown', 'version' => null, 'confidence' => 0.0];

        foreach ($detectors as $cms => $detector) {
            try {
                $result = $detector();
                if ($result['confidence'] > $best['confidence']) {
                    $best = $result;
                }
            } catch (\Throwable $e) {
                Log::debug("CmsDetectorService: {$cms} probe failed", ['error' => $e->getMessage()]);
            }
        }

        return $best;
    }

    /**
     * @return array{cms: string, version: ?string, confidence: float}
     */
    private function detectWordPress(string $url): array
    {
        try {
            $response = Http::timeout(self::TIMEOUT)->get("{$url}/wp-json/wp/v2");

            if ($response->successful()) {
                $data = $response->json();
                $version = $data['generator'] ?? null;

                if (is_string($version) && str_starts_with($version, 'https://wordpress.org')) {
                    // Extract version from generator string e.g. https://wordpress.org/?v=6.4
                    preg_match('/\?v=([\d.]+)/', $version, $matches);
                    $version = $matches[1] ?? null;
                }

                return ['cms' => 'wordpress', 'version' => $version, 'confidence' => 0.95];
            }

            // Fallback: check /wp-login.php header
            $loginResponse = Http::timeout(self::TIMEOUT)->head("{$url}/wp-login.php");
            if ($loginResponse->status() === 200 || $loginResponse->status() === 302) {
                return ['cms' => 'wordpress', 'version' => null, 'confidence' => 0.7];
            }
        } catch (ConnectionException $e) {
            Log::debug('CmsDetectorService: WordPress probe connection failed', ['error' => $e->getMessage()]);
        }

        return ['cms' => 'wordpress', 'version' => null, 'confidence' => 0.0];
    }

    /**
     * @return array{cms: string, version: ?string, confidence: float}
     */
    private function detectStrapi(string $url): array
    {
        try {
            $response = Http::timeout(self::TIMEOUT)->get("{$url}/api");

            if ($response->successful()) {
                $data = $response->json();
                if (isset($data['data']) || isset($data['error'])) {
                    return ['cms' => 'strapi', 'version' => null, 'confidence' => 0.8];
                }
            }

            // Check Strapi health endpoint
            $health = Http::timeout(self::TIMEOUT)->get("{$url}/_health");
            if ($health->status() === 204 || $health->status() === 200) {
                return ['cms' => 'strapi', 'version' => null, 'confidence' => 0.75];
            }
        } catch (ConnectionException $e) {
            Log::debug('CmsDetectorService: Strapi probe connection failed', ['error' => $e->getMessage()]);
        }

        return ['cms' => 'strapi', 'version' => null, 'confidence' => 0.0];
    }

    /**
     * @return array{cms: string, version: ?string, confidence: float}
     */
    private function detectPayload(string $url): array
    {
        try {
            $response = Http::timeout(self::TIMEOUT)->get("{$url}/api");

            if ($response->successful()) {
                $body = $response->body();
                if (str_contains($body, 'payload') || str_contains($body, 'Payload')) {
                    return ['cms' => 'payload', 'version' => null, 'confidence' => 0.7];
                }
            }

            // Check Payload CMS admin panel
            $admin = Http::timeout(self::TIMEOUT)->get("{$url}/admin");
            if ($admin->successful() && str_contains($admin->body(), 'Payload')) {
                return ['cms' => 'payload', 'version' => null, 'confidence' => 0.85];
            }
        } catch (ConnectionException $e) {
            Log::debug('CmsDetectorService: Payload probe connection failed', ['error' => $e->getMessage()]);
        }

        return ['cms' => 'payload', 'version' => null, 'confidence' => 0.0];
    }

    /**
     * @return array{cms: string, version: ?string, confidence: float}
     */
    private function detectContentful(string $url): array
    {
        try {
            // Contentful is SaaS — detect by checking known API domain pattern
            if (str_contains($url, 'contentful.com') || str_contains($url, 'cdn.contentful.com')) {
                return ['cms' => 'contentful', 'version' => null, 'confidence' => 0.99];
            }

            $response = Http::timeout(self::TIMEOUT)->get($url);
            if ($response->successful()) {
                $headers = $response->headers();
                $body = $response->body();

                if (
                    isset($headers['x-contentful-request-id']) ||
                    str_contains($body, 'contentful')
                ) {
                    return ['cms' => 'contentful', 'version' => null, 'confidence' => 0.85];
                }
            }
        } catch (ConnectionException $e) {
            Log::debug('CmsDetectorService: Contentful probe connection failed', ['error' => $e->getMessage()]);
        }

        return ['cms' => 'contentful', 'version' => null, 'confidence' => 0.0];
    }

    /**
     * @return array{cms: string, version: ?string, confidence: float}
     */
    private function detectGhost(string $url): array
    {
        try {
            $response = Http::timeout(self::TIMEOUT)->get("{$url}/ghost/api/content/posts");

            if ($response->successful()) {
                $data = $response->json();
                if (isset($data['posts']) || isset($data['errors'])) {
                    return ['cms' => 'ghost', 'version' => null, 'confidence' => 0.9];
                }
            }

            // Check Ghost admin API
            $admin = Http::timeout(self::TIMEOUT)->get("{$url}/ghost/api/admin");
            if ($admin->status() === 401 || $admin->status() === 403) {
                return ['cms' => 'ghost', 'version' => null, 'confidence' => 0.8];
            }
        } catch (ConnectionException $e) {
            Log::debug('CmsDetectorService: Ghost probe connection failed', ['error' => $e->getMessage()]);
        }

        return ['cms' => 'ghost', 'version' => null, 'confidence' => 0.0];
    }

    /**
     * @return array{cms: string, version: ?string, confidence: float}
     */
    private function detectDirectus(string $url): array
    {
        try {
            $response = Http::timeout(self::TIMEOUT)->get("{$url}/server/info");

            if ($response->successful()) {
                $data = $response->json();
                if (isset($data['data']['version'])) {
                    return ['cms' => 'directus', 'version' => $data['data']['version'], 'confidence' => 0.95];
                }
            }

            // Check Directus ping
            $ping = Http::timeout(self::TIMEOUT)->get("{$url}/server/ping");
            if ($ping->body() === 'pong') {
                return ['cms' => 'directus', 'version' => null, 'confidence' => 0.9];
            }
        } catch (ConnectionException $e) {
            Log::debug('CmsDetectorService: Directus probe connection failed', ['error' => $e->getMessage()]);
        }

        return ['cms' => 'directus', 'version' => null, 'confidence' => 0.0];
    }
}
