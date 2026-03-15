<?php

namespace App\Console\Commands;

use App\Models\ChatConversation;
use Illuminate\Console\Command;

class PruneOldConversationsCommand extends Command
{
    protected $signature = 'chat:prune {--days=90 : Delete conversations inactive for this many days}';

    protected $description = 'Delete conversations (and their messages) that have been inactive beyond the threshold';

    public function handle(): int
    {
        $days = (int) $this->option('days');

        if ($days <= 0) {
            $this->error('--days must be a positive integer.');

            return self::FAILURE;
        }

        $threshold = now()->subDays($days);

        $query = ChatConversation::where(function ($q) use ($threshold): void {
            $q->where('last_active_at', '<', $threshold)
                ->orWhere(function ($q2) use ($threshold): void {
                    $q2->whereNull('last_active_at')
                        ->where('created_at', '<', $threshold);
                });
        });

        $count = $query->count();

        if ($count === 0) {
            $this->info('No conversations to prune.');

            return self::SUCCESS;
        }

        $this->info("Pruning {$count} conversation(s) inactive for more than {$days} day(s)...");

        // Delete in chunks to avoid lock timeouts on large tables
        $query->chunkById(500, function ($conversations): void {
            foreach ($conversations as $conversation) {
                $conversation->delete(); // cascades to messages via DB constraint
            }
        });

        $this->info("Done. {$count} conversation(s) pruned.");

        return self::SUCCESS;
    }
}
