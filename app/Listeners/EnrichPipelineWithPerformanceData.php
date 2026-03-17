<?php

namespace App\Listeners;

use App\Events\Pipeline\PipelineStarted;
use App\Services\Performance\PerformanceInsightBuilder;
use Illuminate\Support\Facades\Log;

class EnrichPipelineWithPerformanceData
{
    public function __construct(
        private readonly PerformanceInsightBuilder $insightBuilder,
    ) {}

    /**
     * Enrich pipeline run context with performance insights when a pipeline starts.
     *
     * Injects space-level performance data into the run context so AI agent stages
     * can leverage historical performance patterns for content generation.
     */
    public function handle(PipelineStarted $event): void
    {
        $run = $event->run;
        $spaceId = $run->context['space_id'] ?? null;

        if (! $spaceId) {
            return;
        }

        try {
            $insights = $this->insightBuilder->buildInsights($spaceId);

            if (! $insights['has_model']) {
                return;
            }

            $promptContext = $this->insightBuilder->toPromptContext();

            $context = $run->context ?? [];
            $context['performance_insights'] = $insights;
            $context['performance_prompt_context'] = $promptContext;

            $run->update(['context' => $context]);

            Log::info('Enriched pipeline run with performance data', [
                'run_id' => $run->id,
                'space_id' => $spaceId,
                'model_confidence' => $insights['model_confidence'],
            ]);
        } catch (\Throwable $e) {
            // Performance enrichment is non-critical — log and continue
            Log::warning('Failed to enrich pipeline with performance data', [
                'run_id' => $run->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
