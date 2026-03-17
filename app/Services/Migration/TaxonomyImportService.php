<?php

declare(strict_types=1);

namespace App\Services\Migration;

use App\Models\Migration\MigrationSession;
use App\Models\TaxonomyTerm;
use App\Models\Vocabulary;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Imports taxonomies from a source CMS into Numen Vocabularies + TaxonomyTerms.
 *
 * Returns a mapping of source taxonomy IDs → Numen taxonomy term IDs so that
 * content items can be linked during the transform phase.
 */
class TaxonomyImportService
{
    public function __construct(
        private readonly CmsConnectorFactory $connectorFactory,
    ) {}

    /**
     * Import all taxonomies for the given migration session.
     *
     * @return Collection<string, string> source_id => numen_term_id
     */
    public function importTaxonomies(MigrationSession $session): Collection
    {
        $connector = $this->connectorFactory->make(
            $session->source_cms,
            $session->source_url,
            $session->credentials ? (is_array($session->credentials) ? $session->credentials : []) : null,
        );

        $rawTaxonomies = $connector->getTaxonomies();
        $mapping = collect();

        foreach ($rawTaxonomies as $taxonomy) {
            if (! is_array($taxonomy)) {
                continue;
            }

            $vocabMapping = $this->importVocabulary($session, $taxonomy);
            $mapping = $mapping->merge($vocabMapping);
        }

        Log::info('TaxonomyImport: completed', [
            'session_id' => $session->id,
            'mapped_count' => $mapping->count(),
        ]);

        return $mapping;
    }

    /**
     * Import a single vocabulary and its terms.
     *
     * @param  array<string, mixed>  $taxonomy
     * @return Collection<string, string>
     */
    private function importVocabulary(MigrationSession $session, array $taxonomy): Collection
    {
        $name = (string) ($taxonomy['name'] ?? $taxonomy['label'] ?? 'Untitled');
        $slug = Str::slug((string) ($taxonomy['slug'] ?? $name));
        $isHierarchical = (bool) ($taxonomy['hierarchical'] ?? false);

        $vocabulary = Vocabulary::query()
            ->where('space_id', $session->space_id)
            ->where('slug', $slug)
            ->first();

        if (! $vocabulary) {
            $vocabulary = Vocabulary::create([
                'space_id' => $session->space_id,
                'name' => $name,
                'slug' => $slug,
                'hierarchy' => $isHierarchical,
                'allow_multiple' => true,
                'sort_order' => 0,
            ]);
        }

        $terms = $taxonomy['terms'] ?? $taxonomy['items'] ?? [];
        $mapping = collect();

        if (is_array($terms)) {
            $this->importTerms($vocabulary, $terms, null, $mapping);
        }

        return $mapping;
    }

    /**
     * Recursively import terms into a vocabulary.
     *
     * @param  list<array<string, mixed>>  $terms
     * @param  Collection<string, string>  $mapping  Mutated by reference
     */
    private function importTerms(
        Vocabulary $vocabulary,
        array $terms,
        ?string $parentId,
        Collection $mapping,
    ): void {
        foreach ($terms as $termData) {
            if (! is_array($termData)) {
                continue;
            }

            $sourceId = (string) ($termData['id'] ?? $termData['term_id'] ?? '');
            $name = (string) ($termData['name'] ?? $termData['label'] ?? 'Untitled');
            $slug = Str::slug((string) ($termData['slug'] ?? $name));

            if ($slug === '') {
                continue;
            }

            // Deduplication: find existing term by slug in this vocabulary
            $existing = TaxonomyTerm::query()
                ->where('vocabulary_id', $vocabulary->id)
                ->where('slug', $slug)
                ->first();

            if ($existing) {
                if ($sourceId !== '') {
                    $mapping->put($sourceId, $existing->id);
                }

                // Still recurse into children
                $children = $termData['children'] ?? [];
                if (is_array($children) && $children !== []) {
                    $this->importTerms($vocabulary, $children, $existing->id, $mapping);
                }

                continue;
            }

            $term = TaxonomyTerm::create([
                'vocabulary_id' => $vocabulary->id,
                'parent_id' => $parentId,
                'name' => $name,
                'slug' => $slug,
                'description' => $termData['description'] ?? null,
                'sort_order' => (int) ($termData['sort_order'] ?? 0),
            ]);

            if ($sourceId !== '') {
                $mapping->put($sourceId, $term->id);
            }

            $children = $termData['children'] ?? [];
            if (is_array($children) && $children !== []) {
                $this->importTerms($vocabulary, $children, $term->id, $mapping);
            }
        }
    }
}
