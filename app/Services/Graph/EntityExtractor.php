<?php

namespace App\Services\Graph;

use App\Models\Content;
use App\Services\AI\CostTracker;
use App\Services\AI\LLMManager;
use Illuminate\Support\Facades\Log;

/**
 * Extracts named entities and concepts from Content using an LLM haiku-class model.
 *
 * Returns an array of entity records:
 *   [['entity' => string, 'type' => string, 'weight' => float], ...]
 *
 * Valid types: concept | topic | person | product | place
 */
class EntityExtractor
{
    private const HAIKU_MODEL = 'claude-haiku-4-5-20251001';

    private const SYSTEM_PROMPT = <<<'PROMPT'
You are an entity and concept extraction assistant. Given the title, excerpt, and beginning of an article's body, you will identify and return the most important named entities and concepts.

Return ONLY a valid JSON array — no prose, no markdown code fences. Each element must be an object with:
- "entity": string (the entity or concept name, 1–5 words)
- "type": one of "concept" | "topic" | "person" | "product" | "place"
- "weight": float between 0.0 and 1.0 reflecting relative importance

Extract between 5 and 20 entities. Rank them by relevance (weight). If no clear entities exist, return an empty array [].
PROMPT;

    public function __construct(
        private readonly LLMManager $llmManager,
        private readonly CostTracker $costTracker,
    ) {}

    /**
     * Extract entities from a Content model.
     *
     * @return array<int, array{entity: string, type: string, weight: float}>
     */
    public function extract(Content $content): array
    {
        $version = $content->currentVersion;

        if ($version === null) {
            Log::debug('EntityExtractor: no current version for content', ['content_id' => $content->id]);

            return [];
        }

        $title = $version->title ?? '';
        $excerpt = $version->excerpt ?? '';
        $body = mb_substr(strip_tags($version->body ?? ''), 0, 2000);

        $userMessage = implode("\n\n", array_filter([
            $title ? "TITLE:\n{$title}" : null,
            $excerpt ? "EXCERPT:\n{$excerpt}" : null,
            $body ? "BODY (first 2000 chars):\n{$body}" : null,
        ]));

        if (trim($userMessage) === '') {
            return [];
        }

        try {
            $response = $this->llmManager->complete([
                'model' => self::HAIKU_MODEL,
                'system' => self::SYSTEM_PROMPT,
                'messages' => [
                    ['role' => 'user', 'content' => $userMessage],
                ],
                'max_tokens' => 1024,
                'temperature' => 0.2,
                '_purpose' => 'knowledge_graph_entity_extraction',
            ]);

            $cost = $this->costTracker->calculateCost(
                self::HAIKU_MODEL,
                $response->inputTokens,
                $response->outputTokens,
            );
            $this->costTracker->recordUsage($cost, $content->space_id);

            return $this->parseResponse($response->content);
        } catch (\Throwable $e) {
            Log::warning('EntityExtractor: LLM call failed', [
                'content_id' => $content->id,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Parse the LLM JSON response into a typed array.
     *
     * @return array<int, array{entity: string, type: string, weight: float}>
     */
    private function parseResponse(string $raw): array
    {
        $raw = trim($raw);

        // Strip accidental markdown code fences
        $raw = preg_replace('/^```(?:json)?\s*/i', '', $raw) ?? $raw;
        $raw = preg_replace('/\s*```$/', '', $raw) ?? $raw;

        /** @var mixed $decoded */
        $decoded = json_decode(trim($raw), true);

        if (! is_array($decoded)) {
            Log::warning('EntityExtractor: failed to parse JSON response', ['raw' => substr($raw, 0, 500)]);

            return [];
        }

        $validTypes = ['concept', 'topic', 'person', 'product', 'place'];
        $results = [];

        foreach ($decoded as $item) {
            if (! is_array($item)) {
                continue;
            }

            $entity = isset($item['entity']) && is_string($item['entity']) ? trim($item['entity']) : '';
            $type = isset($item['type']) && in_array($item['type'], $validTypes, true) ? $item['type'] : 'concept';
            $weight = isset($item['weight']) && is_numeric($item['weight'])
                ? (float) max(0.0, min(1.0, $item['weight']))
                : 0.5;

            if ($entity === '') {
                continue;
            }

            $results[] = ['entity' => $entity, 'type' => $type, 'weight' => $weight];
        }

        return $results;
    }
}
