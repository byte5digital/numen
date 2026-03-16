<?php

namespace App\Jobs;

use App\Models\ContentQualityConfig;
use App\Models\PipelineRun;
use App\Pipelines\PipelineExecutor;
use App\Services\Quality\ContentQualityService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Executes a quality_gate pipeline stage.
 *
 * Scores the content associated with the run. If the score is below the
 * configured threshold the pipeline is paused; otherwise it advances.
 */
class QualityGateStageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(
        public readonly PipelineRun $run,
        public readonly array $stage,
    ) {
        $this->onQueue('ai-pipeline');
    }

    public function handle(ContentQualityService $qualityService, PipelineExecutor $executor): void
    {
        $content = $this->run->content;

        if ($content === null) {
            Log::warning('QualityGateStageJob: no content on pipeline run', ['run_id' => $this->run->id]);
            $executor->advance($this->run, ['success' => true, 'skipped' => 'no_content']);

            return;
        }

        // Load space quality config
        $config = ContentQualityConfig::where('space_id', $content->space_id)->first();

        // Determine minimum score: stage config overrides global config
        $minScore = (float) ($this->stage['min_score']
            ?? ($config?->pipeline_gate_enabled ? $config->pipeline_gate_min_score : null)
            ?? 70.0);

        // Score the content (may use cached score)
        $score = $qualityService->score($content, $config ?? null);

        Log::info('QualityGateStageJob: scored content', [
            'run_id' => $this->run->id,
            'content_id' => $content->id,
            'overall_score' => $score->overall_score,
            'min_score' => $minScore,
        ]);

        if ($score->overall_score < $minScore) {
            // Pause pipeline — quality gate failed
            $this->run->update([
                'status' => 'paused_for_review',
            ]);
            $this->run->addStageResult($this->stage['name'], [
                'success' => false,
                'quality_gate_failed' => true,
                'overall_score' => $score->overall_score,
                'min_score' => $minScore,
                'score_id' => $score->id,
            ]);

            Log::info('QualityGateStageJob: pipeline paused — quality below threshold', [
                'run_id' => $this->run->id,
                'overall_score' => $score->overall_score,
                'min_score' => $minScore,
            ]);

            return;
        }

        $executor->advance($this->run, [
            'success' => true,
            'quality_gate_passed' => true,
            'overall_score' => $score->overall_score,
            'min_score' => $minScore,
            'score_id' => $score->id,
        ]);
    }
}
