<?php

declare(strict_types=1);

namespace App\Services\Migration;

use App\Models\Content;
use App\Models\MediaAsset;
use App\Models\Migration\MigrationItem;
use App\Models\Migration\MigrationSession;
use App\Models\TaxonomyTerm;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Rolls back a completed migration by deleting all imported content, media,
 * taxonomies, and users tracked via MigrationItem records.
 */
class RollbackService
{
    /**
     * Undo a completed migration.
     *
     * @return array{contentDeleted: int, mediaDeleted: int, taxonomiesDeleted: int, usersDeleted: int}
     *
     * @throws \InvalidArgumentException If session is not in a rollback-eligible status.
     */
    public function rollback(MigrationSession $session): array
    {
        if ($session->status !== 'completed') {
            throw new \InvalidArgumentException(
                "Cannot rollback migration in '{$session->status}' status. Only completed migrations can be rolled back."
            );
        }

        Log::info('RollbackService: starting rollback', ['session_id' => $session->id]);

        $summary = DB::transaction(function () use ($session): array {
            // Collect all IDs before modifying item statuses
            $completedItems = MigrationItem::where('migration_session_id', $session->id)
                ->where('status', 'completed')
                ->get();

            $contentIds = $completedItems->whereNotNull('numen_content_id')
                ->where('source_type_key', '!=', 'taxonomy')
                ->where('source_type_key', '!=', 'user')
                ->pluck('numen_content_id')
                ->filter()
                ->unique()
                ->values();

            $mediaIds = $completedItems->whereNotNull('numen_media_ids')
                ->pluck('numen_media_ids')
                ->flatten()
                ->filter()
                ->unique()
                ->values();

            $taxonomyIds = $completedItems->where('source_type_key', 'taxonomy')
                ->whereNotNull('numen_content_id')
                ->pluck('numen_content_id')
                ->filter()
                ->unique()
                ->values();

            $userIds = $completedItems->where('source_type_key', 'user')
                ->whereNotNull('numen_content_id')
                ->pluck('numen_content_id')
                ->filter()
                ->unique()
                ->values();

            // Delete imported resources
            $contentDeleted = $contentIds->isNotEmpty()
                ? Content::withTrashed()->whereIn('id', $contentIds)->forceDelete()
                : 0;

            $mediaDeleted = $mediaIds->isNotEmpty()
                ? MediaAsset::whereIn('id', $mediaIds)->delete()
                : 0;

            $taxonomiesDeleted = $taxonomyIds->isNotEmpty()
                ? TaxonomyTerm::whereIn('id', $taxonomyIds)->delete()
                : 0;

            $usersDeleted = $userIds->isNotEmpty()
                ? User::whereIn('id', $userIds)->delete()
                : 0;

            // Mark all completed items as rolled back
            MigrationItem::where('migration_session_id', $session->id)
                ->where('status', 'completed')
                ->update(['status' => 'rolled_back']);

            $session->update([
                'status' => 'rolled_back',
                'completed_at' => now(),
            ]);

            return [
                'contentDeleted' => $contentDeleted,
                'mediaDeleted' => $mediaDeleted,
                'taxonomiesDeleted' => $taxonomiesDeleted,
                'usersDeleted' => $usersDeleted,
            ];
        });

        Log::info('RollbackService: rollback complete', [
            'session_id' => $session->id,
            ...$summary,
        ]);

        return $summary;
    }
}
