<?php

namespace App\Console\Commands\Pipeline;

use App\Models\PipelineRun;
use Illuminate\Console\Command;

class PipelineStatusCommand extends Command
{
    protected $signature = 'numen:pipeline:status
        {--limit=10 : Number of recent runs to show}
        {--running : Show only running pipelines}
        {--pipeline-id= : Filter by pipeline ID}';

    protected $description = 'Show running and recent pipeline runs';

    public function handle(): int
    {
        $query = PipelineRun::query()
            ->with(['pipeline', 'brief'])
            ->orderByDesc('created_at')
            ->limit((int) $this->option('limit'));

        if ($this->option('running')) {
            $query->where('status', 'running');
        }

        if ($pipelineId = $this->option('pipeline-id')) {
            $query->where('pipeline_id', $pipelineId);
        }

        /** @var \Illuminate\Database\Eloquent\Collection<int, PipelineRun> $runs */
        $runs = $query->get();

        if ($runs->isEmpty()) {
            $this->info('No pipeline runs found.');

            return self::SUCCESS;
        }

        $rows = $runs->map(function (PipelineRun $r): array {
            return [
                substr($r->id, 0, 8).'…',
                $r->pipeline ? $r->pipeline->name : '—',
                mb_strimwidth($r->brief ? $r->brief->title : '—', 0, 35, '…'),
                $r->status,
                $r->current_stage ?? '—',
                $r->started_at ? $r->started_at->format('Y-m-d H:i') : '—',
                $r->completed_at ? $r->completed_at->format('Y-m-d H:i') : '—',
            ];
        });

        $this->table(
            ['Run ID', 'Pipeline', 'Brief', 'Status', 'Stage', 'Started', 'Completed'],
            $rows
        );

        $running = $runs->where('status', 'running')->count();
        $completed = $runs->where('status', 'completed')->count();
        $failed = $runs->where('status', 'failed')->count();

        $this->newLine();
        $this->line("Running: <fg=yellow>{$running}</> | Completed: <fg=green>{$completed}</> | Failed: <fg=red>{$failed}</>");

        return self::SUCCESS;
    }
}
