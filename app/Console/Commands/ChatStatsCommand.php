<?php

namespace App\Console\Commands;

use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ChatStatsCommand extends Command
{
    protected $signature = 'chat:stats';

    protected $description = 'Display chat usage statistics (conversations, messages, cost, top users)';

    public function handle(): int
    {
        $totalConversations = ChatConversation::count();
        $totalMessages = ChatMessage::count();
        $totalCost = (float) ChatMessage::sum('cost_usd');
        $avgCostPerConversation = $totalConversations > 0
            ? round($totalCost / $totalConversations, 6)
            : 0.0;

        $this->info('=== Chat Usage Statistics ===');
        $this->table(
            ['Metric', 'Value'],
            [
                ['Total conversations', number_format($totalConversations)],
                ['Total messages', number_format($totalMessages)],
                ['Total cost (USD)', '$'.number_format($totalCost, 4)],
                ['Avg cost / conversation', '$'.number_format($avgCostPerConversation, 6)],
            ]
        );

        // Most active users
        /** @var array<int, object{user_id: int, conversation_count: int}> $topUsers */
        $topUsers = DB::table('chat_conversations')
            ->select('user_id', DB::raw('COUNT(*) as conversation_count'))
            ->groupBy('user_id')
            ->orderByDesc('conversation_count')
            ->limit(10)
            ->get()
            ->toArray();

        if (count($topUsers) > 0) {
            $this->newLine();
            $this->info('=== Most Active Users (top 10 by conversations) ===');

            $rows = array_map(function (object $row): array {
                $userId = (int) $row->user_id;
                $user = User::find($userId);
                $email = $user instanceof User ? $user->email : "(user #{$userId})";
                $msgCount = ChatMessage::whereHas(
                    'conversation',
                    fn ($q) => $q->where('user_id', $userId)
                )->count();
                $cost = (float) ChatMessage::whereHas(
                    'conversation',
                    fn ($q) => $q->where('user_id', $userId)
                )->sum('cost_usd');

                return [
                    $email,
                    (int) $row->conversation_count,
                    $msgCount,
                    '$'.number_format($cost, 4),
                ];
            }, $topUsers);

            $this->table(['User', 'Conversations', 'Messages', 'Cost (USD)'], $rows);
        }

        return self::SUCCESS;
    }
}
