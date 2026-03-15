<?php

namespace App\Services\Quality\Analyzers;

use App\Models\Content;
use App\Models\ContentVersion;
use App\Services\AI\Exceptions\AllProvidersFailedException;
use App\Services\AI\LLMManager;
use App\Services\Quality\QualityAnalyzerContract;
use App\Services\Quality\QualityDimensionResult;
use Illuminate\Support\Facades\Log;

/**
 * Predicts engagement potential using LLM analysis of:
 *   - Headline strength
 *   - Hook quality (opening sentences)
 *   - Emotional resonance
 *   - CTA effectiveness
 *   - Shareability
 *
 * Scoring weights (internal to the LLM response, equal weighted as fallback):
 *   Headline strength     20%
 *   Hook quality          20%
 *   Emotional resonance   20%
 *   CTA effectiveness     20%
 *   Shareability          20%
 */
class EngagementPredictionAnalyzer implements QualityAnalyzerContract
{
    private const DIMENSION = 'engagement_prediction';

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

        $context = $this->buildContext($content);

        try {
            return $this->analyzeWithLLM($text, $context);
        } catch (AllProvidersFailedException $e) {
            Log::warning('EngagementPredictionAnalyzer: LLM unavailable, using heuristic fallback.', [
                'error' => $e->getMessage(),
            ]);

            return $this->heuristicFallback($text, 'LLM unavailable — using heuristic fallback.');
        } catch (\Throwable $e) {
            Log::warning('EngagementPredictionAnalyzer: unexpected error, using heuristic fallback.', [
                'error' => $e->getMessage(),
            ]);

            return $this->heuristicFallback($text, 'Analysis error — using heuristic fallback.');
        }
    }

    private function analyzeWithLLM(string $text, string $context): QualityDimensionResult
    {
        $prompts = config('quality-prompts.engagement_prediction_prompt');

        $userMessage = str_replace(
            ['{{content}}', '{{context}}'],
            [$text, $context],
            (string) $prompts['user'],
        );

        $response = $this->llm->complete([
            'model' => config('numen.quality.llm_model', 'claude-haiku-4-5-20251001'),
            'system' => (string) $prompts['system'],
            'messages' => [['role' => 'user', 'content' => $userMessage]],
            'max_tokens' => 1200,
            'temperature' => 0.3,
            '_purpose' => 'quality_engagement_prediction',
        ]);

        /** @var array<string, mixed> $data */
        $data = json_decode($response->content, true) ?? [];

        return $this->buildResultFromLLMData($data);
    }

    /** @param  array<string, mixed>  $data */
    private function buildResultFromLLMData(array $data): QualityDimensionResult
    {
        $score = isset($data['score']) ? (float) $data['score'] : 50.0;

        $headlineScore = isset($data['headline_strength']) ? (float) $data['headline_strength'] : $score;
        $hookScore = isset($data['hook_quality']) ? (float) $data['hook_quality'] : $score;
        $emotionScore = isset($data['emotional_resonance']) ? (float) $data['emotional_resonance'] : $score;
        $ctaScore = isset($data['cta_effectiveness']) ? (float) $data['cta_effectiveness'] : $score;
        $shareScore = isset($data['shareability']) ? (float) $data['shareability'] : $score;

        $items = [];
        $factors = isset($data['factors']) && is_array($data['factors']) ? $data['factors'] : [];

        foreach ($factors as $factor) {
            if (! is_array($factor)) {
                continue;
            }
            $factorScore = isset($factor['score']) ? (float) $factor['score'] : 50.0;
            if ($factorScore < 60) {
                $item = [
                    'type' => $factorScore < 40 ? 'error' : 'warning',
                    'message' => sprintf(
                        '[%s] %s',
                        (string) ($factor['factor'] ?? 'Unknown'),
                        (string) ($factor['observation'] ?? ''),
                    ),
                ];
                if (! empty($factor['suggestion'])) {
                    $item['suggestion'] = (string) $factor['suggestion'];
                }
                $items[] = $item;
            }
        }

        $metadata = [
            'headline_strength' => $headlineScore,
            'hook_quality' => $hookScore,
            'emotional_resonance' => $emotionScore,
            'cta_effectiveness' => $ctaScore,
            'shareability' => $shareScore,
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

        $score = 55.0;
        $metadata = ['source' => 'heuristic'];

        // Headline strength: first line length and power words
        $lines = array_filter(explode("\n", $text));
        $headline = trim((string) (reset($lines) ?: ''));
        $headlineWords = str_word_count($headline);
        $headlineScore = 50.0;

        if ($headlineWords >= 6 && $headlineWords <= 12) {
            $headlineScore = 70.0;
        } elseif ($headlineWords < 4) {
            $headlineScore = 35.0;
            $items[] = [
                'type' => 'warning',
                'message' => 'Headline is very short and may not attract readers.',
                'suggestion' => 'Aim for 6–12 words in your headline.',
            ];
        } elseif ($headlineWords > 16) {
            $headlineScore = 45.0;
            $items[] = [
                'type' => 'warning',
                'message' => 'Headline is long and may lose reader attention.',
                'suggestion' => 'Trim to 6–12 words for best engagement.',
            ];
        }

        $powerWords = ['how', 'why', 'best', 'top', 'proven', 'secret', 'ultimate', 'easy', 'free', 'now', 'new'];
        $headlineLower = strtolower($headline);
        $powerWordHits = count(array_filter($powerWords, fn ($w) => str_contains($headlineLower, $w)));
        if ($powerWordHits > 0) {
            $headlineScore = min(100, $headlineScore + 10);
        }

        // Hook quality: first 2 sentences
        $sentences = preg_split('/(?<=[.!?])\s+/', $text) ?: [];
        $hook = implode(' ', array_slice($sentences, 0, 2));
        $hookScore = 50.0;
        if (str_contains($hook, '?') || preg_match('/\b(imagine|discover|did you know|are you)\b/i', $hook)) {
            $hookScore = 72.0;
        }

        // CTA detection
        $hasCta = (bool) preg_match('/\b(click|sign up|subscribe|learn more|get started|download|try|contact|buy|shop|start|join)\b/i', $text);
        $ctaScore = $hasCta ? 70.0 : 30.0;
        if (! $hasCta) {
            $items[] = [
                'type' => 'warning',
                'message' => 'No clear call-to-action detected.',
                'suggestion' => 'Add a CTA to guide readers on what to do next.',
            ];
        }

        // Shareability: emotional keywords
        $emotionalWords = ['love', 'hate', 'amazing', 'shocking', 'incredible', 'inspiring', 'surprising', 'powerful', 'beautiful', 'terrible'];
        $textLower = strtolower($text);
        $emotionHits = count(array_filter($emotionalWords, fn ($w) => str_contains($textLower, $w)));
        $emotionScore = min(100, 40 + ($emotionHits * 8));
        $shareScore = (($headlineScore + $hookScore + $emotionScore) / 3);

        $score = ($headlineScore * 0.20) + ($hookScore * 0.20) + ($emotionScore * 0.20) + ($ctaScore * 0.20) + ($shareScore * 0.20);

        $metadata['headline_strength'] = round($headlineScore, 1);
        $metadata['hook_quality'] = round($hookScore, 1);
        $metadata['emotional_resonance'] = round($emotionScore, 1);
        $metadata['cta_effectiveness'] = round($ctaScore, 1);
        $metadata['shareability'] = round($shareScore, 1);

        return QualityDimensionResult::make($score, $items, $metadata);
    }

    private function buildContext(Content $content): string
    {
        $parts = [];

        if ($content->locale) {
            $parts[] = 'Locale: '.$content->locale;
        }

        if ($content->contentType !== null) {
            $parts[] = 'Content type: '.$content->contentType->name;
        }

        return empty($parts) ? 'No additional context.' : implode("\n", $parts);
    }

    private function extractText(ContentVersion $version): string
    {
        return strip_tags((string) $version->body);
    }
}
