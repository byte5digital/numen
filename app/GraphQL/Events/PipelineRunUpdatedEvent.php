<?php

namespace App\GraphQL\Events;

use App\Models\PipelineRun;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PipelineRunUpdatedEvent
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public readonly PipelineRun $pipelineRun) {}
}
