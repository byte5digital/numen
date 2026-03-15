<?php

namespace App\Services;

use App\Models\Content;
use App\Models\MediaAsset;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MediaUsageService
{
    /**
     * Record that a useable model references a media asset in a given context.
     * Uses updateOrInsert so re-saving content doesn't create duplicate rows.
     */
    public function trackUsage(MediaAsset $asset, Model $useable, string $context = 'body'): void
    {
        DB::table('media_usage')->updateOrInsert(
            [
                'media_asset_id' => $asset->id,
                'useable_type' => $useable->getMorphClass(),
                'useable_id' => (string) $useable->getKey(),
            ],
            [
                'space_id' => $asset->space_id,
                'context' => $context,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }

    /**
     * Remove a specific usage record between an asset and a useable model.
     */
    public function removeUsage(MediaAsset $asset, Model $useable): void
    {
        DB::table('media_usage')
            ->where('media_asset_id', $asset->id)
            ->where('useable_type', $useable->getMorphClass())
            ->where('useable_id', (string) $useable->getKey())
            ->delete();
    }

    /**
     * Retrieve all useable models that reference a given media asset.
     * Returns a flat collection of associative arrays with type, id, and context.
     */
    public function getUsagesForAsset(MediaAsset $asset): Collection
    {
        return DB::table('media_usage')
            ->where('media_asset_id', $asset->id)
            ->orderBy('useable_type')
            ->orderBy('useable_id')
            ->get()
            ->map(fn ($row) => [
                'useable_type' => $row->useable_type,
                'useable_id' => $row->useable_id,
                'context' => $row->context,
                'created_at' => $row->created_at,
                'updated_at' => $row->updated_at,
            ]);
    }

    /**
     * Parse a Content record's body and hero_image_id for media asset references,
     * then sync the media_usage table accordingly.
     *
     * Detects:
     *  - hero_image_id on the content record
     *  - ULID references in img src attributes within the content version body
     *  - Explicit media asset IDs embedded as JSON field "media_id"
     */
    public function syncContentUsage(Content $content): void
    {
        try {
            $spaceId = $content->space_id;
            $foundAssetIds = [];

            // 1. Hero image reference
            if ($content->hero_image_id) {
                $foundAssetIds[$content->hero_image_id] = 'hero_image';
            }

            // 2. Parse body from current or draft version
            $version = $content->currentVersion ?? $content->draftVersion;
            if ($version && ! empty($version->body)) {
                $bodyAssetIds = $this->extractAssetIdsFromBody($version->body);
                foreach ($bodyAssetIds as $assetId) {
                    $foundAssetIds[$assetId] = $foundAssetIds[$assetId] ?? 'body';
                }
            }

            // 3. Remove stale usages for this content item
            $morphClass = $content->getMorphClass();
            $useableId = (string) $content->getKey();

            $existingAssetIds = DB::table('media_usage')
                ->where('useable_type', $morphClass)
                ->where('useable_id', $useableId)
                ->pluck('media_asset_id')
                ->all();

            $staleIds = array_diff($existingAssetIds, array_keys($foundAssetIds));
            if (! empty($staleIds)) {
                DB::table('media_usage')
                    ->where('useable_type', $morphClass)
                    ->where('useable_id', $useableId)
                    ->whereIn('media_asset_id', $staleIds)
                    ->delete();
            }

            // 4. Upsert current usages
            foreach ($foundAssetIds as $assetId => $context) {
                DB::table('media_usage')->updateOrInsert(
                    [
                        'media_asset_id' => $assetId,
                        'useable_type' => $morphClass,
                        'useable_id' => $useableId,
                    ],
                    [
                        'space_id' => $spaceId,
                        'context' => $context,
                        'updated_at' => now(),
                        'created_at' => now(),
                    ]
                );
            }
        } catch (\Throwable $e) {
            Log::warning('MediaUsageService: failed to sync content usage', [
                'content_id' => $content->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Extract ULID-like media asset IDs from HTML/JSON body content.
     *
     * @return string[]
     */
    private function extractAssetIdsFromBody(mixed $body): array
    {
        $assetIds = [];
        $bodyText = is_array($body) ? json_encode($body) : (string) $body;

        // Match ULIDs in img src attributes: src="/media/01ABCDEF..." or src="...?id=01ABCDEF..."
        if (preg_match_all('/\b([0-9A-Z]{26})\b/', $bodyText, $matches)) {
            foreach ($matches[1] as $candidate) {
                // Basic ULID format validation (26 chars, Crockford base32 chars)
                if (preg_match('/^[0-9A-HJKMNP-TV-Z]{26}$/', $candidate)) {
                    $assetIds[] = $candidate;
                }
            }
        }

        return array_unique($assetIds);
    }
}
