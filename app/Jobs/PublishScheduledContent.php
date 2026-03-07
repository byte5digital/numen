<?php

namespace App\Jobs;

use App\Models\ScheduledPublish;
use App\Services\Versioning\VersioningService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class PublishScheduledContent implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Maximum number of retry attempts.
     */
    public int $tries = 3;

    /**
     * Backoff in seconds between retries.
     */
    public int $backoff = 60;

    public function __construct(public string $scheduleId) {}

    public function handle(VersioningService $versioning): void
    {
        $schedule = ScheduledPublish::with(['content', 'version'])->find($this->scheduleId);

        if (! $schedule) {
            Log::warning('PublishScheduledContent: Schedule not found', ['id' => $this->scheduleId]);

            return;
        }

        if ($schedule->status !== 'pending') {
            Log::info('PublishScheduledContent: Skipping — status is not pending', [
                'id' => $this->scheduleId,
                'status' => $schedule->status,
            ]);

            return;
        }

        if (! $schedule->content || ! $schedule->version) {
            Log::error('PublishScheduledContent: Missing content or version', ['id' => $this->scheduleId]);
            $schedule->update(['status' => 'failed']);

            return;
        }

        try {
            $versioning->publish($schedule->content, $schedule->version);
            $schedule->update(['status' => 'published']);

            Log::info('PublishScheduledContent: Published successfully', [
                'content_id' => $schedule->content_id,
                'version_id' => $schedule->version_id,
            ]);
        } catch (\Throwable $e) {
            $schedule->update(['status' => 'failed']);
            Log::error('PublishScheduledContent: Failed', [
                'id' => $this->scheduleId,
                'error' => $e->getMessage(),
            ]);

            throw $e; // Allow queue to retry
        }
    }
}
