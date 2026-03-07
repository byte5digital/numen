<?php

namespace App\Console\Commands;

use App\Models\SearchConversation;
use Illuminate\Console\Command;

/**
 * Cleans up expired search conversations (>24h old).
 * Run daily via scheduler.
 */
class SearchCleanup extends Command
{
    protected $signature = 'numen:search:cleanup';

    protected $description = 'Clean up expired search conversations';

    public function handle(): int
    {
        $deleted = SearchConversation::where('expires_at', '<', now())->delete();

        $this->info("Deleted {$deleted} expired search conversations.");

        return self::SUCCESS;
    }
}
