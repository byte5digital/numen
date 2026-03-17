<?php

declare(strict_types=1);

namespace App\Services\Migration;

use App\Jobs\MigrateContentChunkJob;
use App\Models\Migration\MigrationItem;
use App\Models\Migration\MigrationSession;
use Illuminate\Support\Facades\Log;

/**
 * Orchestrates a full migration: taxonomy import → content transform → chunk dispatch.
 */
class MigrationExecutorService
{
    public function __construct(
        private readonly ContentTransformPipeline $pipeline,
    ) {}

    /**
     * Execute the full migration for a session.
     */
    public function execute(MigrationSession $session): void
    {
        if (! in_array($session->status, ['pending', 'mapped', 'paused'], true)) {
            Log::warning('MigrationExecutor: invalid session status for execution', [
                'session_id' => $session->id,
                'status' => $session->status,
            ]);

            return;
        }

        $session->update([
            'status' => 'running',
            'started_at' => $session->started_at ?? now(),
        ]);

        try {
            // Step 1: Run transform pipeline (taxonomy import + content transform)
            $this->pipeline->run($session);

            // Step 2: Dispatch chunk jobs for all transformed items
            $this->dispatchChunkJobs($session);
        } catch (\Throwable $e) {
            Log::error('MigrationExecutor: execution failed', [
                'session_id' => $session->id,
                'error' => $e->getMessage(),
            ]);

            $session->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'completed_at' => now(),
            ]);
        }
    }

    /**
     * Dispatch chunk jobs to import transformed items.
     */
    private function dispatchChunkJobs(MigrationSession $session, int $chunkSize = 50): void
    {
        $totalTransformed = MigrationItem::where('migration_session_id', $session->id)
            ->where('status', 'transformed')
            ->count();

        if ($totalTransformed === 0) {
            $session->update([
                'status' => 'completed',
                'completed_at' => now(),
            ]);

            return;
        }

        $session->update(['total_items' => $session->total_items ?: $totalTransformed]);

        for ($offset = 0; $offset < $totalTransformed; $offset += $chunkSize) {
            MigrateContentChunkJob::dispatch(
                $session->id,
                $offset,
                $chunkSize,
            );
        }
    }

    /**
     * Get migration progress for a session.
     *
     * @return array{total: int, completed: int, failed: int, pending: int, percentage: float}
     */
    public function getProgress(MigrationSession $session): array
    {
        $total = MigrationItem::where('migration_session_id', $session->id)->count();
        $completed = MigrationItem::where('migration_session_id', $session->id)
            ->where('status', 'completed')->count();
        $failed = MigrationItem::where('migration_session_id', $session->id)
            ->where('status', 'failed')->count();
        $pending = $total - $completed - $failed;

        return [
            'total' => $total,
            'completed' => $completed,
            'failed' => $failed,
            'pending' => $pending,
            'percentage' => $total > 0 ? round(($completed + $failed) / $total * 100, 2) : 0.0,
        ];
    }

    /**
     * Pause a running migration.
     */
    public function pause(MigrationSession $session): bool
    {
        if ($session->status !== 'running') {
            return false;
        }

        $session->update(['status' => 'paused']);

        return true;
    }

    /**
     * Resume a paused migration by re-dispatching chunk jobs.
     */
    public function resume(MigrationSession $session): bool
    {
        if ($session->status !== 'paused') {
            return false;
        }

        $session->update(['status' => 'running']);

        $this->dispatchChunkJobs($session);

        return true;
    }
}
