<?php

namespace App\Services\Quality\Analyzers;

use App\Models\Content;
use App\Models\ContentGraphNode;
use App\Models\ContentVersion;
use App\Services\AI\Exceptions\AllProvidersFailedException;
use App\Services\AI\LLMManager;
use App\Services\Quality\QualityAnalyzerContract;
use App\Services\Quality\QualityDimensionResult;
use Illuminate\Support\Facades\Log;

/**
 * Evaluates factual accuracy by:
 *   1. Extracting claims from content via LLM
 *   2. Cross-referencing with Knowledge Graph entities (ContentGraphNode) when available
 *   3. Scoring based on verifiable claims ratio, source citations, and entity consistency
 *
 * Falls back to regex-based heuristics when LLM is unavailable.
 */
class FactualAccuracyAnalyzer implements QualityAnalyzerContract
{
    private const DIMENSION = 'factual_accuracy';

    private const WEIGHT = 0.20;

    public function __construct(
        private readonly LLMManager $llm,
    ) {}

    public function getDimension(): string
    {
        return self::DIMENSION;
    }

    public function getWeight(): float
    {
        return self::WEIGHT;
    }

    public function analyze(Content $content): QualityDimensionResult
    {
        $version = $content->currentVersion ?? $content->draftVersion;
        if ($version === null) {
            return QualityDimensionResult::make(0, [
                ['type' => 'error', 'message' => 'No content version available.'],
            ]);
        }

        $text = $this->extractText($version);
        if (trim($text) === '') {
            return QualityDimensionResult::make(0, [
                ['type' => 'error', 'message' => 'Content body is empty.'],
            ]);
        }

        $graphContext = $this->buildGraphContext($content);

        try {
            return $this->analyzeWithLLM($text, $graphContext);
        } catch (AllProvidersFailedException $e) {
            Log::warning('FactualAccuracyAnalyzer: LLM unavailable, using heuristic fallback.', [
                'error' => $e->getMessage(),
            ]);

            return $this->heuristicFallback($text, 'LLM unavailable — using heuristic fallback.');
        } catch (\Throwable $e) {
            Log::warning('FactualAccuracyAnalyzer: unexpected error, using heuristic fallback.', [
                'error' => $e->getMessage(),
            ]);

            return $this->heuristicFallback($text, 'Analysis error — using heuristic fallback.');
        }
    }

    private function analyzeWithLLM(string $text, string $graphContext): QualityDimensionResult
    {
        $prompts = config('quality-prompts.factual_accuracy_prompt');

        $userMessage = str_replace(
            ['{{content}}', '{{context}}'],
            [$text, $graphContext],
            (string) $prompts['user'],
        );

        $response = $this->llm->complete([
            'model' => config('numen.quality.llm_model', 'claude-haiku-4-5-20251001'),
            'system' => (string) $prompts['system'],
            'messages' => [['role' => 'user', 'content' => $userMessage]],
            'max_tokens' => 1500,
            'temperature' => 0.1,
            '_purpose' => 'quality_factual_accuracy',
        ]);

        /** @var array<string, mixed> $data */
        $data = json_decode($response->content, true) ?? [];

        return $this->buildResultFromLLMData($data);
    }

    /** @param  array<string, mixed>  $data */
    private function buildResultFromLLMData(array $data): QualityDimensionResult
    {
        $score = isset($data['score']) ? (float) $data['score'] : 50.0;
        $hasCitations = isset($data['has_source_citations']) ? (bool) $data['has_source_citations'] : false;
        $verifiableRatio = isset($data['verifiable_claims_ratio']) ? (float) $data['verifiable_claims_ratio'] : 0.5;

        $items = [];
        $claims = isset($data['claims']) && is_array($data['claims']) ? $data['claims'] : [];

        foreach ($claims as $claim) {
            if (! is_array($claim)) {
                continue;
            }
            $isVerifiable = isset($claim['verifiable']) ? (bool) $claim['verifiable'] : true;
            $issue = ! empty($claim['issue']) ? (string) $claim['issue'] : null;

            if (! $isVerifiable || $issue !== null) {
                $item = [
                    'type' => $isVerifiable ? 'warning' : 'error',
                    'message' => $issue ?? ('Unverifiable claim: '.(string) ($claim['claim'] ?? '')),
                ];
                if (! empty($claim['suggestion'])) {
                    $item['suggestion'] = (string) $claim['suggestion'];
                }
                $items[] = $item;
            }
        }

        if (! $hasCitations && count($claims) > 0) {
            $items[] = [
                'type' => 'warning',
                'message' => 'No source citations found. Adding references improves credibility.',
                'suggestion' => 'Include links or references to authoritative sources.',
            ];
        }

        $metadata = [
            'verifiable_claims_ratio' => $verifiableRatio,
            'has_source_citations' => $hasCitations,
            'total_claims' => count($claims),
            'summary' => (string) ($data['summary'] ?? ''),
            'source' => 'llm',
        ];

        return QualityDimensionResult::make($score, $items, $metadata);
    }

    private function heuristicFallback(string $text, string $reason): QualityDimensionResult
    {
        $items = [
            [
                'type' => 'info',
                'message' => $reason,
            ],
        ];

        $score = 60.0;

        // Check for source citations (URLs, "according to", "source:", etc.)
        $hasCitations = (bool) preg_match('/https?:\/\/|according to|source:|cited|reference/i', $text);
        if (! $hasCitations) {
            $score -= 10;
            $items[] = [
                'type' => 'warning',
                'message' => 'No source citations or references detected.',
                'suggestion' => 'Add links or references to authoritative sources.',
            ];
        }

        // Check for numbers/dates (common factual claims)
        $numberMatches = preg_match_all('/\b\d{4}\b|\d+%|\$[\d,]+|\b\d+\s*(million|billion|thousand)\b/i', $text, $_m);
        if ($numberMatches > 5) {
            $items[] = [
                'type' => 'info',
                'message' => "Detected {$numberMatches} numeric claims. Consider verifying each with a source.",
            ];
            if (! $hasCitations) {
                $score -= 10;
            }
        }

        // Check for named entities (capitalized multi-word phrases)
        $entityMatches = preg_match_all('/\b[A-Z][a-z]+ [A-Z][a-z]+\b/', $text, $_m2);
        if ($entityMatches > 3 && ! $hasCitations) {
            $items[] = [
                'type' => 'warning',
                'message' => "Detected {$entityMatches} potential named entities without citations.",
                'suggestion' => 'Verify named entities and add references where appropriate.',
            ];
        }

        return QualityDimensionResult::make($score, $items, [
            'source' => 'heuristic',
            'has_source_citations' => $hasCitations,
            'numeric_claims_found' => $numberMatches,
            'named_entities_found' => $entityMatches,
        ]);
    }

    private function buildGraphContext(Content $content): string
    {
        try {
            /** @var ContentGraphNode|null $node */
            $node = ContentGraphNode::where('content_id', $content->id)->first();

            if ($node === null) {
                return 'No Knowledge Graph data available for this content.';
            }

            $labels = $node->entity_labels ?? [];
            $meta = $node->node_metadata ?? [];

            $parts = ['## Knowledge Graph Entities'];

            if (! empty($labels)) {
                $parts[] = 'Entity labels: '.implode(', ', $labels);
            }

            if (! empty($meta)) {
                $parts[] = 'Node metadata: '.json_encode($meta, JSON_PRETTY_PRINT);
            }

            return implode("\n", $parts);
        } catch (\Throwable) {
            return 'Knowledge Graph unavailable.';
        }
    }

    private function extractText(ContentVersion $version): string
    {
        return strip_tags((string) $version->body);
    }
}
