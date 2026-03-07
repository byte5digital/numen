<?php

namespace App\Console\Commands;

use App\Models\ScheduledPublish;
use App\Services\Versioning\VersioningService;
use Illuminate\Console\Command;

class PublishScheduledCommand extends Command
{
    protected $signature = 'numen:publish-scheduled';

    protected $description = 'Publish any content past its scheduled time (safety net for missed queue jobs)';

    public function handle(VersioningService $versioning): int
    {
        $due = ScheduledPublish::due()->with(['content', 'version'])->get();

        if ($due->isEmpty()) {
            $this->line('No scheduled publishes due.');

            return self::SUCCESS;
        }

        $this->info("Processing {$due->count()} scheduled publish(es)...");

        foreach ($due as $schedule) {
            if (! $schedule->content || ! $schedule->version) {
                $this->error("Skipping schedule {$schedule->id}: missing content or version");
                $schedule->update(['status' => 'failed']);

                continue;
            }

            try {
                $versioning->publish($schedule->content, $schedule->version);
                $schedule->update(['status' => 'published']);
                $this->info("Published: {$schedule->content->slug} v{$schedule->version->version_number}");
            } catch (\Throwable $e) {
                $schedule->update(['status' => 'failed']);
                $this->error("Failed: {$schedule->content->slug} — {$e->getMessage()}");
            }
        }

        return self::SUCCESS;
    }
}
