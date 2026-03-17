<?php

declare(strict_types=1);

namespace App\Services\Migration;

use App\Models\MediaAsset;
use App\Models\Migration\MigrationCheckpoint;
use App\Models\Migration\MigrationSession;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MediaImportService
{
    private const CHECKPOINT_KEY = '__media_import';

    public function __construct(
        private readonly CmsConnectorFactory $connectorFactory,
    ) {}

    public function importMedia(MigrationSession $session, int $batchSize = 50): Collection
    {
        $connector = $this->connectorFactory->make(
            $session->source_cms,
            $session->source_url,
            $session->credentials ? (is_array($session->credentials) ? $session->credentials : []) : null,
        );

        $mapping = collect();
        $page = 1;

        $checkpoint = MigrationCheckpoint::query()
            ->where('migration_session_id', $session->id)
            ->where('source_type_key', self::CHECKPOINT_KEY)
            ->first();

        $processedCount = $checkpoint ? $checkpoint->item_count : 0;

        if ($processedCount > 0) {
            $page = (int) ceil($processedCount / $batchSize) + 1;
        }

        while (true) {
            $mediaItems = $connector->getMediaItems($page, $batchSize);

            if (empty($mediaItems)) {
                break;
            }

            foreach ($mediaItems as $mediaItem) {
                if (! is_array($mediaItem)) {
                    continue;
                }

                $sourceUrl = (string) ($mediaItem['url'] ?? $mediaItem['source_url'] ?? '');
                $sourceId = (string) ($mediaItem['id'] ?? $mediaItem['_id'] ?? $sourceUrl);

                if ($sourceUrl === '') {
                    continue;
                }

                try {
                    $result = $this->importSingleMedia($session, $sourceUrl, $mediaItem);

                    if ($result !== null) {
                        $mapping->put($sourceId, $result);
                        $mapping->put($sourceUrl, $result);
                    }
                } catch (\Throwable $e) {
                    Log::warning('MediaImportService: failed to import media', [
                        'source_url' => $sourceUrl,
                        'error' => $e->getMessage(),
                    ]);
                }

                $processedCount++;
            }

            MigrationCheckpoint::updateOrCreate(
                [
                    'migration_session_id' => $session->id,
                    'source_type_key' => self::CHECKPOINT_KEY,
                ],
                [
                    'space_id' => $session->space_id,
                    'last_cursor' => (string) $page,
                    'last_synced_at' => now(),
                    'item_count' => $processedCount,
                ],
            );

            $page++;
        }

        return $mapping;
    }

    private function importSingleMedia(MigrationSession $session, string $sourceUrl, array $mediaItem): ?string
    {
        $urlHash = md5($sourceUrl);

        $existing = MediaAsset::query()
            ->where('space_id', $session->space_id)
            ->whereJsonContains('metadata->migration_url_hash', $urlHash)
            ->first();

        if ($existing) {
            return $existing->id;
        }

        $response = Http::timeout(30)->get($sourceUrl);

        if (! $response->successful()) {
            Log::warning('MediaImportService: download failed', [
                'url' => $sourceUrl,
                'status' => $response->status(),
            ]);

            return null;
        }

        $contents = $response->body();
        $mimeType = $this->detectMimeType($response->header('Content-Type'), $sourceUrl);
        $filename = $this->extractFilename($sourceUrl, $mediaItem);
        $extension = pathinfo($filename, PATHINFO_EXTENSION) ?: $this->mimeToExtension($mimeType);

        $storagePath = sprintf('media/%s/%s.%s', $session->space_id, Str::ulid()->toBase32(), $extension);

        Storage::disk('public')->put($storagePath, $contents);

        $asset = MediaAsset::create([
            'space_id' => $session->space_id,
            'filename' => $filename,
            'disk' => 'public',
            'path' => $storagePath,
            'mime_type' => $mimeType,
            'size_bytes' => strlen($contents),
            'source' => 'migration',
            'alt_text' => $mediaItem['alt_text'] ?? $mediaItem['alt'] ?? null,
            'caption' => $mediaItem['caption'] ?? null,
            'width' => $mediaItem['width'] ?? null,
            'height' => $mediaItem['height'] ?? null,
            'metadata' => [
                'migration_session_id' => $session->id,
                'migration_source_url' => $sourceUrl,
                'migration_url_hash' => $urlHash,
            ],
        ]);

        return $asset->id;
    }

    public function getProgress(MigrationSession $session): array
    {
        $checkpoint = MigrationCheckpoint::query()
            ->where('migration_session_id', $session->id)
            ->where('source_type_key', self::CHECKPOINT_KEY)
            ->first();

        return [
            'total_processed' => $checkpoint !== null ? $checkpoint->item_count : 0,
            'last_synced_at' => $checkpoint?->last_synced_at?->toIso8601String(),
            'status' => $checkpoint ? 'in_progress' : 'pending',
        ];
    }

    private function detectMimeType(?string $contentType, string $url): string
    {
        if ($contentType && ! str_contains($contentType, 'octet-stream')) {
            return explode(';', $contentType)[0];
        }

        $ext = strtolower(pathinfo(parse_url($url, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION));

        return match ($ext) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml',
            'pdf' => 'application/pdf',
            'mp4' => 'video/mp4',
            'webm' => 'video/webm',
            'mp3' => 'audio/mpeg',
            'wav' => 'audio/wav',
            default => 'application/octet-stream',
        };
    }

    private function mimeToExtension(string $mimeType): string
    {
        return match ($mimeType) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'image/svg+xml' => 'svg',
            'application/pdf' => 'pdf',
            'video/mp4' => 'mp4',
            'video/webm' => 'webm',
            'audio/mpeg' => 'mp3',
            'audio/wav' => 'wav',
            default => 'bin',
        };
    }

    private function extractFilename(string $url, array $mediaItem): string
    {
        if (! empty($mediaItem['filename'])) {
            return (string) $mediaItem['filename'];
        }

        if (! empty($mediaItem['name'])) {
            return (string) $mediaItem['name'];
        }

        $path = parse_url($url, PHP_URL_PATH);

        return $path ? basename($path) : 'unnamed-media';
    }
}
