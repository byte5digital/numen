<?php

namespace App\Services\Competitor;

use App\Models\Content;
use App\Models\ContentBrief;
use App\Models\DifferentiationAnalysis;
use App\Services\AI\LLMManager;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class DifferentiationAnalysisService
{
    private const DEFAULT_MODEL = 'claude-haiku-4-5-20251001';

    private const MAX_TOKENS = 1024;

    private const TEMPERATURE = 0.4;

    public function __construct(
        private readonly LLMManager $llm,
        private readonly SimilarityCalculator $calculator,
        private readonly ContentFingerprintService $fingerprintService,
    ) {}

    public function analyze(Content|ContentBrief $content, Collection $similarCompetitorContent): Collection
    {
        $ourFingerprint = $this->fingerprintService->fingerprint($content);
        $spaceId = $content->space_id;
        $results = collect();

        foreach ($similarCompetitorContent as $entry) {
            $competitorItem = $entry['item'];
            $competitorFingerprint = $entry['fingerprint'];

            try {
                $similarityScore = $this->calculator->calculateSimilarity($ourFingerprint, $competitorFingerprint);
                $differentiationScore = round(max(0.0, 1.0 - $similarityScore), 6);
                $llmResult = $this->generateDifferentiationInsights($content, $competitorItem);

                $contentId = $content instanceof Content ? $content->id : null;
                $briefId = $content instanceof ContentBrief ? $content->id : null;

                $analysis = DifferentiationAnalysis::updateOrCreate(
                    [
                        'space_id' => $spaceId,
                        'content_id' => $contentId,
                        'brief_id' => $briefId,
                        'competitor_content_id' => $competitorItem->id,
                    ],
                    [
                        'similarity_score' => $similarityScore,
                        'differentiation_score' => $differentiationScore,
                        'angles' => $llmResult->angles,
                        'gaps' => $llmResult->gaps,
                        'recommendations' => $llmResult->recommendations,
                        'analyzed_at' => now(),
                    ]
                );

                $results->push($analysis);
            } catch (\Throwable $e) {
                Log::warning('DifferentiationAnalysisService: failed to analyse competitor item', [
                    'competitor_content_id' => $competitorItem->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $results;
    }

    public function enrichBrief(ContentBrief $brief, SimilarContentFinder $finder): ContentBrief
    {
        try {
            $fingerprint = $this->fingerprintService->fingerprint($brief);
            $similar = $finder->findSimilar($fingerprint, threshold: 0.25, limit: 5);

            if ($similar->isEmpty()) {
                return $brief;
            }

            $analyses = $this->analyze($brief, $similar);

            if ($analyses->isEmpty()) {
                return $brief;
            }

            $allAngles = $analyses->flatMap(fn (DifferentiationAnalysis $a) => $a->angles ?? [])->unique()->values()->all();
            $allGaps = $analyses->flatMap(fn (DifferentiationAnalysis $a) => $a->gaps ?? [])->unique()->values()->all();
            $allRecommendations = $analyses->flatMap(fn (DifferentiationAnalysis $a) => $a->recommendations ?? [])->unique()->values()->all();

            $avgDifferentiation = round($analyses->avg('differentiation_score'), 4);
            $avgSimilarity = round($analyses->avg('similarity_score'), 4);

            $existingRequirements = $brief->requirements ?? [];
            $brief->requirements = array_merge($existingRequirements, [
                'competitor_differentiation' => [
                    'competitor_count' => $similar->count(),
                    'avg_similarity_score' => $avgSimilarity,
                    'avg_differentiation_score' => $avgDifferentiation,
                    'unique_angles' => array_slice($allAngles, 0, 5),
                    'content_gaps' => array_slice($allGaps, 0, 5),
                    'differentiation_recommendations' => array_slice($allRecommendations, 0, 5),
                    'enriched_at' => now()->toIso8601String(),
                ],
            ]);

            $brief->save();

            Log::info('DifferentiationAnalysisService: brief enriched', [
                'brief_id' => $brief->id,
                'competitor_count' => $similar->count(),
                'avg_differentiation' => $avgDifferentiation,
            ]);
        } catch (\Throwable $e) {
            Log::warning('DifferentiationAnalysisService: brief enrichment failed', [
                'brief_id' => $brief->id,
                'error' => $e->getMessage(),
            ]);
        }

        return $brief;
    }

    private function generateDifferentiationInsights(
        Content|ContentBrief $ourContent,
        \App\Models\CompetitorContentItem $competitorItem
    ): DifferentiationResult {
        $ourSummary = $this->buildOurContentSummary($ourContent);
        $competitorSummary = $this->buildCompetitorSummary($competitorItem);
        $personaContext = $this->buildPersonaContext($ourContent);

        $systemPrompt = 'You are a content strategy expert. Analyse how a piece of content differs from competitor content and identify differentiation opportunities. Respond ONLY with valid JSON: {"angles":["..."],"gaps":["..."],"recommendations":["..."]}. angles = unique perspectives our content could take (2-4 items). gaps = topics/questions competitors missed (2-4 items). recommendations = specific actionable steps (2-4 items).';

        $userPrompt = "## Our Content\n{$ourSummary}\n\n## Competitor Content\n{$competitorSummary}{$personaContext}\n\nAnalyse differentiation opportunities.";

        $response = $this->llm->complete([
            'model' => self::DEFAULT_MODEL,
            'system' => $systemPrompt,
            'messages' => [['role' => 'user', 'content' => $userPrompt]],
            'max_tokens' => self::MAX_TOKENS,
            'temperature' => self::TEMPERATURE,
            '_purpose' => 'differentiation_analysis',
        ]);

        return $this->parseLLMResponse($response->content);
    }

    private function buildOurContentSummary(Content|ContentBrief $content): string
    {
        if ($content instanceof ContentBrief) {
            $keywords = implode(', ', $content->target_keywords ?? []);

            return implode("\n", array_filter([
                "Title: {$content->title}",
                $content->description ? "Description: {$content->description}" : null,
                $keywords ? "Target keywords: {$keywords}" : null,
                "Locale: {$content->target_locale}",
            ]));
        }

        $version = $content->currentVersion;
        $title = ($version !== null) ? $version->title : $content->slug;
        $excerpt = ($version !== null) ? ($version->excerpt ?? '') : '';
        $body = substr(strip_tags(($version !== null) ? $version->body : ''), 0, 500);

        return implode("\n", array_filter([
            "Title: {$title}",
            $excerpt ? "Excerpt: {$excerpt}" : null,
            $body ? "Body preview: {$body}" : null,
        ]));
    }

    private function buildCompetitorSummary(\App\Models\CompetitorContentItem $item): string
    {
        $body = substr(strip_tags($item->body ?? ''), 0, 500);

        return implode("\n", array_filter([
            $item->title ? "Title: {$item->title}" : null,
            $item->excerpt ? "Excerpt: {$item->excerpt}" : null,
            $body ? "Body preview: {$body}" : null,
            $item->external_url ? "URL: {$item->external_url}" : null,
        ]));
    }

    private function buildPersonaContext(Content|ContentBrief $content): string
    {
        if (! $content instanceof ContentBrief || $content->persona_id === null) {
            return '';
        }

        $persona = $content->persona;

        if ($persona === null) {
            return '';
        }

        return "

## Persona Context
Name: {$persona->name}
";
    }

    private function parseLLMResponse(string $raw): DifferentiationResult
    {
        $json = preg_replace('/^```(?:json)?\s*|\s*```$/m', '', trim($raw));
        $decoded = json_decode($json ?? '', true);

        if (! is_array($decoded)) {
            Log::warning('DifferentiationAnalysisService: LLM returned invalid JSON', ['raw' => substr($raw, 0, 200)]);

            return new DifferentiationResult(
                similarityScore: 0.0,
                differentiationScore: 1.0,
                angles: [],
                gaps: [],
                recommendations: [],
            );
        }

        return new DifferentiationResult(
            similarityScore: 0.0,
            differentiationScore: 1.0,
            angles: array_values(array_filter((array) ($decoded['angles'] ?? []), 'is_string')),
            gaps: array_values(array_filter((array) ($decoded['gaps'] ?? []), 'is_string')),
            recommendations: array_values(array_filter((array) ($decoded['recommendations'] ?? []), 'is_string')),
        );
    }
}
