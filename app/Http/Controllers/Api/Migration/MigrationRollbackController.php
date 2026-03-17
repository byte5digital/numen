<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Migration;

use App\Http\Controllers\Controller;
use App\Models\Migration\MigrationSession;
use App\Models\Space;
use App\Services\Migration\DeltaSyncService;
use App\Services\Migration\RollbackService;
use Illuminate\Http\JsonResponse;

/**
 * Handles rollback and delta-sync operations for completed migrations.
 */
class MigrationRollbackController extends Controller
{
    public function __construct(
        private readonly RollbackService $rollbackService,
        private readonly DeltaSyncService $deltaSyncService,
    ) {}

    /**
     * Rollback a completed migration, deleting all imported content.
     *
     * POST /api/v1/spaces/{space}/migrations/{session}/rollback
     */
    public function rollback(Space $space, string $session): JsonResponse
    {
        $migrationSession = MigrationSession::findOrFail($session);
        abort_unless($migrationSession->space_id === $space->id, 404);

        if ($migrationSession->status !== 'completed') {
            return response()->json([
                'message' => "Cannot rollback migration in '{$migrationSession->status}' status. Only completed migrations can be rolled back.",
            ], 422);
        }

        $summary = $this->rollbackService->rollback($migrationSession);

        return response()->json([
            'message' => 'Migration rolled back successfully.',
            'data' => [
                'session_id' => $migrationSession->id,
                'status' => $migrationSession->fresh()?->status,
                ...$summary,
            ],
        ]);
    }

    /**
     * Perform a delta sync, importing only new/changed content since last run.
     *
     * POST /api/v1/spaces/{space}/migrations/{session}/sync
     */
    public function sync(Space $space, string $session): JsonResponse
    {
        $migrationSession = MigrationSession::findOrFail($session);
        abort_unless($migrationSession->space_id === $space->id, 404);

        if (! in_array($migrationSession->status, ['completed', 'synced'], true)) {
            return response()->json([
                'message' => "Cannot sync migration in '{$migrationSession->status}' status. Only completed or synced migrations can be synced.",
            ], 422);
        }

        $summary = $this->deltaSyncService->sync($migrationSession);

        return response()->json([
            'message' => 'Delta sync completed.',
            'data' => [
                'session_id' => $migrationSession->id,
                'status' => $migrationSession->fresh()?->status,
                ...$summary,
            ],
        ]);
    }
}
