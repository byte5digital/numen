<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Migration;

use App\Http\Controllers\Controller;
use App\Models\Migration\MigrationSession;
use App\Models\Space;
use App\Services\Migration\MigrationExecutorService;
use Illuminate\Http\JsonResponse;

class MigrationExecuteController extends Controller
{
    public function __construct(
        private readonly MigrationExecutorService $executor,
    ) {}

    /**
     * Start migration execution.
     */
    public function execute(Space $space, string $session): JsonResponse
    {
        $migrationSession = MigrationSession::findOrFail($session);
        abort_unless($migrationSession->space_id === $space->id, 404);

        if (! in_array($migrationSession->status, ['pending', 'mapped', 'paused'], true)) {
            return response()->json([
                'message' => "Cannot execute migration in '{$migrationSession->status}' status.",
            ], 422);
        }

        $this->executor->execute($migrationSession);

        return response()->json([
            'message' => 'Migration started.',
            'data' => [
                'session_id' => $migrationSession->id,
                'status' => $migrationSession->fresh()?->status,
            ],
        ]);
    }

    /**
     * Get migration progress.
     */
    public function progress(Space $space, string $session): JsonResponse
    {
        $migrationSession = MigrationSession::findOrFail($session);
        abort_unless($migrationSession->space_id === $space->id, 404);

        $progress = $this->executor->getProgress($migrationSession);

        return response()->json([
            'data' => [
                'session_id' => $migrationSession->id,
                'status' => $migrationSession->status,
                ...$progress,
            ],
        ]);
    }

    /**
     * Pause a running migration.
     */
    public function pause(Space $space, string $session): JsonResponse
    {
        $migrationSession = MigrationSession::findOrFail($session);
        abort_unless($migrationSession->space_id === $space->id, 404);

        if (! $this->executor->pause($migrationSession)) {
            return response()->json([
                'message' => 'Migration cannot be paused (not running).',
            ], 422);
        }

        return response()->json([
            'message' => 'Migration paused.',
            'data' => [
                'session_id' => $migrationSession->id,
                'status' => 'paused',
            ],
        ]);
    }

    /**
     * Resume a paused migration.
     */
    public function resume(Space $space, string $session): JsonResponse
    {
        $migrationSession = MigrationSession::findOrFail($session);
        abort_unless($migrationSession->space_id === $space->id, 404);

        if (! $this->executor->resume($migrationSession)) {
            return response()->json([
                'message' => 'Migration cannot be resumed (not paused).',
            ], 422);
        }

        return response()->json([
            'message' => 'Migration resumed.',
            'data' => [
                'session_id' => $migrationSession->id,
                'status' => 'running',
            ],
        ]);
    }
}
