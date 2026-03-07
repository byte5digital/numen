<?php

namespace App\Services\Search;

use App\Models\SearchSynonym;
use Illuminate\Support\Facades\Log;

/**
 * Syncs search synonyms from the database to Meilisearch.
 * Called after any synonym create/update/delete operation.
 */
class SynonymSyncService
{
    public function syncToMeilisearch(string $spaceId): void
    {
        try {
            $synonyms = SearchSynonym::where('space_id', $spaceId)
                ->where('approved', true)
                ->get();

            /** @var array<string, string[]> $meilisearchSynonyms */
            $meilisearchSynonyms = [];

            foreach ($synonyms as $synonym) {
                /** @var array<int, string> $allTerms */
                $allTerms = array_merge([$synonym->term], $synonym->synonyms ?? []);

                if ($synonym->is_one_way) {
                    // One-way: "JS" → "JavaScript" but not reverse
                    foreach ($synonym->synonyms ?? [] as $syn) {
                        $meilisearchSynonyms[$syn] = [$synonym->term];
                    }
                } else {
                    // Two-way: all terms are interchangeable
                    foreach ($allTerms as $term) {
                        $meilisearchSynonyms[$term] = array_values(
                            array_diff($allTerms, [$term])
                        );
                    }
                }
            }

            $host = config('numen.search.meilisearch_host', 'http://127.0.0.1:7700');
            $key = config('numen.search.meilisearch_key');

            $client = new \Meilisearch\Client((string) $host, $key ? (string) $key : null);
            $client->index('contents')->updateSynonyms($meilisearchSynonyms);

            Log::info('SynonymSyncService: synced synonyms to Meilisearch', [
                'space_id' => $spaceId,
                'count' => count($meilisearchSynonyms),
            ]);

        } catch (\Throwable $e) {
            Log::warning('SynonymSyncService: failed to sync', [
                'space_id' => $spaceId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
