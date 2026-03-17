<?php

declare(strict_types=1);

namespace App\Services\Migration;

use App\Services\AI\LLMManager;
use Illuminate\Support\Facades\Log;

/**
 * Suggests field mappings between a source CMS schema and Numen content types.
 *
 * Primary strategy: AI-powered mapping via LLMManager.
 * Fallback: rule-based mapping using field type + name similarity.
 */
class AiFieldMappingService
{
    public function __construct(
        private readonly LLMManager $llm,
    ) {}

    /**
     * @param  array{key: string, label: string, fields: list<array{name: string, type: string, required: bool}>}  $sourceType
     * @param  array{key: string, label: string, fields: list<array{name: string, type: string, required: bool}>}  $numenType
     * @return list<array{source_field: string, target_field: string|null, source_type: string, target_type: string|null, confidence: float, requires_transform: bool}>
     */
    public function suggest(array $sourceType, array $numenType): array
    {
        try {
            return $this->suggestViaAi($sourceType, $numenType);
        } catch (\Throwable $e) {
            Log::warning('AI field mapping unavailable, falling back to rule-based', [
                'source_type' => $sourceType['key'] ?? 'unknown',
                'error' => $e->getMessage(),
            ]);

            return $this->suggestRuleBased($sourceType, $numenType);
        }
    }

    /**
     * @param  list<array{key: string, label: string, fields: list<array{name: string, type: string, required: bool}>}>  $sourceTypes
     * @param  list<array{key: string, label: string, fields: list<array{name: string, type: string, required: bool}>}>  $numenTypes
     * @return list<array{source_type: string, numen_type: string|null, mappings: list<array{source_field: string, target_field: string|null, source_type: string, target_type: string|null, confidence: float, requires_transform: bool}>}>
     */
    public function suggestAll(array $sourceTypes, array $numenTypes): array
    {
        $results = [];

        foreach ($sourceTypes as $sourceType) {
            $bestMatch = $this->findBestNumenType($sourceType, $numenTypes);
            $numenType = $bestMatch ?? $numenTypes[0] ?? null;

            if ($numenType === null) {
                $results[] = [
                    'source_type' => $sourceType['key'],
                    'numen_type' => null,
                    'mappings' => [],
                ];

                continue;
            }

            $results[] = [
                'source_type' => $sourceType['key'],
                'numen_type' => $numenType['key'],
                'mappings' => $this->suggest($sourceType, $numenType),
            ];
        }

        return $results;
    }

    /**
     * @return list<array{source_field: string, target_field: string|null, source_type: string, target_type: string|null, confidence: float, requires_transform: bool}>
     */
    private function suggestViaAi(array $sourceType, array $numenType): array
    {
        $sourceFields = json_encode($sourceType['fields'], JSON_THROW_ON_ERROR);
        $targetFields = json_encode($numenType['fields'], JSON_THROW_ON_ERROR);

        $system = 'You are a CMS migration assistant. Given source and target content type fields, '
            .'suggest the best field mappings. Respond ONLY with a JSON array. '
            .'Each element: {"source_field":"...","target_field":"...","confidence":0.0-1.0,"requires_transform":true/false}. '
            .'If no good target exists, set target_field to null and confidence to 0. '
            .'Do not include any text outside the JSON array.';

        $userPrompt = "Source type: {$sourceType['key']} (label: {$sourceType['label']})\n"
            ."Source fields:\n{$sourceFields}\n\n"
            ."Target type: {$numenType['key']} (label: {$numenType['label']})\n"
            ."Target fields:\n{$targetFields}";

        $response = $this->llm->complete([
            'model' => 'claude-haiku-4-5-20251001',
            'system' => $system,
            'messages' => [['role' => 'user', 'content' => $userPrompt]],
            'max_tokens' => 2048,
            'temperature' => 0.1,
            '_purpose' => 'migration_field_mapping',
        ]);

        return $this->parseAiResponse($response->content, $sourceType, $numenType);
    }

    /**
     * @return list<array{source_field: string, target_field: string|null, source_type: string, target_type: string|null, confidence: float, requires_transform: bool}>
     */
    private function parseAiResponse(string $content, array $sourceType, array $numenType): array
    {
        $content = trim($content);

        // Strip markdown code fences if present
        if (str_starts_with($content, '```')) {
            $content = preg_replace('/^```(?:json)?\s*/', '', $content) ?? $content;
            $content = preg_replace('/\s*```$/', '', $content) ?? $content;
        }

        $decoded = json_decode($content, true);
        if (! is_array($decoded)) {
            throw new \RuntimeException('AI returned invalid JSON for field mapping');
        }

        $sourceFieldNames = array_column($sourceType['fields'], 'name');
        $sourceFieldTypes = [];
        foreach ($sourceType['fields'] as $f) {
            $sourceFieldTypes[$f['name']] = $f['type'];
        }

        $numenFieldTypes = [];
        foreach ($numenType['fields'] as $f) {
            $numenFieldTypes[$f['name']] = $f['type'];
        }

        $result = [];
        foreach ($decoded as $mapping) {
            if (! is_array($mapping) || ! isset($mapping['source_field'])) {
                continue;
            }

            $sourceField = (string) $mapping['source_field'];
            $targetField = isset($mapping['target_field']) ? (string) $mapping['target_field'] : null;
            $confidence = min(1.0, max(0.0, (float) ($mapping['confidence'] ?? 0.5)));
            $requiresTransform = (bool) ($mapping['requires_transform'] ?? false);

            $result[] = [
                'source_field' => $sourceField,
                'target_field' => $targetField,
                'source_type' => $sourceFieldTypes[$sourceField] ?? 'string',
                'target_type' => $targetField ? ($numenFieldTypes[$targetField] ?? 'string') : null,
                'confidence' => $confidence,
                'requires_transform' => $requiresTransform,
            ];
        }

        // Add unmapped source fields
        $mappedSourceFields = array_column($result, 'source_field');
        foreach ($sourceFieldNames as $name) {
            if (! in_array($name, $mappedSourceFields, true)) {
                $result[] = [
                    'source_field' => $name,
                    'target_field' => null,
                    'source_type' => $sourceFieldTypes[$name] ?? 'string',
                    'target_type' => null,
                    'confidence' => 0.0,
                    'requires_transform' => false,
                ];
            }
        }

        return $result;
    }

    /**
     * Rule-based fallback when AI is unavailable.
     *
     * @return list<array{source_field: string, target_field: string|null, source_type: string, target_type: string|null, confidence: float, requires_transform: bool}>
     */
    public function suggestRuleBased(array $sourceType, array $numenType): array
    {
        $numenFields = $numenType['fields'] ?? [];
        $usedTargets = [];
        $result = [];

        foreach (($sourceType['fields'] ?? []) as $sourceField) {
            $match = $this->findBestFieldMatch($sourceField, $numenFields, $usedTargets);

            if ($match !== null) {
                $usedTargets[] = $match['name'];
                $sameType = $sourceField['type'] === $match['type'];

                $result[] = [
                    'source_field' => $sourceField['name'],
                    'target_field' => $match['name'],
                    'source_type' => $sourceField['type'],
                    'target_type' => $match['type'],
                    'confidence' => $match['_score'],
                    'requires_transform' => ! $sameType,
                ];
            } else {
                $result[] = [
                    'source_field' => $sourceField['name'],
                    'target_field' => null,
                    'source_type' => $sourceField['type'],
                    'target_type' => null,
                    'confidence' => 0.0,
                    'requires_transform' => false,
                ];
            }
        }

        return $result;
    }

    /** @return array{name: string, type: string, required: bool, _score: float}|null */
    private function findBestFieldMatch(array $sourceField, array $numenFields, array $usedTargets): ?array
    {
        $bestScore = 0.0;
        $bestMatch = null;

        foreach ($numenFields as $numenField) {
            if (in_array($numenField['name'], $usedTargets, true)) {
                continue;
            }

            $score = $this->fieldSimilarityScore($sourceField, $numenField);

            if ($score > $bestScore && $score >= 0.3) {
                $bestScore = $score;
                $bestMatch = array_merge($numenField, ['_score' => round($score, 2)]);
            }
        }

        return $bestMatch;
    }

    private function fieldSimilarityScore(array $source, array $target): float
    {
        $score = 0.0;
        $sourceName = $this->normaliseName($source['name']);
        $targetName = $this->normaliseName($target['name']);

        if ($sourceName === $targetName) {
            $score += 0.6;
        } else {
            similar_text($sourceName, $targetName, $percent);
            $score += ($percent / 100) * 0.4;

            if ($this->areSynonyms($sourceName, $targetName)) {
                $score += 0.3;
            }
        }

        if ($source['type'] === $target['type']) {
            $score += 0.4;
        } elseif ($this->areCompatibleTypes($source['type'], $target['type'])) {
            $score += 0.2;
        }

        return min(1.0, $score);
    }

    private function normaliseName(string $name): string
    {
        $snake = strtolower((string) preg_replace('/[A-Z]/', '_$0', $name));
        $snake = str_replace(['-', ' '], '_', $snake);
        $snake = ltrim($snake, '_');

        return (string) preg_replace('/^(field_|meta_|custom_)/', '', $snake);
    }

    /** @var array<string, list<string>> */
    private array $synonymGroups = [
        'title' => ['title', 'name', 'heading', 'subject', 'headline'],
        'body' => ['body', 'content', 'html', 'text', 'description', 'richtext'],
        'summary' => ['summary', 'excerpt', 'intro', 'teaser', 'abstract', 'description'],
        'image' => ['image', 'featured_image', 'feature_image', 'featured_media', 'thumbnail', 'cover', 'hero_image', 'photo'],
        'slug' => ['slug', 'url_slug', 'handle', 'permalink'],
        'date' => ['date', 'published_at', 'created_at', 'publish_date', 'published', 'created'],
        'author' => ['author', 'authors', 'writer', 'creator', 'created_by'],
        'tags' => ['tags', 'keywords', 'labels'],
        'categories' => ['categories', 'category', 'topics', 'sections'],
        'status' => ['status', 'state', 'visibility', 'published'],
    ];

    private function areSynonyms(string $a, string $b): bool
    {
        foreach ($this->synonymGroups as $synonyms) {
            if (in_array($a, $synonyms, true) && in_array($b, $synonyms, true)) {
                return true;
            }
        }

        return false;
    }

    /** @var array<string, list<string>> */
    private array $compatibleTypes = [
        'string' => ['string', 'richtext', 'markdown', 'html', 'enum'],
        'richtext' => ['richtext', 'html', 'markdown', 'string'],
        'html' => ['html', 'richtext', 'markdown', 'string'],
        'markdown' => ['markdown', 'richtext', 'html', 'string'],
        'number' => ['number', 'string'],
        'date' => ['date', 'string'],
        'media' => ['media', 'string'],
        'relation' => ['relation', 'json'],
        'json' => ['json', 'relation'],
        'enum' => ['enum', 'string'],
        'boolean' => ['boolean', 'number'],
    ];

    private function areCompatibleTypes(string $a, string $b): bool
    {
        $compatA = $this->compatibleTypes[$a] ?? [$a];

        return in_array($b, $compatA, true);
    }

    /** @return array{key: string, label: string, fields: list<array{name: string, type: string, required: bool}>}|null */
    private function findBestNumenType(array $sourceType, array $numenTypes): ?array
    {
        $bestScore = 0.0;
        $bestType = null;

        $sourceKey = $this->normaliseName($sourceType['key']);
        $sourceLabel = strtolower($sourceType['label']);

        foreach ($numenTypes as $numenType) {
            $numenKey = $this->normaliseName($numenType['key']);
            $numenLabel = strtolower($numenType['label']);

            if ($sourceKey === $numenKey) {
                $score = 1.0;
            } else {
                similar_text($sourceKey, $numenKey, $keyPercent);
                similar_text($sourceLabel, $numenLabel, $labelPercent);
                $score = max($keyPercent, $labelPercent) / 100;
            }

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestType = $numenType;
            }
        }

        return $bestScore >= 0.4 ? $bestType : null;
    }
}
