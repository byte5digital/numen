<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Migration;

use App\Http\Controllers\Controller;
use App\Jobs\MediaImportJob;
use App\Models\Migration\MigrationSession;
use App\Models\Space;
use App\Services\Migration\MediaImportService;
use Illuminate\Http\JsonResponse;

class MigrationMediaController extends Controller
{
    public function __construct(
        private readonly MediaImportService $mediaImportService,
    ) {}

    /**
     * Start media import for a migration session.
     */
    public function start(Space $space, string $session): JsonResponse
    {
        $migrationSession = MigrationSession::findOrFail($session);
        abort_unless($migrationSession->space_id === $space->id, 404);

        if (! in_array($migrationSession->status, ['pending', 'mapped', 'running'], true)) {
            return response()->json([
                'message' => "Cannot start media import in '{$migrationSession->status}' status.",
            ], 422);
        }

        MediaImportJob::dispatch($migrationSession->id);

        return response()->json([
            'message' => 'Media import started.',
            'data' => [
                'session_id' => $migrationSession->id,
                'status' => 'queued',
            ],
        ]);
    }

    /**
     * Get media import progress.
     */
    public function progress(Space $space, string $session): JsonResponse
    {
        $migrationSession = MigrationSession::findOrFail($session);
        abort_unless($migrationSession->space_id === $space->id, 404);

        $progress = $this->mediaImportService->getProgress($migrationSession);

        return response()->json([
            'data' => [
                'session_id' => $migrationSession->id,
                ...$progress,
            ],
        ]);
    }
}
