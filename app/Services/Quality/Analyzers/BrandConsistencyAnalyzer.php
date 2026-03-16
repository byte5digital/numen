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
 * Evaluates content against the assigned Persona's brand voice and guidelines using LLM.
 *
 * Falls back to basic heuristics when:
 * - No Persona is assigned to the content's pipeline
 * - LLM is unavailable / all providers fail
 *
 * Scoring weights:
 *   Tone consistency        35%
 *   Vocabulary alignment    35%
 *   Brand voice adherence   30%
 */
class BrandConsistencyAnalyzer implements QualityAnalyzerContract
{
    private const DIMENSION = 'brand_consistency';

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

        $persona = $this->resolvePersona($content);

        if ($persona === null) {
            return $this->heuristicFallback($text, 'No persona assigned — using heuristic fallback.');
        }

        try {
            return $this->analyzWithLLM($text, $persona->system_prompt, $persona->voice_guidelines);
        } catch (AllProvidersFailedException $e) {
            Log::warning('BrandConsistencyAnalyzer: LLM unavailable, using heuristic fallback.', [
                'error' => $e->getMessage(),
            ]);

            return $this->heuristicFallback($text, 'LLM unavailable — using heuristic fallback.');
        } catch (\Throwable $e) {
            Log::warning('BrandConsistencyAnalyzer: unexpected error, using heuristic fallback.', [
                'error' => $e->getMessage(),
            ]);

            return $this->heuristicFallback($text, 'Analysis error — using heuristic fallback.');
        }
    }

    /** @param  array<string, mixed>|null  $voiceGuidelines */
    private function analyzWithLLM(string $text, string $systemPrompt, ?array $voiceGuidelines): QualityDimensionResult
    {
        $context = $this->buildPersonaContext($systemPrompt, $voiceGuidelines);
        $prompts = config('quality-prompts.brand_consistency_prompt');

        $userMessage = str_replace(
            ['{{content}}', '{{context}}'],
            [$text, $context],
            (string) $prompts['user'],
        );

        $response = $this->llm->complete([
            'model' => config('numen.quality.llm_model', 'claude-haiku-4-5-20251001'),
            'system' => (string) $prompts['system'],
            'messages' => [['role' => 'user', 'content' => $userMessage]],
            'max_tokens' => 1024,
            'temperature' => 0.2,
            '_purpose' => 'quality_brand_consistency',
        ]);

        /** @var array<string, mixed> $data */
        $data = json_decode($response->content, true) ?? [];

        return $this->buildResultFromLLMData($data);
    }

    /** @param  array<string, mixed>  $data */
    private function buildResultFromLLMData(array $data): QualityDimensionResult
    {
        $score = isset($data['score']) ? (float) $data['score'] : 50.0;
        $toneScore = isset($data['tone_consistency']) ? (float) $data['tone_consistency'] : $score;
        $vocabScore = isset($data['vocabulary_alignment']) ? (float) $data['vocabulary_alignment'] : $score;
        $voiceScore = isset($data['brand_voice_adherence']) ? (float) $data['brand_voice_adherence'] : $score;

        $items = [];
        $deviations = isset($data['deviations']) && is_array($data['deviations']) ? $data['deviations'] : [];

        foreach ($deviations as $deviation) {
            if (! is_array($deviation)) {
                continue;
            }
            $item = [
                'type' => (string) ($deviation['type'] ?? 'style'),
                'message' => (string) ($deviation['message'] ?? ''),
            ];
            if (! empty($deviation['suggestion'])) {
                $item['suggestion'] = (string) $deviation['suggestion'];
            }
            $items[] = $item;
        }

        $metadata = [
            'tone_consistency' => $toneScore,
            'vocabulary_alignment' => $vocabScore,
            'brand_voice_adherence' => $voiceScore,
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
        $wordCount = str_word_count($text);
        $sentenceCount = max(1, preg_match_all('/[.!?]+/', $text, $_matches));
        $avgWordsPerSentence = $wordCount / $sentenceCount;

        // Basic heuristics: penalise very long sentences and lack of structure
        $score = 70.0;

        if ($avgWordsPerSentence > 35) {
            $score -= 15;
            $items[] = [
                'type' => 'warning',
                'message' => 'Sentences are very long on average, which may hurt brand voice clarity.',
                'suggestion' => 'Aim for an average of 15–25 words per sentence.',
            ];
        } elseif ($avgWordsPerSentence > 25) {
            $score -= 7;
            $items[] = [
                'type' => 'warning',
                'message' => 'Sentences are slightly long on average.',
                'suggestion' => 'Consider breaking up complex sentences.',
            ];
        }

        // Check for first-person consistency
        $firstPerson = preg_match_all('/\bI\b|\bwe\b|\bour\b|\bmy\b/i', $text, $_m2);
        $thirdPerson = preg_match_all('/\bthey\b|\btheir\b|\bhe\b|\bshe\b/i', $text, $_m3);

        if ($firstPerson > 0 && $thirdPerson > 0 && abs($firstPerson - $thirdPerson) > 3) {
            $score -= 10;
            $items[] = [
                'type' => 'warning',
                'message' => 'Mixed first-person and third-person voice detected.',
                'suggestion' => 'Choose a consistent point of view throughout the content.',
            ];
        }

        return QualityDimensionResult::make($score, $items, [
            'source' => 'heuristic',
            'avg_words_per_sentence' => round($avgWordsPerSentence, 1),
        ]);
    }

    /** @param  array<string, mixed>|null  $voiceGuidelines */
    private function buildPersonaContext(string $systemPrompt, ?array $voiceGuidelines): string
    {
        $parts = ["## Persona System Prompt\n{$systemPrompt}"];

        if (! empty($voiceGuidelines)) {
            $parts[] = "## Voice Guidelines\n".json_encode($voiceGuidelines, JSON_PRETTY_PRINT);
        }

        return implode("\n\n", $parts);
    }

    private function resolvePersona(Content $content): ?\App\Models\Persona
    {
        // Attempt to find persona via a recent pipeline run.
        // ContentPipeline does not formally declare a persona relation yet;
        // pipelines may reference a Persona via dynamic property in future iterations.
        /** @var \App\Models\PipelineRun|null $latestRun */
        $latestRun = $content->pipelineRuns()->with('pipeline')->latest()->first();

        if ($latestRun === null) {
            return null;
        }

        $pipeline = $latestRun->pipeline;

        /** @var object{persona?: \App\Models\Persona} $pipeline */
        return isset($pipeline->persona) ? $pipeline->persona : null;
    }

    private function extractText(ContentVersion $version): string
    {
        return strip_tags((string) $version->body);
    }
}
