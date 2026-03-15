<?php

namespace App\Console\Commands\Brief;

use App\Models\ContentBrief;
use Illuminate\Console\Command;

class BriefListCommand extends Command
{
    protected $signature = 'numen:brief:list
        {--status= : Filter by status (pending, processing, completed, failed)}
        {--space-id= : Filter by space ID}
        {--limit=20 : Number of items to show}';

    protected $description = 'List content briefs with their current status';

    public function handle(): int
    {
        $query = ContentBrief::query()
            ->with(['pipelineRun', 'persona'])
            ->orderByDesc('created_at')
            ->limit((int) $this->option('limit'));

        if ($status = $this->option('status')) {
            $query->where('status', $status);
        }

        if ($spaceId = $this->option('space-id')) {
            $query->where('space_id', $spaceId);
        }

        /** @var \Illuminate\Database\Eloquent\Collection<int, ContentBrief> $briefs */
        $briefs = $query->get();

        if ($briefs->isEmpty()) {
            $this->info('No briefs found.');

            return self::SUCCESS;
        }

        $rows = $briefs->map(function (ContentBrief $b): array {
            return [
                substr($b->id, 0, 8).'…',
                mb_strimwidth($b->title, 0, 40, '…'),
                $b->content_type_slug,
                $b->status,
                $b->priority,
                $b->pipelineRun ? $b->pipelineRun->status : '—',
                $b->created_at->format('Y-m-d'),
            ];
        });

        $this->table(
            ['ID', 'Title', 'Type', 'Status', 'Priority', 'Run', 'Created'],
            $rows
        );

        $this->line("Showing {$briefs->count()} brief(s).");

        return self::SUCCESS;
    }
}
