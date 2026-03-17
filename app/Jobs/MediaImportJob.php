<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Migration\MigrationSession;
use App\Services\Migration\MediaImportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class MediaImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [30, 60, 120];

    public function __construct(
        public readonly string $sessionId,
        public readonly int $batchSize = 50,
    ) {
        $this->onQueue('migration');
    }

    public function handle(MediaImportService $mediaImportService): void
    {
        $session = MigrationSession::find($this->sessionId);

        if (! $session) {
            Log::warning('MediaImportJob: session not found', ['session_id' => $this->sessionId]);

            return;
        }

        if (in_array($session->status, ['paused', 'cancelled'], true)) {
            Log::info('MediaImportJob: session paused/cancelled, skipping', ['session_id' => $this->sessionId]);

            return;
        }

        try {
            $mapping = $mediaImportService->importMedia($session, $this->batchSize);

            $options = $session->options ?? [];
            $options['media_mapping'] = $mapping->toArray();
            $session->update(['options' => $options]);

            Log::info('MediaImportJob: completed', [
                'session_id' => $this->sessionId,
                'media_count' => $mapping->count(),
            ]);
        } catch (\Throwable $e) {
            Log::error('MediaImportJob: failed', [
                'session_id' => $this->sessionId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
