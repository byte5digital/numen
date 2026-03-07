<?php

namespace App\Services\Search;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SearchCapabilityDetector
{
    public function detect(): SearchCapabilities
    {
        return new SearchCapabilities(
            instant: $this->isMeilisearchAvailable(),
            semantic: $this->isPgvectorAvailable(),
            ask: $this->isPgvectorAvailable() && $this->isLLMAvailable(),
        );
    }

    private function isMeilisearchAvailable(): bool
    {
        /** @var bool */
        return Cache::remember('search:meilisearch:health', 30, function (): bool {
            try {
                $host = config('numen.search.meilisearch_host', 'http://127.0.0.1:7700');
                $key = config('numen.search.meilisearch_key');

                $client = new \Meilisearch\Client((string) $host, $key ? (string) $key : null);
                $client->health();

                return true;
            } catch (\Throwable $e) {
                Log::debug('Meilisearch health check failed', ['error' => $e->getMessage()]);

                return false;
            }
        });
    }

    private function isPgvectorAvailable(): bool
    {
        /** @var bool */
        return Cache::remember('search:pgvector:available', 300, function (): bool {
            try {
                if (DB::getDriverName() !== 'pgsql') {
                    return false;
                }

                $result = DB::select("SELECT 1 FROM pg_extension WHERE extname = 'vector'");

                return $result !== [];
            } catch (\Throwable $e) {
                Log::debug('pgvector availability check failed', ['error' => $e->getMessage()]);

                return false;
            }
        });
    }

    private function isLLMAvailable(): bool
    {
        $apiKey = config('numen.providers.anthropic.api_key')
            ?? config('numen.providers.openai.api_key');

        return ! empty($apiKey);
    }
}
