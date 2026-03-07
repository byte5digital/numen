<?php

namespace App\Services\Search;

use App\Models\Content;
use App\Models\ContentVersion;
use App\Services\Search\Results\ContentChunk;

/**
 * Splits a Content + ContentVersion into semantic chunks for embedding.
 *
 * Chunking hierarchy:
 * 1. Title chunk (always standalone)
 * 2. Excerpt chunk (if present)
 * 3. SEO chunk (meta title + description)
 * 4. Block-level chunks (with heading context propagated)
 */
class ContentChunker
{
    private const CHUNK_MAX_TOKENS = 512;

    private const CHUNK_OVERLAP_TOKENS = 64;

    /**
     * @return ContentChunk[]
     */
    public function chunk(Content $content, ContentVersion $version): array
    {
        $chunks = [];
        $index = 0;

        $maxTokens = (int) config('numen.search.chunk_max_tokens', self::CHUNK_MAX_TOKENS);

        // 1. Title chunk
        if (! empty($version->title)) {
            $chunks[] = new ContentChunk(
                text: $version->title,
                type: 'title',
                index: $index++,
                metadata: ['locale' => $content->locale],
                tokenCount: $this->estimateTokens($version->title),
            );
        }

        // 2. Excerpt chunk
        if (! empty($version->excerpt)) {
            $chunks[] = new ContentChunk(
                text: $version->excerpt,
                type: 'excerpt',
                index: $index++,
                metadata: ['locale' => $content->locale],
                tokenCount: $this->estimateTokens($version->excerpt),
            );
        }

        // 3. SEO data chunk
        $seoData = is_array($version->seo_data) ? $version->seo_data : [];
        $seoTitle = (string) ($seoData['title'] ?? '');
        $seoDesc = (string) ($seoData['description'] ?? '');
        $seoText = trim($seoTitle.' '.$seoDesc);

        if (! empty($seoText)) {
            $chunks[] = new ContentChunk(
                text: $seoText,
                type: 'seo',
                index: $index++,
                metadata: ['locale' => $content->locale],
                tokenCount: $this->estimateTokens($seoText),
            );
        }

        // 4. Block-level chunks from content blocks
        $blocks = $version->blocks()->orderBy('sort_order')->get();
        $currentHeading = '';

        foreach ($blocks as $block) {
            /** @var \App\Models\ContentBlock $block */
            $blockType = (string) ($block->type ?? 'paragraph');
            $blockContent = $this->extractBlockText($block);

            if (empty($blockContent)) {
                continue;
            }

            if ($blockType === 'heading') {
                $currentHeading = $blockContent;
                // Also create a standalone heading chunk
                $chunks[] = new ContentChunk(
                    text: $blockContent,
                    type: 'block',
                    index: $index++,
                    metadata: [
                        'block_type' => 'heading',
                        'locale' => $content->locale,
                    ],
                    tokenCount: $this->estimateTokens($blockContent),
                );

                continue;
            }

            // Prepend heading context for body chunks
            $contextPrefix = $currentHeading ? "{$currentHeading}\n" : '';

            $blockChunks = $this->splitIntoChunks(
                $contextPrefix.$blockContent,
                $maxTokens,
                $blockType,
                $index,
                ['block_type' => $blockType, 'heading_context' => $currentHeading, 'locale' => $content->locale],
            );

            foreach ($blockChunks as $chunk) {
                $chunks[] = $chunk;
                $index++;
            }
        }

        // 5. Fallback: if no blocks, chunk the raw body
        if ($blocks->isEmpty() && ! empty($version->body)) {
            $bodyText = strip_tags((string) $version->body);
            $bodyChunks = $this->splitIntoChunks($bodyText, $maxTokens, 'body', $index, ['locale' => $content->locale]);

            foreach ($bodyChunks as $chunk) {
                $chunks[] = $chunk;
                $index++;
            }
        }

        return $chunks;
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return ContentChunk[]
     */
    private function splitIntoChunks(
        string $text,
        int $maxTokens,
        string $type,
        int $startIndex,
        array $metadata,
    ): array {
        $tokenCount = $this->estimateTokens($text);

        // Short enough — single chunk
        if ($tokenCount <= $maxTokens) {
            return [new ContentChunk(
                text: $text,
                type: $type,
                index: $startIndex,
                metadata: $metadata,
                tokenCount: $tokenCount,
            )];
        }

        // Split at sentence boundaries
        $sentences = $this->splitSentences($text);
        $chunks = [];
        $currentChunk = '';
        $currentTokens = 0;
        $overlapTokens = (int) config('numen.search.chunk_overlap_tokens', self::CHUNK_OVERLAP_TOKENS);
        $overlapChars = $overlapTokens * 4;
        $idx = $startIndex;

        foreach ($sentences as $sentence) {
            $sentenceTokens = $this->estimateTokens($sentence);

            if ($currentTokens + $sentenceTokens > $maxTokens && $currentChunk !== '') {
                $chunks[] = new ContentChunk(
                    text: trim($currentChunk),
                    type: $type,
                    index: $idx++,
                    metadata: $metadata,
                    tokenCount: $currentTokens,
                );

                // Keep overlap
                $overlap = strlen($currentChunk) > $overlapChars
                    ? substr($currentChunk, -$overlapChars)
                    : $currentChunk;
                $currentChunk = $overlap.' '.$sentence;
                $currentTokens = $this->estimateTokens($currentChunk);
            } else {
                $currentChunk .= ' '.$sentence;
                $currentTokens += $sentenceTokens;
            }
        }

        if (! empty(trim($currentChunk))) {
            $chunks[] = new ContentChunk(
                text: trim($currentChunk),
                type: $type,
                index: $idx,
                metadata: $metadata,
                tokenCount: $currentTokens,
            );
        }

        return $chunks;
    }

    /**
     * @return string[]
     */
    private function splitSentences(string $text): array
    {
        // Split on sentence-ending punctuation, keeping delimiter
        $parts = preg_split('/(?<=[.!?])\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);

        return is_array($parts) ? $parts : [$text];
    }

    private function extractBlockText(\App\Models\ContentBlock $block): string
    {
        $data = $block->data;

        if (is_string($data)) {
            return strip_tags($data);
        }

        if (! is_array($data)) {
            return '';
        }

        // Try common content keys
        $text = '';
        foreach (['text', 'body', 'content', 'caption', 'alt', 'title'] as $key) {
            if (! empty($data[$key]) && is_string($data[$key])) {
                $text .= ' '.strip_tags($data[$key]);
            }
        }

        return trim($text);
    }

    private function estimateTokens(string $text): int
    {
        // Fast approximation: strlen / 4 for English text
        return (int) ceil(strlen($text) / 4);
    }
}
