<?php

namespace App\Services;

use App\Models\Content;
use App\Models\ContentVersion;
use App\Models\Persona;
use App\Services\AI\LLMManager;

class AITranslationService
{
    /**
     * Maximum total character count across all content fields before rejecting the
     * translation request. ~100k chars ≈ 25k tokens input, well within model limits
     * but prevents accidental or intentional cost explosions with huge content bodies.
     */
    private const MAX_CONTENT_CHARS = 100_000;

    public function __construct(
        private readonly LLMManager $llm,
    ) {}

    /**
     * Translate source content to the target locale using an LLM.
     *
     * @return array{title: string, body: string, excerpt: ?string, meta_description: ?string}
     */
    public function translate(Content $source, string $targetLocale, ?Persona $persona = null): array
    {
        $this->assertContentSizeWithinLimit($source);

        $prompt = $this->buildTranslationPrompt($source, $targetLocale, $persona);

        $response = $this->llm->complete([
            'model' => config('numen.providers.anthropic.default_model', 'claude-sonnet-4-6'),
            'system' => $prompt['system'],
            'messages' => [
                ['role' => 'user', 'content' => $prompt['user']],
            ],
            'max_tokens' => 4096,
            'temperature' => 0.3,
            '_purpose' => 'content_translation',
        ], null, $persona);

        $raw = $response->content;

        // Strip markdown code fences if present
        $raw = preg_replace('/^```(?:json)?\s*/m', '', $raw);
        $raw = preg_replace('/\s*```$/m', '', $raw);
        $raw = trim($raw);

        $decoded = json_decode($raw, true);

        if (! is_array($decoded)) {
            throw new \RuntimeException('AITranslationService: LLM returned non-JSON response: '.substr($raw, 0, 200));
        }

        return [
            'title' => $decoded['title'] ?? '',
            'body' => $decoded['body'] ?? '',
            'excerpt' => $decoded['excerpt'] ?? null,
            'meta_description' => $decoded['meta_description'] ?? null,
        ];
    }

    /**
     * Build the system and user prompts for a translation request.
     *
     * @return array{system: string, user: string}
     */
    public function buildTranslationPrompt(Content $source, string $targetLocale, ?Persona $persona): array
    {
        $system = '';

        if ($persona && ! empty($persona->voice_guidelines)) {
            $voiceLines = [];
            foreach ((array) $persona->voice_guidelines as $key => $value) {
                $voiceLines[] = is_string($key) ? "{$key}: {$value}" : $value;
            }
            $system .= "Voice and tone guidelines:\n".implode("\n", $voiceLines)."\n\n";
        }

        $system .= "You are a professional translator. Translate the following content to {$targetLocale}. "
            .'Preserve formatting, HTML tags, and structure exactly. Return only a valid JSON object with keys: '
            .'title, body, excerpt, meta_description. Do not include any explanation or markdown fences.';

        /** @var ContentVersion|null $version */
        $version = $source->currentVersion;

        $payload = [
            'title' => $version !== null ? $version->title : '',
            'body' => $version !== null ? $version->body : '',
            'excerpt' => $version !== null ? $version->excerpt : null,
            'meta_description' => $version !== null ? $version->meta_description : null,
        ];

        $user = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        return ['system' => $system, 'user' => $user];
    }

    /**
     * Estimate token cost for translating the given content.
     *
     * @return array{input_tokens: int, estimated_output_tokens: int}
     */
    public function estimateCost(Content $content): array
    {
        /** @var ContentVersion|null $version */
        $version = $content->currentVersion;

        $text = implode(' ', array_filter([
            $version !== null ? $version->title : '',
            $version !== null ? $version->body : '',
            $version !== null ? ($version->excerpt ?? '') : '',
            $version !== null ? ($version->meta_description ?? '') : '',
        ]));

        // Rough estimate: 1 token ≈ 4 characters
        $inputTokens = (int) ceil(mb_strlen($text) / 4);

        // Translation output is typically similar length to input
        $estimatedOutputTokens = (int) ceil($inputTokens * 1.1);

        return [
            'input_tokens' => $inputTokens,
            'estimated_output_tokens' => $estimatedOutputTokens,
        ];
    }

    /**
     * Guard against very large content bodies to prevent runaway AI costs.
     *
     * @throws \RuntimeException if combined content length exceeds MAX_CONTENT_CHARS
     */
    private function assertContentSizeWithinLimit(Content $content): void
    {
        /** @var ContentVersion|null $version */
        $version = $content->currentVersion;

        $totalChars = mb_strlen($version !== null ? $version->title : '')
            + mb_strlen($version !== null ? $version->body : '')
            + mb_strlen($version !== null ? ($version->excerpt ?? '') : '')
            + mb_strlen($version !== null ? ($version->meta_description ?? '') : '');

        if ($totalChars > self::MAX_CONTENT_CHARS) {
            throw new \RuntimeException(
                "Content #{$content->id} exceeds the maximum translation size limit "
                .'('.number_format($totalChars).' chars > '.number_format(self::MAX_CONTENT_CHARS).' chars). '
                .'Please reduce the content size before translating.',
            );
        }
    }
}
