<?php

namespace App\Services\Quality;

use App\Models\Content;

class ReadabilityAnalyzer implements QualityAnalyzerContract
{
    private const DIMENSION = 'readability';

    private const WEIGHT = 0.20;

    private const FLESCH_VERY_EASY = 90;

    private const FLESCH_EASY = 70;

    private const FLESCH_STANDARD = 60;

    private const FLESCH_FAIRLY_HARD = 50;

    private const FLESCH_HARD = 30;

    private const SENTENCE_LONG = 30;

    private const SENTENCE_VERY_LONG = 40;

    private const PARA_OPTIMAL_MAX = 5;

    private const PARA_LONG = 8;

    private const PASSIVE_WARN = 0.10;

    private const PASSIVE_ERROR = 0.25;

    public function analyze(Content $content): QualityDimensionResult
    {
        $version = $content->currentVersion ?? $content->draftVersion;
        if ($version === null) {
            return QualityDimensionResult::make(0, [['type' => 'error', 'message' => 'No content version available.']]);
        }
        $text = $this->extractPlainText((string) $version->body);
        if (trim($text) === '') {
            return QualityDimensionResult::make(0, [['type' => 'error', 'message' => 'Content body is empty.']]);
        }
        $items = [];
        $words = $this->tokenizeWords($text);
        $sentences = $this->splitSentences($text);
        $paragraphs = $this->splitParagraphs($text);
        $wordCount = count($words);
        $sentenceCount = max(1, count($sentences));
        $syllableCount = array_sum(array_map([$this, 'countSyllables'], $words));
        $flesch = $this->fleschReadingEase($wordCount, $sentenceCount, $syllableCount);
        $fleschScore = $this->scoreFleschReading($flesch, $items);
        $sentScore = $this->scoreSentenceLengths($sentences, $items);
        $paraScore = $this->scoreParagraphStructure($paragraphs, $items);
        $passScore = $this->scorePassiveVoice($sentences, $items);
        $total = ($fleschScore * 0.35) + ($sentScore * 0.25) + ($paraScore * 0.25) + ($passScore * 0.15);
        $metadata = [
            'word_count' => $wordCount,
            'sentence_count' => $sentenceCount,
            'paragraph_count' => count($paragraphs),
            'syllable_count' => $syllableCount,
            'flesch_score' => round($flesch, 1),
            'avg_sentence_len' => $sentenceCount > 0 ? round($wordCount / $sentenceCount, 1) : 0,
        ];

        return QualityDimensionResult::make(round($total, 2), $items, $metadata);
    }

    public function getDimension(): string
    {
        return self::DIMENSION;
    }

    public function getWeight(): float
    {
        return self::WEIGHT;
    }

    private function extractPlainText(string $html): string
    {
        $text = strip_tags($html);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = (string) preg_replace('/\s+/', ' ', $text);

        return trim($text);
    }

    /** @return string[] */
    private function tokenizeWords(string $text): array
    {
        preg_match_all('/\b[a-zA-Z\x27]+\b/', $text, $matches);

        return $matches[0];
    }

    /** @return string[] */
    private function splitSentences(string $text): array
    {
        $raw = preg_split('/(?<=[.!?])\s+/', trim($text), -1, PREG_SPLIT_NO_EMPTY);

        return is_array($raw) ? array_values(array_filter($raw, fn ($s) => trim($s) !== '')) : [];
    }

    /** @return string[] */
    private function splitParagraphs(string $text): array
    {
        $raw = preg_split('/\n{2,}/', $text, -1, PREG_SPLIT_NO_EMPTY);

        return is_array($raw) ? array_values(array_filter($raw, fn ($p) => trim($p) !== '')) : [];
    }

    private function fleschReadingEase(int $words, int $sentences, int $syllables): float
    {
        if ($words === 0 || $sentences === 0) {
            return 0.0;
        }

        return 206.835 - (1.015 * ($words / $sentences)) - (84.6 * ($syllables / $words));
    }

    private function countSyllables(string $word): int
    {
        $word = strtolower($word);
        $word = rtrim($word, 'e');
        if ($word === '') {
            return 1;
        }
        $count = preg_match_all('/[aeiouy]+/', $word);

        return max(1, (int) $count);
    }

    /** @param array<int, array{type: string, message: string, suggestion?: string, meta?: array<string, mixed>}> $items */
    private function scoreFleschReading(float $flesch, array &$items): float
    {
        $r = round($flesch, 1);
        if ($flesch >= self::FLESCH_VERY_EASY) {
            $items[] = ['type' => 'info',    'message' => "Flesch: {$r} — very easy."];

            return 100.0;
        }
        if ($flesch >= self::FLESCH_EASY) {
            $items[] = ['type' => 'info',    'message' => "Flesch: {$r} — easy."];

            return 90.0;
        }
        if ($flesch >= self::FLESCH_STANDARD) {
            $items[] = ['type' => 'info',    'message' => "Flesch: {$r} — standard readability."];

            return 75.0;
        }
        if ($flesch >= self::FLESCH_FAIRLY_HARD) {
            $items[] = ['type' => 'warning', 'message' => "Flesch: {$r} — fairly difficult.", 'suggestion' => 'Simplify sentences.'];

            return 50.0;
        }
        if ($flesch >= self::FLESCH_HARD) {
            $items[] = ['type' => 'warning', 'message' => "Flesch: {$r} — difficult.", 'suggestion' => 'Use shorter sentences and words.'];

            return 25.0;
        }
        $items[] = ['type' => 'error', 'message' => "Flesch: {$r} — very difficult.", 'suggestion' => 'Significantly simplify language.'];

        return 0.0;
    }

    /**
     * @param  string[]  $sentences
     * @param  array<int, array{type: string, message: string, suggestion?: string, meta?: array<string, mixed>}>  $items
     */
    private function scoreSentenceLengths(array $sentences, array &$items): float
    {
        if (count($sentences) === 0) {
            return 100.0;
        }
        $longCount = 0;
        $veryLongCount = 0;
        foreach ($sentences as $sentence) {
            $wc = count($this->tokenizeWords($sentence));
            if ($wc > self::SENTENCE_VERY_LONG) {
                $veryLongCount++;
            } elseif ($wc > self::SENTENCE_LONG) {
                $longCount++;
            }
        }
        $total = count($sentences);
        $longRatio = ($longCount + $veryLongCount) / $total;
        if ($veryLongCount > 0) {
            $items[] = ['type' => 'warning', 'message' => "{$veryLongCount} sentence(s) exceed ".self::SENTENCE_VERY_LONG.' words.', 'suggestion' => 'Split very long sentences.', 'meta' => ['very_long_sentences' => $veryLongCount]];
        }
        if ($longCount > 0) {
            $items[] = ['type' => 'info',    'message' => "{$longCount} long sentence(s) detected.", 'meta' => ['long_sentences' => $longCount]];
        }
        if ($longRatio === 0.0) {
            return 100.0;
        }
        if ($longRatio <= 0.10) {
            return 85.0;
        }
        if ($longRatio <= 0.20) {
            return 65.0;
        }
        if ($longRatio <= 0.35) {
            return 40.0;
        }

        return 15.0;
    }

    /**
     * @param  string[]  $paragraphs
     * @param  array<int, array{type: string, message: string, suggestion?: string, meta?: array<string, mixed>}>  $items
     */
    private function scoreParagraphStructure(array $paragraphs, array &$items): float
    {
        if (count($paragraphs) === 0) {
            $items[] = ['type' => 'warning', 'message' => 'No paragraphs detected.', 'suggestion' => 'Structure content into paragraphs.'];

            return 30.0;
        }
        if (count($paragraphs) === 1) {
            $items[] = ['type' => 'warning', 'message' => 'Content is one block of text.', 'suggestion' => 'Break into multiple paragraphs.'];

            return 50.0;
        }
        $longParas = 0;
        foreach ($paragraphs as $para) {
            $sc = count($this->splitSentences($para));
            if ($sc > self::PARA_LONG) {
                $longParas++;
            } elseif ($sc > self::PARA_OPTIMAL_MAX) {
                $items[] = ['type' => 'info', 'message' => 'A paragraph exceeds '.self::PARA_OPTIMAL_MAX.' sentences.'];
            }
        }
        if ($longParas > 0) {
            $items[] = ['type' => 'warning', 'message' => "{$longParas} paragraph(s) exceed ".self::PARA_LONG.' sentences.', 'suggestion' => 'Split long paragraphs.', 'meta' => ['long_paragraphs' => $longParas]];

            return 60.0;
        }

        return 100.0;
    }

    /**
     * @param  string[]  $sentences
     * @param  array<int, array{type: string, message: string, suggestion?: string, meta?: array<string, mixed>}>  $items
     */
    private function scorePassiveVoice(array $sentences, array &$items): float
    {
        if (count($sentences) === 0) {
            return 100.0;
        }
        $pattern = '/\b(am|is|are|was|were|be|been|being)\s+\w+ed\b/i';
        $passiveCount = 0;
        foreach ($sentences as $sentence) {
            if (preg_match($pattern, $sentence)) {
                $passiveCount++;
            }
        }
        $ratio = $passiveCount / count($sentences);
        if ($ratio >= self::PASSIVE_ERROR) {
            $pct = round($ratio * 100);
            $items[] = ['type' => 'error', 'message' => "High passive voice: ~{$pct}%.", 'suggestion' => 'Rewrite in active voice.', 'meta' => ['passive_ratio' => round($ratio, 2)]];

            return max(0.0, 100.0 - ($ratio * 200));
        }
        if ($ratio >= self::PASSIVE_WARN) {
            $pct = round($ratio * 100);
            $items[] = ['type' => 'warning', 'message' => "Moderate passive voice: ~{$pct}%.", 'suggestion' => 'Prefer active voice.', 'meta' => ['passive_ratio' => round($ratio, 2)]];

            return 75.0;
        }

        return 100.0;
    }
}
