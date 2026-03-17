<?php

namespace App\Jobs;

use App\Services\Performance\SpacePerformanceModelService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class BuildPerformanceModelJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 120;

    public function __construct(
        public readonly string $spaceId,
    ) {}

    public function handle(SpacePerformanceModelService $service): void
    {
        $service->refreshModel($this->spaceId);
    }
}
