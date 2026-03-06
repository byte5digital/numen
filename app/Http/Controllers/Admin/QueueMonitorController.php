<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class QueueMonitorController extends Controller
{
    public function index()
    {
        $pending = DB::table('jobs')->count();
        $failed  = DB::table('failed_jobs')->count();

        $jobs = DB::table('jobs')
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get()
            ->map(fn ($job) => [
                'id'           => $job->id,
                'queue'        => $job->queue,
                'payload'      => $this->parsePayload($job->payload),
                'attempts'     => $job->attempts,
                'reserved_at'  => $job->reserved_at ? date('Y-m-d H:i:s', $job->reserved_at) : null,
                'available_at' => date('Y-m-d H:i:s', $job->available_at),
                'created_at'   => date('Y-m-d H:i:s', $job->created_at),
                'status'       => $job->reserved_at ? 'processing' : 'pending',
            ]);

        $failedJobs = DB::table('failed_jobs')
            ->orderByDesc('failed_at')
            ->limit(50)
            ->get()
            ->map(fn ($job) => [
                'id'         => $job->id,
                'uuid'       => $job->uuid,
                'queue'      => $job->queue,
                'payload'    => $this->parsePayload($job->payload),
                'exception'  => \Illuminate\Support\Str::limit($job->exception, 500),
                'failed_at'  => $job->failed_at,
            ]);

        // Recent pipeline runs for context
        $pipelineRuns = \App\Models\PipelineRun::with('brief')
            ->orderByDesc('created_at')
            ->limit(20)
            ->get()
            ->map(fn ($run) => [
                'id'            => $run->id,
                'brief_title'   => $run->brief?->title ?? 'Unknown',
                'status'        => $run->status,
                'current_stage' => $run->current_stage,
                'started_at'    => $run->created_at->diffForHumans(),
                'updated_at'    => $run->updated_at->diffForHumans(),
            ]);

        // Worker status check
        $workerRunning = $this->isWorkerRunning();

        return Inertia::render('Queue/Index', [
            'stats' => [
                'pending'        => $pending,
                'failed'         => $failed,
                'worker_running' => $workerRunning,
            ],
            'jobs'         => $jobs,
            'failedJobs'   => $failedJobs,
            'pipelineRuns' => $pipelineRuns,
        ]);
    }

    public function retryFailed(string $id)
    {
        $failedJob = DB::table('failed_jobs')->where('id', $id)->first();
        if (!$failedJob) {
            return back()->with('error', 'Failed job not found.');
        }

        // Re-queue the job
        DB::table('jobs')->insert([
            'queue'        => $failedJob->queue,
            'payload'      => $failedJob->payload,
            'attempts'     => 0,
            'reserved_at'  => null,
            'available_at' => time(),
            'created_at'   => time(),
        ]);

        DB::table('failed_jobs')->where('id', $id)->delete();

        return back()->with('success', 'Job re-queued.');
    }

    public function flushFailed()
    {
        DB::table('failed_jobs')->truncate();
        return back()->with('success', 'All failed jobs cleared.');
    }

    private function parsePayload(string $payload): array
    {
        $data = json_decode($payload, true);
        $displayName = $data['displayName'] ?? 'Unknown';
        $shortName = class_basename($displayName);

        return [
            'class'   => $shortName,
            'full'    => $displayName,
            'queue'   => $data['queue'] ?? 'default',
            'timeout' => $data['timeout'] ?? null,
            'tries'   => $data['maxTries'] ?? null,
        ];
    }

    private function isWorkerRunning(): bool
    {
        $output = shell_exec('ps aux | grep "[q]ueue:work" 2>/dev/null') ?? '';
        return trim($output) !== '';
    }
}
