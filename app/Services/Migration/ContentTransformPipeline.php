<?php

declare(strict_types=1);

namespace App\Services\Migration;

use App\Models\Migration\MigrationCheckpoint;
use App\Models\Migration\MigrationItem;
use App\Models\Migration\MigrationSession;
use App\Models\Migration\MigrationTypeMapping;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Orchestrates: fetch source content → transform → link taxonomies → prepare for import.
 *
 * Supports batch processing and progress tracking via MigrationCheckpoint.
 */
class ContentTransformPipeline
{
    public function __construct(
        private readonly CmsConnectorFactory $connectorFactory,
        private readonly ContentTransformerService $transformer,
        private readonly TaxonomyImportService $taxonomyImporter,
    ) {}

    /**
     * Run the full transform pipeline for a migration session.
     *
     * @return array{total: int, processed: int, failed: int, skipped: int}
     */
    public function run(MigrationSession $session, int $batchSize = 50): array
    {
        $connector = $this->connectorFactory->make(
            $session->source_cms,
            $session->source_url,
            $session->credentials ? (is_array($session->credentials) ? $session->credentials : []) : null,
        );

        // Step 1: Import taxonomies
        $taxonomyMap = $this->taxonomyImporter->importTaxonomies($session);

        // Step 2: Process each type mapping
        $stats = ['total' => 0, 'processed' => 0, 'failed' => 0, 'skipped' => 0];

        /** @var Collection<int, MigrationTypeMapping> $mappings */
        $mappings = $session->typeMappings()->where('status', 'confirmed')->get();

        foreach ($mappings as $mapping) {
            $typeStats = $this->processTypeMapping(
                $session,
                $mapping,
                $connector,
                $taxonomyMap,
                $batchSize,
            );

            $stats['total'] += $typeStats['total'];
            $stats['processed'] += $typeStats['processed'];
            $stats['failed'] += $typeStats['failed'];
            $stats['skipped'] += $typeStats['skipped'];
        }

        // Update session totals
        $session->update([
            'total_items' => $stats['total'],
            'processed_items' => $stats['processed'],
            'failed_items' => $stats['failed'],
            'skipped_items' => $stats['skipped'],
        ]);

        return $stats;
    }

    /**
     * Process all content items for a single type mapping.
     *
     * @param  Collection<string, string>  $taxonomyMap
     * @return array{total: int, processed: int, failed: int, skipped: int}
     */
    private function processTypeMapping(
        MigrationSession $session,
        MigrationTypeMapping $mapping,
        Connectors\CmsConnectorInterface $connector,
        Collection $taxonomyMap,
        int $batchSize,
    ): array {
        $sourceTypeKey = $mapping->source_type_key;
        $total = $connector->getTotalCount($sourceTypeKey);

        $stats = ['total' => $total, 'processed' => 0, 'failed' => 0, 'skipped' => 0];

        // Resume from checkpoint if available
        $checkpoint = MigrationCheckpoint::query()
            ->where('migration_session_id', $session->id)
            ->where('source_type_key', $sourceTypeKey)
            ->first();

        $page = 1;
        $cursor = $checkpoint?->last_cursor;
        $itemCount = $checkpoint ? $checkpoint->item_count : 0;

        if ($cursor !== null) {
            $page = (int) ceil($itemCount / $batchSize) + 1;
        }

        while (true) {
            $items = $connector->getContentItems($sourceTypeKey, $page, $batchSize, $cursor);

            if (empty($items)) {
                break;
            }

            foreach ($items as $sourceItem) {
                if (! is_array($sourceItem)) {
                    $stats['skipped']++;

                    continue;
                }

                $sourceId = (string) ($sourceItem['id'] ?? $sourceItem['_id'] ?? '');

                // Skip if already processed
                $existing = MigrationItem::query()
                    ->where('migration_session_id', $session->id)
                    ->where('source_type_key', $sourceTypeKey)
                    ->where('source_id', $sourceId)
                    ->where('status', 'completed')
                    ->exists();

                if ($existing) {
                    $stats['skipped']++;

                    continue;
                }

                try {
                    $result = $this->transformer->transform($sourceItem, $mapping);

                    // Resolve taxonomy references
                    $resolvedTaxonomyIds = [];
                    foreach ($result['taxonomy_refs'] as $ref) {
                        $numenId = $taxonomyMap->get($ref);
                        if ($numenId !== null) {
                            $resolvedTaxonomyIds[] = $numenId;
                        }
                    }

                    MigrationItem::updateOrCreate(
                        [
                            'migration_session_id' => $session->id,
                            'source_type_key' => $sourceTypeKey,
                            'source_id' => $sourceId,
                        ],
                        [
                            'space_id' => $session->space_id,
                            'status' => 'transformed',
                            'source_payload' => json_encode([
                                'fields' => $result['fields'],
                                'media_refs' => $result['media_refs'],
                                'taxonomy_ids' => $resolvedTaxonomyIds,
                            ]),
                            'source_hash' => md5(json_encode($sourceItem) ?: ''),
                            'attempt' => 1,
                        ],
                    );

                    $stats['processed']++;
                } catch (\Throwable $e) {
                    Log::warning('ContentTransformPipeline: item failed', [
                        'source_id' => $sourceId,
                        'error' => $e->getMessage(),
                    ]);

                    MigrationItem::updateOrCreate(
                        [
                            'migration_session_id' => $session->id,
                            'source_type_key' => $sourceTypeKey,
                            'source_id' => $sourceId,
                        ],
                        [
                            'space_id' => $session->space_id,
                            'status' => 'failed',
                            'error_message' => $e->getMessage(),
                            'attempt' => 1,
                        ],
                    );

                    $stats['failed']++;
                }

                $itemCount++;
                $cursor = $sourceId;
            }

            // Update checkpoint after each batch
            MigrationCheckpoint::updateOrCreate(
                [
                    'migration_session_id' => $session->id,
                    'source_type_key' => $sourceTypeKey,
                ],
                [
                    'space_id' => $session->space_id,
                    'last_cursor' => $cursor ?? '',
                    'last_synced_at' => now(),
                    'item_count' => $itemCount,
                ],
            );

            $page++;
        }

        return $stats;
    }
}
