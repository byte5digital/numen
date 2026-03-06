<?php

namespace App\Events\Pipeline;

use App\Models\PipelineRun;
use Illuminate\Foundation\Events\Dispatchable;

class PipelineStarted
{
    use Dispatchable;

    public function __construct(public PipelineRun $run) {}
}
