<?php

namespace App\Events;

use App\Models\Performance\ContentPerformanceEvent;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PerformanceEventIngested
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly ContentPerformanceEvent $event,
    ) {}
}
