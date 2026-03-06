<?php

namespace App\Events\Pipeline;

use App\Models\PipelineRun;
use Illuminate\Foundation\Events\Dispatchable;

class PipelineCompleted
{
    use Dispatchable;

    public function __construct(public PipelineRun $run) {}
}
