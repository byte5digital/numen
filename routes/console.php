<?php

use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Console Schedule
|--------------------------------------------------------------------------
|
| Define your application's command schedule here. Commands defined here
| will run on the schedule you specify.
|
*/

// Scheduled publishing safety net: run every minute as backup to delayed queue jobs
Schedule::command('numen:publish-scheduled')
    ->everyMinute()
    ->withoutOverlapping();

// Knowledge graph clustering: re-compute clusters every 6 hours
Schedule::call(fn () => app(\App\Services\Graph\ClusteringService::class)->computeAllClusters())
    ->everySixHours()
    ->name('graph:compute-clusters')
    ->withoutOverlapping();

// Knowledge graph prune: remove orphaned nodes/edges weekly
Schedule::command('graph:prune')
    ->weekly()
    ->withoutOverlapping()
    ->runInBackground();

// Competitor crawling: dispatch jobs for each active source based on their configured interval
Schedule::call(function () {
    \App\Models\CompetitorSource::where('is_active', true)
        ->get()
        ->each(function (\App\Models\CompetitorSource $source) {
            // Only dispatch if enough time has passed since last crawl
            $intervalMinutes = max(1, $source->crawl_interval_minutes);
            $shouldCrawl = ! $source->last_crawled_at
                || $source->last_crawled_at->addMinutes($intervalMinutes)->isPast();

            if ($shouldCrawl) {
                \App\Jobs\CrawlCompetitorSourceJob::dispatch($source);
            }
        });
})
    ->everyMinute()
    ->name('competitor:dispatch-crawlers')
    ->withoutOverlapping();
