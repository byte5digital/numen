<?php

namespace App\Services\Taxonomy;

use App\Models\Content;
use App\Models\TaxonomyTerm;
use App\Models\Vocabulary;
use App\Services\AI\LLMManager;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class TaxonomyCategorizationService
{
    public function __construct(
        private readonly LLMManager $llm,
        private readonly TaxonomyService $taxonomy,
    ) {}

    /**
     * Analyze content and suggest taxonomy terms.
     *
     * @param  string|null  $vocabularyId  Limit to a specific vocabulary (null = all)
     * @return array<int, array{term: TaxonomyTerm, confidence: float}>
     */
    public function suggestTerms(Content $content, ?string $vocabularyId = null): array
    {
        $vocabularies = $this->loadVocabularies($content, $vocabularyId);

        if ($vocabularies->isEmpty()) {
            Log::info('TaxonomyCategorizationService: no vocabularies found', [
                'content_id' => $content->id,
                'space_id' => $content->space_id,
            ]);

            return [];
        }

        $termMap = $this->buildTermMap($vocabularies);

        if (empty($termMap)) {
            return [];
        }

        $prompt = $this->buildCategorizationPrompt($content, $termMap);

        try {
            $model = config('numen.taxonomy.categorization_model', 'claude-haiku-4-5-20251001');
            $provider = config('numen.taxonomy.categorization_provider', 'anthropic');

            $response = $this->llm->complete([
                'model' => "{$provider}:{$model}",
                'max_tokens' => 1024,
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => $prompt,
                    ],
                ],
            ]);

            return $this->parseCategorizationResponse($response->content, $termMap);
        } catch (\Throwable $e) {
            Log::warning('TaxonomyCategorizationService: LLM categorization failed', [
                'content_id' => $content->id,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Auto-assign terms to content based on AI analysis.
     * Only assigns terms above the confidence threshold.
     *
     * @param  string|null  $vocabularyId  Limit to a specific vocabulary
     * @return array<int, array{term: TaxonomyTerm, confidence: float}>
     */
    public function autoAssign(
        Content $content,
        float $confidenceThreshold = 0.7,
        ?string $vocabularyId = null
    ): array {
        $suggestions = $this->suggestTerms($content, $vocabularyId);

        $maxTerms = (int) config('numen.taxonomy.auto_assign_max_terms', 5);

        $qualified = array_filter(
            $suggestions,
            fn (array $s): bool => $s['confidence'] >= $confidenceThreshold
        );

        // Sort by confidence descending and limit
        usort($qualified, fn (array $a, array $b): int => $b['confidence'] <=> $a['confidence']);
        $qualified = array_slice($qualified, 0, $maxTerms);

        if (! empty($qualified)) {
            $assignments = array_map(fn (array $s): array => [
                'term_id' => $s['term']->id,
                'auto_assigned' => true,
                'confidence' => $s['confidence'],
                'sort_order' => 0,
            ], $qualified);

            $this->taxonomy->assignTerms($content, $assignments);

            Log::info('TaxonomyCategorizationService: auto-assigned terms', [
                'content_id' => $content->id,
                'terms' => array_map(fn (array $s): string => $s['term']->name, $qualified),
            ]);
        }

        return array_values($qualified);
    }

    /**
     * Build the LLM prompt for categorization.
     *
     * @param  array<string, array{term: TaxonomyTerm, vocabulary: string, path: string}>  $termMap
     */
    private function buildCategorizationPrompt(Content $content, array $termMap): string
    {
        $version = $content->currentVersion;
        $title = $version !== null ? $version->title : $content->slug;
        $contentBody = $version !== null ? $version->body : '';
        if (strlen($contentBody) > 3000) {
            $contentBody = substr($contentBody, 0, 3000).'...';
        }

        $termList = '';
        foreach ($termMap as $termId => $entry) {
            $termList .= sprintf(
                "  - ID: %s | Vocabulary: %s | Name: %s | Path: %s\n",
                $termId,
                $entry['vocabulary'],
                $entry['term']->name,
                $entry['path'],
            );
        }

        return <<<PROMPT
You are a content categorization assistant. Analyze the following content and assign it to the most relevant taxonomy terms from the provided list.

## Content to Categorize

Title: {$title}

Body:
{$contentBody}

## Available Taxonomy Terms

{$termList}

## Instructions

Return a JSON array of term assignments with confidence scores. Only include terms that genuinely apply to the content.

Format:
```json
[
  {"term_id": "<uuid>", "confidence": 0.95},
  {"term_id": "<uuid>", "confidence": 0.80}
]
```

Rules:
- confidence must be between 0.0 and 1.0
- only include terms with confidence >= 0.5
- maximum 10 suggestions
- return ONLY the JSON array, no other text
PROMPT;
    }

    /**
     * Parse the LLM response into term + confidence pairs.
     *
     * @param  array<string, array{term: TaxonomyTerm, vocabulary: string, path: string}>  $termMap
     * @return array<int, array{term: TaxonomyTerm, confidence: float}>
     */
    private function parseCategorizationResponse(string $responseContent, array $termMap): array
    {
        // Extract JSON from response (may be wrapped in markdown code fences)
        $json = $responseContent;
        if (preg_match('/```(?:json)?\s*([\s\S]+?)\s*```/', $responseContent, $matches)) {
            $json = $matches[1];
        }

        try {
            /** @var array<int, array{term_id: string, confidence: mixed}> $parsed */
            $parsed = json_decode(trim($json), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            Log::warning('TaxonomyCategorizationService: failed to parse LLM response as JSON', [
                'response' => substr($responseContent, 0, 200),
            ]);

            return [];
        }

        $results = [];
        foreach ($parsed as $item) {
            $termId = $item['term_id'] ?? null;
            $confidence = isset($item['confidence']) ? (float) $item['confidence'] : 0.0;

            if (! $termId || ! isset($termMap[$termId])) {
                continue;
            }

            $confidence = max(0.0, min(1.0, $confidence));
            $results[] = [
                'term' => $termMap[$termId]['term'],
                'confidence' => $confidence,
            ];
        }

        return $results;
    }

    /**
     * Load vocabularies (and their terms) for the content's space.
     *
     * @return Collection<int, Vocabulary>
     */
    private function loadVocabularies(Content $content, ?string $vocabularyId): Collection
    {
        $query = Vocabulary::forSpace($content->space_id)->with('terms');

        if ($vocabularyId !== null) {
            $query->where('id', $vocabularyId);
        }

        return $query->get();
    }

    /**
     * Build a flat term map indexed by term ID for fast lookup.
     *
     * @param  Collection<int, Vocabulary>  $vocabularies
     * @return array<string, array{term: TaxonomyTerm, vocabulary: string, path: string}>
     */
    private function buildTermMap(Collection $vocabularies): array
    {
        $map = [];

        foreach ($vocabularies as $vocabulary) {
            foreach ($vocabulary->terms as $term) {
                $map[$term->id] = [
                    'term' => $term,
                    'vocabulary' => $vocabulary->name,
                    'path' => $term->path ?? $term->name,
                ];
            }
        }

        return $map;
    }
}
