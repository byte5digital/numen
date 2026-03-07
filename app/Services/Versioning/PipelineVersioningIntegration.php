<?php

namespace App\Services\Versioning;

use App\Models\Content;
use App\Models\ContentVersion;
use App\Models\PipelineRun;

class PipelineVersioningIntegration
{
    /**
     * Create a ContentVersion from a completed pipeline run.
     *
     * @param  array<string, mixed>  $generatedContent
     */
    public function onPipelineComplete(PipelineRun $run, array $generatedContent): ContentVersion
    {
        $content = $run->content;

        if (! $content instanceof Content) {
            throw new \RuntimeException("Pipeline run {$run->id} has no associated content.");
        }

        $nextNumber = $content->versions()->max('version_number') + 1;

        $version = $content->versions()->create([
            'version_number' => $nextNumber,
            'title' => $generatedContent['title'] ?? '',
            'excerpt' => $generatedContent['excerpt'] ?? null,
            'body' => $generatedContent['body'] ?? '',
            'body_format' => $generatedContent['body_format'] ?? 'markdown',
            'structured_fields' => $generatedContent['structured_fields'] ?? null,
            'seo_data' => $generatedContent['seo_data'] ?? null,
            'author_type' => 'ai_agent',
            'author_id' => (string) $run->pipeline_id,
            'change_reason' => 'Pipeline run: '.($run->pipeline->name ?? 'Unknown pipeline'),
            'pipeline_run_id' => $run->id,
            'ai_metadata' => [
                'pipeline_id' => $run->pipeline_id,
                'pipeline_run_id' => $run->id,
                'stages_completed' => array_keys($run->stage_results ?? []),
                'models_used' => $this->extractModelsUsed($run),
                'total_tokens' => $this->extractTotalTokens($run),
                'total_cost_usd' => $this->extractTotalCost($run),
                'brief_id' => $run->content_brief_id,
                'generated_at' => now()->toIso8601String(),
            ],
            'quality_score' => $generatedContent['quality_score'] ?? null,
            'seo_score' => $generatedContent['seo_score'] ?? null,
            'status' => 'draft', // AI versions start as draft — need human approval
            'parent_version_id' => $content->current_version_id,
            'content_hash' => null, // computed after blocks are created
        ]);

        // Create content blocks if pipeline produced them
        if (! empty($generatedContent['blocks'])) {
            foreach ($generatedContent['blocks'] as $i => $block) {
                $version->blocks()->create([
                    'type' => $block['type'],
                    'sort_order' => $i,
                    'data' => $block['data'] ?? null,
                ]);
            }
        }

        // Compute and store content hash
        $version->update(['content_hash' => $version->computeHash()]);

        return $version;
    }

    /**
     * @return array<int, string>
     */
    private function extractModelsUsed(PipelineRun $run): array
    {
        return $run->generationLogs->pluck('model')->unique()->values()->toArray();
    }

    private function extractTotalTokens(PipelineRun $run): int
    {
        return (int) $run->generationLogs->sum('total_tokens');
    }

    private function extractTotalCost(PipelineRun $run): float
    {
        return (float) $run->generationLogs->sum('cost_usd');
    }
}
