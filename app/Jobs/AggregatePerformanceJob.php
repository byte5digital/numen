<?php

namespace App\Jobs;

use App\Services\Performance\PerformanceAggregatorService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class AggregatePerformanceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(
        public readonly string $contentId,
        public readonly string $period,
        public readonly string $date,
    ) {}

    public function handle(PerformanceAggregatorService $aggregator): void
    {
        $date = Carbon::parse($this->date);

        match ($this->period) {
            'daily' => $aggregator->aggregateDaily($this->contentId, $date),
            'weekly' => $aggregator->aggregateWeekly($this->contentId, $date),
            'monthly' => $aggregator->aggregateMonthly($this->contentId, $date),
            default => throw new \InvalidArgumentException("Invalid period: {$this->period}"),
        };
    }
}
