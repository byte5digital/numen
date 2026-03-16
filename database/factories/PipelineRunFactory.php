<?php

namespace Database\Factories;

use App\Models\ContentPipeline;
use App\Models\PipelineRun;
use Illuminate\Database\Eloquent\Factories\Factory;

class PipelineRunFactory extends Factory
{
    protected $model = PipelineRun::class;

    public function definition(): array
    {
        return [
            'pipeline_id' => ContentPipeline::factory(),
            'content_id' => null,
            'content_brief_id' => null,
            'status' => 'running',
            'current_stage' => 'stage_1',
            'stage_results' => [],
            'context' => [],
            'started_at' => now(),
            'completed_at' => null,
        ];
    }
}
