<?php

namespace App\Events\Pipeline;

use App\Models\PipelineRun;
use Illuminate\Foundation\Events\Dispatchable;

class PipelineStageCompleted
{
    use Dispatchable;

    public function __construct(
        public PipelineRun $run,
        public string $stageName,
        public array $result,
    ) {}
}
