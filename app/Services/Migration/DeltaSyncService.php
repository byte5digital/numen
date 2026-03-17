<?php

declare(strict_types=1);

namespace App\Services\Migration;

use App\Models\Migration\MigrationCheckpoint;
use App\Models\Migration\MigrationItem;
use App\Models\Migration\MigrationSession;
use App\Models\Migration\MigrationTypeMapping;
use Illuminate\Support\Facades\Log;

/**
 * Performs an incremental (delta) sync since the last migration checkpoint.
 * Fetches only new or updated content from the source CMS.
 */
class DeltaSyncService
{
    public function __construct(
        private readonly CmsConnectorFactory $connectorFactory,

    ) {}

    /**
     * Run a delta sync for the given session.
     *
     * @return array{created: int, updated: int, unchanged: int, failed: int}
     *
     * @throws \InvalidArgumentException If session is not in a syncable status.
     */
    public function sync(MigrationSession $session): array
    {
        if (! in_array($session->status, ['completed', 'synced'], true)) {
            throw new \InvalidArgumentException(
                "Cannot sync migration in '{$session->status}' status. Only completed or previously synced migrations can be synced."
            );
        }

        Log::info('DeltaSyncService: starting sync', ['session_id' => $session->id]);

        $connector = $this->connectorFactory->make(
            $session->source_cms,
            $session->source_url,
            $session->credentials ? (is_array($session->credentials) ? $session->credentials : []) : null,
        );

        $mappings = MigrationTypeMapping::where('migration_session_id', $session->id)
            ->where('status', 'confirmed')
            ->get();

        $created = 0;
        $updated = 0;
        $unchanged = 0;
        $failed = 0;

        foreach ($mappings as $mapping) {
            $checkpoint = MigrationCheckpoint::where('migration_session_id', $session->id)
                ->where('source_type_key', $mapping->source_type_key)
                ->first();

            $cursor = $checkpoint?->last_cursor;
            $page = 1;
            $hasMore = true;

            while ($hasMore) {
                try {
                    $items = $connector->getContentItems(
                        $mapping->source_type_key,
                        $page,
                        50,
                        $cursor,
                    );
                } catch (\Throwable $e) {
                    Log::warning('DeltaSyncService: failed to fetch page', [
                        'session_id' => $session->id,
                        'type' => $mapping->source_type_key,
                        'page' => $page,
                        'error' => $e->getMessage(),
                    ]);
                    $failed++;

                    break;
                }

                if (empty($items)) {
                    $hasMore = false;

                    continue;
                }

                foreach ($items as $item) {
                    $sourceId = (string) ($item['id'] ?? $item['slug'] ?? '');
                    if ($sourceId === '') {
                        $failed++;

                        continue;
                    }

                    $sourceHash = md5(json_encode($item) ?: '');

                    $existing = MigrationItem::where('migration_session_id', $session->id)
                        ->where('source_type_key', $mapping->source_type_key)
                        ->where('source_id', $sourceId)
                        ->first();

                    if ($existing) {
                        if ($existing->source_hash === $sourceHash) {
                            $unchanged++;

                            continue;
                        }

                        // Source changed — mark for re-processing
                        try {
                            $existing->update([
                                'source_hash' => $sourceHash,
                                'source_payload' => json_encode($item),
                                'status' => 'transformed',
                                'attempt' => $existing->attempt + 1,
                            ]);
                            $updated++;
                        } catch (\Throwable $e) {
                            Log::warning('DeltaSyncService: failed to update item', [
                                'source_id' => $sourceId,
                                'error' => $e->getMessage(),
                            ]);
                            $failed++;
                        }
                    } else {
                        // New item
                        try {
                            MigrationItem::create([
                                'migration_session_id' => $session->id,
                                'space_id' => $session->space_id,
                                'source_type_key' => $mapping->source_type_key,
                                'source_id' => $sourceId,
                                'source_hash' => $sourceHash,
                                'source_payload' => json_encode($item),
                                'status' => 'transformed',
                                'attempt' => 1,
                            ]);
                            $created++;
                        } catch (\Throwable $e) {
                            Log::warning('DeltaSyncService: failed to create item', [
                                'source_id' => $sourceId,
                                'error' => $e->getMessage(),
                            ]);
                            $failed++;
                        }
                    }
                }

                // Update checkpoint cursor
                $lastItem = end($items);
                $newCursor = (string) ($lastItem['id'] ?? $lastItem['slug'] ?? $cursor);

                MigrationCheckpoint::updateOrCreate(
                    [
                        'migration_session_id' => $session->id,
                        'source_type_key' => $mapping->source_type_key,
                    ],
                    [
                        'space_id' => $session->space_id,
                        'last_cursor' => $newCursor,
                        'last_synced_at' => now(),
                        'item_count' => MigrationItem::where('migration_session_id', $session->id)
                            ->where('source_type_key', $mapping->source_type_key)
                            ->count(),
                    ],
                );

                $page++;

                if (count($items) < 50) {
                    $hasMore = false;
                }
            }
        }

        $session->update(['status' => 'synced']);

        $summary = [
            'created' => $created,
            'updated' => $updated,
            'unchanged' => $unchanged,
            'failed' => $failed,
        ];

        Log::info('DeltaSyncService: sync complete', [
            'session_id' => $session->id,
            ...$summary,
        ]);

        return $summary;
    }
}
