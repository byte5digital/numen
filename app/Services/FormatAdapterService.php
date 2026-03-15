<?php

namespace App\Services;

use App\Models\Content;
use App\Models\FormatTemplate;
use App\Models\Persona;

class FormatAdapterService
{
    /**
     * Build the system + user prompt pair for a given content + template + optional persona.
     *
     * @return array{system: string, user: string}
     */
    public function buildPrompt(Content $content, FormatTemplate $template, ?Persona $persona = null): array
    {
        $body = $this->extractBody($content);
        $excerpt = (string) ($content->excerpt ?? '');
        $title = (string) ($content->title ?? '');

        // Derive tone and word_count — prefer persona, fall back to sensible defaults.
        $tone = $this->resolveTone($persona);
        $wordCount = (string) $this->estimateWordCount($body);

        $placeholders = [
            '{{title}}' => $title,
            '{{body}}' => $body,
            '{{tone}}' => $tone,
            '{{word_count}}' => $wordCount,
            '{{excerpt}}' => $excerpt,
        ];

        $systemPrompt = strtr($template->system_prompt, $placeholders);
        $userPrompt = strtr($template->user_prompt_template, $placeholders);

        // Merge persona voice/tone instructions into the system prompt.
        if ($persona !== null) {
            $voiceInstructions = $this->buildPersonaVoiceInstructions($persona);

            if ($voiceInstructions !== '') {
                $systemPrompt .= "\n\n".$voiceInstructions;
            }
        }

        return [
            'system' => $systemPrompt,
            'user' => $userPrompt,
        ];
    }

    /**
     * Parse raw LLM output into a normalised structure.
     *
     * @return array{output: string, output_parts: array<int, string>|null}
     */
    public function parseOutput(string $rawOutput, FormatTemplate $template): array
    {
        $trimmed = trim($rawOutput);

        if ($template->format_key === 'twitter_thread') {
            $parts = $this->splitTwitterThread($trimmed);

            return [
                'output' => $trimmed,
                'output_parts' => $parts ?: null,
            ];
        }

        return [
            'output' => $trimmed,
            'output_parts' => null,
        ];
    }

    /**
     * Returns the full list of supported format_keys with metadata.
     *
     * @return array<string, array{label: string, description: string}>
     */
    public function getSupportedFormats(): array
    {
        return [
            'twitter_thread' => [
                'label' => 'Twitter Thread',
                'description' => 'A numbered multi-tweet thread for Twitter/X.',
            ],
            'linkedin_post' => [
                'label' => 'LinkedIn Post',
                'description' => 'A professional post for LinkedIn.',
            ],
            'newsletter_section' => [
                'label' => 'Newsletter Section',
                'description' => 'A section ready to drop into an email newsletter.',
            ],
            'instagram_caption' => [
                'label' => 'Instagram Caption',
                'description' => 'A caption with hashtags for Instagram.',
            ],
            'podcast_script_outline' => [
                'label' => 'Podcast Script Outline',
                'description' => 'A structured outline for a podcast episode.',
            ],
            'product_page_copy' => [
                'label' => 'Product Page Copy',
                'description' => 'Conversion-focused copy for a product or landing page.',
            ],
            'faq_section' => [
                'label' => 'FAQ Section',
                'description' => 'A set of questions and answers derived from the content.',
            ],
            'youtube_description' => [
                'label' => 'YouTube Description',
                'description' => 'An SEO-optimised description for a YouTube video.',
            ],
        ];
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function extractBody(Content $content): string
    {
        $version = $content->currentVersion;

        if ($version !== null && ! empty($version->body)) {
            return (string) $version->body;
        }

        // Attempt to flatten content blocks if available.
        if ($version !== null && $version->relationLoaded('blocks') && $version->blocks->isNotEmpty()) {
            return $version->blocks
                ->pluck('content')
                ->filter()
                ->implode("\n\n");
        }

        return '';
    }

    private function resolveTone(?Persona $persona): string
    {
        if ($persona === null) {
            return 'professional';
        }

        $guidelines = $persona->voice_guidelines ?? [];

        return (string) ($guidelines['tone'] ?? $guidelines['voice'] ?? 'professional');
    }

    private function estimateWordCount(string $body): int
    {
        $words = str_word_count(strip_tags($body));

        // Round up to the nearest 50.
        return (int) (ceil($words / 50) * 50);
    }

    private function buildPersonaVoiceInstructions(Persona $persona): string
    {
        $guidelines = $persona->voice_guidelines ?? [];

        if (empty($guidelines)) {
            return '';
        }

        $lines = ["Voice & tone guidelines for persona \"{$persona->name}\":"];

        foreach ($guidelines as $key => $value) {
            if (is_string($value) && $value !== '') {
                $lines[] = '- '.ucfirst($key).': '.$value;
            }
        }

        return count($lines) > 1 ? implode("\n", $lines) : '';
    }

    /**
     * Split a numbered Twitter thread into individual tweets.
     *
     * Handles formats like "1/ ...", "1. ...", "[1] ...", "Tweet 1: ..."
     *
     * @return array<int, string>
     */
    private function splitTwitterThread(string $text): array
    {
        $lines = explode("\n", $text);
        $tweets = [];
        $current = '';

        foreach ($lines as $line) {
            $line = rtrim($line);

            // Detect a new numbered tweet marker.
            if (preg_match('/^(?:Tweet\s*)?\d+[\/\.\]:\)]\s/i', $line)) {
                if ($current !== '') {
                    $tweets[] = trim($current);
                }
                $current = $line;
            } elseif ($current !== '') {
                $current .= "\n".$line;
            } elseif ($line !== '') {
                // Pre-numbered preamble — attach to first tweet (current is empty here).
                $current .= $line;
            }
        }

        if ($current !== '') {
            $tweets[] = trim($current);
        }

        return $tweets;
    }
}
