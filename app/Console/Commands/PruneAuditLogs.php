<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Prunes old audit log entries based on retention policy.
 *
 * Default retention: 90 days.
 * Configure via AUDIT_LOG_RETENTION_DAYS environment variable.
 *
 * Usage:
 *   php artisan numen:audit:prune
 *   php artisan numen:audit:prune --days=30
 */
class PruneAuditLogs extends Command
{
    protected $signature = 'numen:audit:prune
                            {--days= : Retention days (overrides env config)}
                            {--dry-run : Show how many records would be deleted without deleting}';

    protected $description = 'Prune audit log entries older than the configured retention period.';

    public function handle(): int
    {
        $days = (int) ($this->option('days') ?? config('numen.audit_log_retention_days', 90));

        if ($days <= 0) {
            $this->error('Retention days must be a positive integer.');

            return self::FAILURE;
        }

        $cutoff = now()->subDays($days);

        $count = DB::table('audit_logs')
            ->where('created_at', '<', $cutoff)
            ->count();

        if ($this->option('dry-run')) {
            $this->info("Dry run: {$count} audit log entries would be deleted (older than {$days} days).");

            return self::SUCCESS;
        }

        if ($count === 0) {
            $this->info('No audit logs to prune.');

            return self::SUCCESS;
        }

        // Delete in chunks to avoid memory pressure and long locks
        $deleted = 0;
        do {
            $chunk = DB::table('audit_logs')
                ->where('created_at', '<', $cutoff)
                ->limit(1000)
                ->delete();
            $deleted += $chunk;
        } while ($chunk > 0);

        $this->info("Pruned {$deleted} audit log entries older than {$days} days.");

        return self::SUCCESS;
    }
}
