<?php

declare(strict_types=1);

namespace App\Services\Migration;

use App\Models\Migration\MigrationCheckpoint;
use App\Models\Migration\MigrationItem;
use App\Models\Migration\MigrationSession;
use App\Models\Migration\MigrationTypeMapping;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class ContentTransformPipeline
{
    public function __construct(
        private readonly CmsConnectorFactory $connectorFactory,
        private readonly ContentTransformerService $transformer,
        private readonly TaxonomyImportService $taxonomyImporter,
        private readonly MediaImportService $mediaImporter,
        private readonly UserImportService $userImporter,
    ) {}

    /**
     * @return array{total: int, processed: int, failed: int, skipped: int}
     */
    public function run(MigrationSession $session, int $batchSize = 50): array
    {
        $connector = $this->connectorFactory->make(
            $session->source_cms,
            $session->source_url,
            $session->credentials ? (is_array($session->credentials) ? $session->credentials : []) : null,
        );

        $taxonomyMap = $this->taxonomyImporter->importTaxonomies($session);
        $mediaMap = $this->importMediaMapping($session);
        $userMap = $this->importUserMapping($session);

        $stats = ['total' => 0, 'processed' => 0, 'failed' => 0, 'skipped' => 0];

        /** @var Collection<int, MigrationTypeMapping> $mappings */
        $mappings = $session->typeMappings()->where('status', 'confirmed')->get();

        foreach ($mappings as $mapping) {
            $typeStats = $this->processTypeMapping(
                $session, $mapping, $connector, $taxonomyMap, $mediaMap, $userMap, $batchSize,
            );
            $stats['total'] += $typeStats['total'];
            $stats['processed'] += $typeStats['processed'];
            $stats['failed'] += $typeStats['failed'];
            $stats['skipped'] += $typeStats['skipped'];
        }

        $session->update([
            'total_items' => $stats['total'],
            'processed_items' => $stats['processed'],
            'failed_items' => $stats['failed'],
            'skipped_items' => $stats['skipped'],
        ]);

        return $stats;
    }

    /** @return Collection<string, string> */
    private function importMediaMapping(MigrationSession $session): Collection
    {
        $options = $session->options ?? [];
        if (! empty($options['media_mapping'])) {
            return collect($options['media_mapping']);
        }
        try {
            $mapping = $this->mediaImporter->importMedia($session);
            $options['media_mapping'] = $mapping->toArray();
            $session->update(['options' => $options]);

            return $mapping;
        } catch (\Throwable $e) {
            Log::warning('ContentTransformPipeline: media import failed', ['error' => $e->getMessage()]);

            return collect();
        }
    }

    /** @return Collection<string, int> */
    private function importUserMapping(MigrationSession $session): Collection
    {
        $options = $session->options ?? [];
        if (! empty($options['user_mapping'])) {
            return collect($options['user_mapping']);
        }
        try {
            $mapping = $this->userImporter->importUsers($session);
            $options['user_mapping'] = $mapping->toArray();
            $session->update(['options' => $options]);

            return $mapping;
        } catch (\Throwable $e) {
            Log::warning('ContentTransformPipeline: user import failed', ['error' => $e->getMessage()]);

            return collect();
        }
    }

    /**
     * @param  Collection<string, string>  $taxonomyMap
     * @param  Collection<string, string>  $mediaMap
     * @param  Collection<string, int>  $userMap
     * @return array{total: int, processed: int, failed: int, skipped: int}
     */
    private function processTypeMapping(
        MigrationSession $session,
        MigrationTypeMapping $mapping,
        Connectors\CmsConnectorInterface $connector,
        Collection $taxonomyMap,
        Collection $mediaMap,
        Collection $userMap,
        int $batchSize,
    ): array {
        $sourceTypeKey = $mapping->source_type_key;
        $total = $connector->getTotalCount($sourceTypeKey);
        $stats = ['total' => $total, 'processed' => 0, 'failed' => 0, 'skipped' => 0];

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

                    $resolvedTaxonomyIds = [];
                    foreach ($result['taxonomy_refs'] as $ref) {
                        $numenId = $taxonomyMap->get($ref);
                        if ($numenId !== null) {
                            $resolvedTaxonomyIds[] = $numenId;
                        }
                    }

                    $resolvedMediaIds = [];
                    $transformedFields = $result['fields'];
                    foreach ($result['media_refs'] as $mediaRef) {
                        $numenMediaId = $mediaMap->get($mediaRef);
                        if ($numenMediaId !== null) {
                            $resolvedMediaIds[] = $numenMediaId;
                            $transformedFields = $this->replaceMediaReferences(
                                $transformedFields, $mediaRef, $numenMediaId,
                            );
                        }
                    }

                    $authorId = $session->created_by;
                    $sourceAuthorId = (string) ($sourceItem['author'] ?? $sourceItem['author_id'] ?? $sourceItem['user_id'] ?? '');
                    if ($sourceAuthorId !== '' && $userMap->has($sourceAuthorId)) {
                        $authorId = (string) $userMap->get($sourceAuthorId);
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
                                'fields' => $transformedFields,
                                'media_refs' => $result['media_refs'],
                                'media_ids' => $resolvedMediaIds,
                                'taxonomy_ids' => $resolvedTaxonomyIds,
                                'author_id' => $authorId,
                            ]),
                            'numen_media_ids' => ! empty($resolvedMediaIds) ? $resolvedMediaIds : null,
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

    /**
     * @param  array<string, mixed>  $fields
     * @return array<string, mixed>
     */
    private function replaceMediaReferences(array $fields, string $sourceRef, string $numenMediaId): array
    {
        foreach ($fields as $key => $value) {
            if (is_string($value) && str_contains($value, $sourceRef)) {
                $fields[$key] = str_replace($sourceRef, sprintf('/api/v1/media/%s', $numenMediaId), $value);
            } elseif (is_array($value)) {
                $fields[$key] = $this->replaceMediaReferences($value, $sourceRef, $numenMediaId);
            }
        }

        return $fields;
    }
}
