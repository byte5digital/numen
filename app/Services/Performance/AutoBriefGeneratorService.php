<?php

namespace App\Services\Performance;

use App\Models\Content;
use App\Models\ContentBrief;
use App\Models\Performance\ContentRefreshSuggestion;

class AutoBriefGeneratorService
{
    public function __construct(
        private readonly PerformanceInsightBuilder $insightBuilder,
    ) {}

    /**
     * Auto-generate a content brief for refreshing content based on performance data.
     */
    public function generateRefreshBrief(ContentRefreshSuggestion $suggestion): ContentBrief
    {
        $content = Content::findOrFail($suggestion->content_id);
        $insights = $this->insightBuilder->buildInsights($suggestion->space_id, $suggestion->content_id);

        $requirements = $this->buildRequirements($suggestion, $insights);
        $title = sprintf('Refresh: %s', $content->slug);
        $description = $this->buildDescription($suggestion, $insights);

        $brief = ContentBrief::create([
            'space_id' => $suggestion->space_id,
            'content_id' => $suggestion->content_id,
            'title' => $title,
            'description' => $description,
            'requirements' => $requirements,
            'content_type_slug' => $content->contentType->slug ?? 'article',
            'target_locale' => $content->locale ?? 'en',
            'source' => 'performance_refresh',
            'priority' => $suggestion->urgency_score >= 50 ? 'high' : ($suggestion->urgency_score >= 25 ? 'medium' : 'low'),
            'status' => 'draft',
        ]);

        $suggestion->update([
            'brief_id' => $brief->id,
            'status' => 'in_progress',
            'acted_on_at' => now(),
        ]);

        return $brief;
    }

    /**
     * @param  array<string, mixed>  $insights
     * @return list<string>
     */
    private function buildRequirements(ContentRefreshSuggestion $suggestion, array $insights): array
    {
        $requirements = [];
        $reasons = $suggestion->suggestions ?? [];

        foreach ($reasons as $item) {
            $type = $item['type'] ?? '';
            $requirements[] = match ($type) {
                'update_content' => 'Update content to reverse declining traffic trend. Refresh headline, intro, and key sections.',
                'improve_engagement' => 'Reduce bounce rate by improving content structure, adding a compelling intro, and clearer CTAs.',
                'add_visuals' => 'Increase scroll depth by adding more visuals, breaking up long text blocks, and adding interactive elements.',
                'update_statistics' => 'Update outdated statistics, facts, and references. Check all links are still valid.',
                'optimize_seo' => 'Optimize for better search performance. Review keywords, meta description, and internal linking.',
                default => 'Review and improve content quality.',
            };
        }

        $contentInsights = $insights['content_specific'] ?? [];
        if (! empty($contentInsights)) {
            if (isset($contentInsights['scroll_depth']) && $contentInsights['scroll_depth'] < 0.4) {
                $requirements[] = 'Add more visual breaks and subheadings to improve scroll depth.';
            }
        }

        $optimalWordCount = $insights['optimal_word_count'] ?? ['min' => 0, 'max' => 0];
        if ($optimalWordCount['min'] > 0) {
            $requirements[] = sprintf(
                'Target word count: %d-%d words (optimal range for this space).',
                $optimalWordCount['min'],
                $optimalWordCount['max'],
            );
        }

        return $requirements;
    }

    /**
     * @param  array<string, mixed>  $insights
     */
    private function buildDescription(ContentRefreshSuggestion $suggestion, array $insights): string
    {
        $lines = ['This brief was auto-generated based on performance data analysis.'];
        $lines[] = '';

        $context = $suggestion->performance_context ?? [];
        if (! empty($context)) {
            $lines[] = '## Current Performance';
            if (isset($context['current_score'])) {
                $lines[] = sprintf('- Composite score: %.1f', $context['current_score']);
            }
            if (isset($context['current_views'])) {
                $lines[] = sprintf('- Weekly views: %d', $context['current_views']);
            }
            if (isset($context['bounce_rate'])) {
                $lines[] = sprintf('- Bounce rate: %.0f%%', $context['bounce_rate'] * 100);
            }
            $lines[] = '';
        }

        $reasons = $suggestion->suggestions ?? [];
        if (! empty($reasons)) {
            $lines[] = '## Refresh Reasons';
            foreach ($reasons as $item) {
                $lines[] = sprintf('- [%s] %s', strtoupper($item['priority'] ?? 'medium'), $item['detail'] ?? '');
            }
            $lines[] = '';
        }

        $recommendations = $insights['recommendations'] ?? [];
        if (! empty($recommendations)) {
            $lines[] = '## Performance Recommendations';
            foreach (array_slice($recommendations, 0, 5) as $rec) {
                $lines[] = sprintf('- %s', $rec['message'] ?? $rec);
            }
        }

        return implode("\n", $lines);
    }
}
