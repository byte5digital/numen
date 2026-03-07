<?php

namespace App\Jobs;

use App\Models\SearchConversation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Deletes expired search conversations (older than 24h).
 * Run daily via scheduler.
 */
class CleanupSearchConversationsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        $deleted = SearchConversation::where('expires_at', '<', now())->delete();

        Log::info('CleanupSearchConversationsJob: deleted expired conversations', ['count' => $deleted]);
    }
}
