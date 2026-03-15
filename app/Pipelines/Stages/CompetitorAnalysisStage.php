<?php

namespace App\Pipelines\Stages;

use App\Models\PipelineRun;
use App\Plugin\Contracts\PipelineStageContract;
use App\Services\Competitor\ContentFingerprintService;
use App\Services\Competitor\DifferentiationAnalysisService;
use App\Services\Competitor\SimilarContentFinder;
use Illuminate\Support\Facades\Log;

/**
 * Pipeline stage that enriches a content brief with competitor differentiation
 * context — angles, gaps, and recommendations — before content generation.
 *
 * Stage type: "competitor_analysis"
 *
 * Stage config keys (all optional):
 *   - enabled            bool   Override global config. Default: true.
 *   - similarity_threshold float Skip enrichment if no competitor above this
 *                                score is found. Default: 0.25.
 *   - max_competitors    int    How many similar competitors to analyse.
 *                                Default: config('numen.competitor_analysis.max_competitors_to_analyze').
 */
class CompetitorAnalysisStage implements PipelineStageContract
{
    public function __construct(
        private readonly DifferentiationAnalysisService $analysisService,
        private readonly ContentFingerprintService $fingerprintService,
        private readonly SimilarContentFinder $finder,
    ) {}

    public static function type(): string
    {
        return 'competitor_analysis';
    }

    public static function label(): string
    {
        return 'Competitor Analysis';
    }

    /**
     * @return array<string, mixed>
     */
    public static function configSchema(): array
    {
        return [
            'enabled' => [
                'type' => 'boolean',
                'default' => true,
                'description' => 'Whether to run competitor analysis for this stage.',
            ],
            'similarity_threshold' => [
                'type' => 'number',
                'default' => 0.25,
                'description' => 'Minimum similarity score to consider a competitor relevant.',
            ],
            'max_competitors' => [
                'type' => 'integer',
                'default' => 5,
                'description' => 'Maximum number of competitor items to analyse.',
            ],
        ];
    }

    /**
     * Enrich the run's brief with competitor differentiation context.
     *
     * @param  array<string, mixed>  $stageConfig
     * @return array<string, mixed>
     */
    public function handle(PipelineRun $run, array $stageConfig): array
    {
        // ── 1. Resolve configuration ────────────────────────────────────────
        $globalEnabled = (bool) config('numen.competitor_analysis.enabled', true);
        $stageEnabled = isset($stageConfig['enabled']) ? (bool) $stageConfig['enabled'] : $globalEnabled;

        if (! $stageEnabled) {
            Log::info('CompetitorAnalysisStage: disabled — skipping', ['run_id' => $run->id]);

            return ['skipped' => true, 'reason' => 'disabled'];
        }

        $threshold = isset($stageConfig['similarity_threshold'])
            ? (float) $stageConfig['similarity_threshold']
            : (float) config('numen.competitor_analysis.similarity_threshold', 0.25);

        $maxCompetitors = isset($stageConfig['max_competitors'])
            ? (int) $stageConfig['max_competitors']
            : (int) config('numen.competitor_analysis.max_competitors_to_analyze', 5);

        // ── 2. Resolve the brief ────────────────────────────────────────────
        $brief = $run->brief;

        if ($brief === null) {
            Log::warning('CompetitorAnalysisStage: no brief attached to run', ['run_id' => $run->id]);

            return ['skipped' => true, 'reason' => 'no_brief'];
        }

        // ── 3. Fingerprint + find similar competitors ───────────────────────
        try {
            $fingerprint = $this->fingerprintService->fingerprint($brief);
            $similar = $this->finder->findSimilar($fingerprint, threshold: $threshold, limit: $maxCompetitors);
        } catch (\Throwable $e) {
            Log::warning('CompetitorAnalysisStage: fingerprint/find failed', [
                'run_id' => $run->id,
                'error' => $e->getMessage(),
            ]);

            return ['skipped' => true, 'reason' => 'fingerprint_error', 'error' => $e->getMessage()];
        }

        if ($similar->isEmpty()) {
            Log::info('CompetitorAnalysisStage: no similar competitors above threshold — skipping', [
                'run_id' => $run->id,
                'threshold' => $threshold,
            ]);

            return ['skipped' => true, 'reason' => 'no_similar_competitors'];
        }

        // ── 4. Enrich the brief ─────────────────────────────────────────────
        try {
            $enrichedBrief = $this->analysisService->enrichBrief($brief, $this->finder);
        } catch (\Throwable $e) {
            Log::warning('CompetitorAnalysisStage: enrichment failed', [
                'run_id' => $run->id,
                'error' => $e->getMessage(),
            ]);

            return ['skipped' => true, 'reason' => 'enrichment_error', 'error' => $e->getMessage()];
        }

        // ── 5. Update pipeline context with competitor metadata ─────────────
        $context = $run->context ?? [];
        $context['competitor_analysis'] = $enrichedBrief->requirements['competitor_differentiation'] ?? [];
        $run->update(['context' => $context]);

        $competitorCount = $similar->count();

        Log::info('CompetitorAnalysisStage: brief enriched', [
            'run_id' => $run->id,
            'brief_id' => $brief->id,
            'competitor_count' => $competitorCount,
        ]);

        return [
            'enriched' => true,
            'competitor_count' => $competitorCount,
            'brief_id' => $brief->id,
        ];
    }
}
