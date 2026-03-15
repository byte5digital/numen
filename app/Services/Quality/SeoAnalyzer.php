<?php

namespace App\Services\Quality;

use App\Models\Content;

/**
 * Analyzes SEO signals: title, meta description, headings, keyword density, links, image alt text.
 *
 * Scoring weights:
 *   Title tag length          20%
 *   Meta description length   15%
 *   Heading hierarchy         20%
 *   Keyword density           15%
 *   Link analysis             15%
 *   Image alt text coverage   15%
 */
class SeoAnalyzer implements QualityAnalyzerContract
{
    private const DIMENSION = 'seo';

    private const WEIGHT = 0.25;

    private const TITLE_MIN_OPTIMAL = 50;

    private const TITLE_MAX_OPTIMAL = 60;

    private const TITLE_MAX_WARN = 70;

    private const META_MIN_OPTIMAL = 150;

    private const META_MAX_OPTIMAL = 160;

    private const META_MAX_WARN = 170;

    private const KW_DENSITY_MIN = 0.005;

    private const KW_DENSITY_MAX = 0.030;

    public function analyze(Content $content): QualityDimensionResult
    {
        $version = $content->currentVersion ?? $content->draftVersion;
        if ($version === null) {
            return QualityDimensionResult::make(0, [['type' => 'error', 'message' => 'No content version available.']]);
        }

        $items = [];
        $title = (string) $version->title;
        $metaDesc = (string) ($version->meta_description ?? '');
        $body = (string) $version->body;
        $seoData = is_array($version->seo_data) ? $version->seo_data : [];

        $titleScore = $this->scoreTitle($title, $seoData, $items);
        $metaScore = $this->scoreMetaDescription($metaDesc, $items);
        $headingScore = $this->scoreHeadings($body, $items);
        $kwScore = $this->scoreKeywordDensity($body, $title, $items);
        $linkScore = $this->scoreLinks($body, $items);
        $imgScore = $this->scoreImageAltText($body, $items);

        $total = ($titleScore * 0.20) + ($metaScore * 0.15) + ($headingScore * 0.20)
            + ($kwScore * 0.15) + ($linkScore * 0.15) + ($imgScore * 0.15);

        $metadata = [
            'title_length' => mb_strlen($title),
            'meta_desc_length' => mb_strlen($metaDesc),
            'title_score' => $titleScore,
            'meta_score' => $metaScore,
            'heading_score' => $headingScore,
            'keyword_score' => $kwScore,
            'link_score' => $linkScore,
            'image_alt_score' => $imgScore,
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

    /**
     * @param  array<string, mixed>  $seoData
     * @param  array<int, array{type: string, message: string, suggestion?: string, meta?: array<string, mixed>}>  $items
     */
    private function scoreTitle(string $title, array $seoData, array &$items): float
    {
        $seoTitle = isset($seoData['title']) && is_string($seoData['title']) ? $seoData['title'] : $title;
        $len = mb_strlen(trim($seoTitle));
        if ($len === 0) {
            $items[] = ['type' => 'error', 'message' => 'No title found.', 'suggestion' => 'Add a descriptive title between 50-60 characters.'];

            return 0.0;
        }
        if ($len >= self::TITLE_MIN_OPTIMAL && $len <= self::TITLE_MAX_OPTIMAL) {
            $items[] = ['type' => 'info', 'message' => "Title length is optimal ({$len} chars)."];

            return 100.0;
        }
        if ($len < self::TITLE_MIN_OPTIMAL) {
            $items[] = ['type' => 'warning', 'message' => "Title is short ({$len} chars). Aim for 50-60 chars.", 'suggestion' => 'Expand title to be more descriptive.', 'meta' => ['title_length' => $len]];

            return $len < 20 ? 20.0 : 60.0;
        }
        if ($len <= self::TITLE_MAX_WARN) {
            $items[] = ['type' => 'warning', 'message' => "Title is slightly long ({$len} chars). Aim for 50-60 chars.", 'suggestion' => 'Shorten title to avoid SERP truncation.', 'meta' => ['title_length' => $len]];

            return 70.0;
        }
        $items[] = ['type' => 'error', 'message' => "Title is too long ({$len} chars). Will be truncated in SERPs.", 'suggestion' => 'Shorten title to 50-60 characters.', 'meta' => ['title_length' => $len]];

        return 30.0;
    }

    /** @param array<int, array{type: string, message: string, suggestion?: string, meta?: array<string, mixed>}> $items */
    private function scoreMetaDescription(string $meta, array &$items): float
    {
        $len = mb_strlen(trim($meta));
        if ($len === 0) {
            $items[] = ['type' => 'error', 'message' => 'Meta description is missing.', 'suggestion' => 'Add a meta description between 150-160 characters.'];

            return 0.0;
        }
        if ($len >= self::META_MIN_OPTIMAL && $len <= self::META_MAX_OPTIMAL) {
            $items[] = ['type' => 'info', 'message' => "Meta description length is optimal ({$len} chars)."];

            return 100.0;
        }
        if ($len < self::META_MIN_OPTIMAL) {
            $items[] = ['type' => 'warning', 'message' => "Meta description is short ({$len} chars). Aim for 150-160 chars.", 'suggestion' => 'Expand meta description to better summarise the content.', 'meta' => ['meta_length' => $len]];

            return $len < 50 ? 20.0 : 55.0;
        }
        if ($len <= self::META_MAX_WARN) {
            $items[] = ['type' => 'warning', 'message' => "Meta description is slightly long ({$len} chars).", 'suggestion' => 'Trim to 150-160 chars to avoid SERP truncation.', 'meta' => ['meta_length' => $len]];

            return 70.0;
        }
        $items[] = ['type' => 'error', 'message' => "Meta description is too long ({$len} chars).", 'suggestion' => 'Shorten to 150-160 characters.', 'meta' => ['meta_length' => $len]];

        return 30.0;
    }

    /** @param array<int, array{type: string, message: string, suggestion?: string, meta?: array<string, mixed>}> $items */
    private function scoreHeadings(string $html, array &$items): float
    {
        preg_match_all('/<h([1-6])[^>]*>/i', $html, $matches);
        $levels = array_map('intval', $matches[1]);
        if (count($levels) === 0) {
            $items[] = ['type' => 'error', 'message' => 'No headings found in content.', 'suggestion' => 'Add structured headings (H1, H2, H3) to improve SEO.'];

            return 0.0;
        }
        $h1Count = count(array_filter($levels, fn ($l) => $l === 1));
        $score = 100.0;
        if ($h1Count === 0) {
            $items[] = ['type' => 'error', 'message' => 'No H1 heading found.', 'suggestion' => 'Add exactly one H1 heading as the main topic.'];
            $score -= 50;
        } elseif ($h1Count > 1) {
            $items[] = ['type' => 'warning', 'message' => "Multiple H1 headings found ({$h1Count}).", 'suggestion' => 'Use only one H1 per page.'];
            $score -= 20;
        } else {
            $items[] = ['type' => 'info', 'message' => 'H1 heading is present.'];
        }
        $prevLevel = 0;
        $skipJumps = 0;
        foreach ($levels as $level) {
            if ($prevLevel > 0 && $level > $prevLevel + 1) {
                $skipJumps++;
            }
            $prevLevel = $level;
        }
        if ($skipJumps > 0) {
            $items[] = ['type' => 'warning', 'message' => "Heading hierarchy skips {$skipJumps} level(s).", 'suggestion' => 'Ensure headings follow a logical hierarchy (H1 > H2 > H3).'];
            $score -= ($skipJumps * 10);
        }

        return max(0.0, $score);
    }

    /** @param array<int, array{type: string, message: string, suggestion?: string, meta?: array<string, mixed>}> $items */
    private function scoreKeywordDensity(string $html, string $title, array &$items): float
    {
        $text = strip_tags($html);
        $text = mb_strtolower(html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        preg_match_all('/\b[a-z]{3,}\b/', $text, $wordMatches);
        $words = $wordMatches[0];
        $totalWords = count($words);
        if ($totalWords === 0) {
            $items[] = ['type' => 'warning', 'message' => 'No body text for keyword analysis.'];

            return 50.0;
        }
        $freq = array_count_values($words);
        arsort($freq);
        $stopwords = ['the', 'and', 'for', 'are', 'but', 'not', 'you', 'all', 'any', 'can', 'that', 'this', 'with', 'from', 'was', 'has', 'have', 'been', 'its', 'our', 'your'];
        $candidates = array_diff_key($freq, array_flip($stopwords));
        if (count($candidates) === 0) {
            $items[] = ['type' => 'info', 'message' => 'Keyword density check: only common words found.'];

            return 50.0;
        }
        $topKw = array_key_first($candidates);
        $topFreq = $candidates[$topKw];
        $density = $topFreq / $totalWords;
        $pct = round($density * 100, 2);
        if ($density >= self::KW_DENSITY_MIN && $density <= self::KW_DENSITY_MAX) {
            $items[] = ['type' => 'info', 'message' => "Top keyword '{$topKw}' has good density ({$pct}%).", 'meta' => ['keyword' => $topKw, 'density' => $pct]];

            return 100.0;
        }
        if ($density < self::KW_DENSITY_MIN) {
            $items[] = ['type' => 'warning', 'message' => "Low keyword density ({$pct}%). Aim for 0.5-3%.", 'suggestion' => 'Use your target keyword more naturally throughout the content.', 'meta' => ['keyword' => $topKw, 'density' => $pct]];

            return 50.0;
        }
        $items[] = ['type' => 'error', 'message' => "Keyword stuffing detected ('{$topKw}': {$pct}%). Max recommended is 3%.", 'suggestion' => 'Reduce keyword repetition to avoid SEO penalties.', 'meta' => ['keyword' => $topKw, 'density' => $pct]];

        return 20.0;
    }

    /** @param array<int, array{type: string, message: string, suggestion?: string, meta?: array<string, mixed>}> $items */
    private function scoreLinks(string $html, array &$items): float
    {
        preg_match_all('/<a\s[^>]*href=["\x27](https?:[^"\x27]+)["\x27]/i', $html, $extMatches);
        preg_match_all('/<a\s[^>]*href=["\x27](\/[^"\x27]*)["\x27]/i', $html, $intMatches);
        $extCount = count($extMatches[0]);
        $intCount = count($intMatches[0]);
        $total = $extCount + $intCount;
        $score = 100.0;
        if ($total === 0) {
            $items[] = ['type' => 'warning', 'message' => 'No links found in content.', 'suggestion' => 'Add internal and external links.'];

            return 30.0;
        }
        if ($intCount === 0) {
            $items[] = ['type' => 'warning', 'message' => 'No internal links found.', 'suggestion' => 'Add internal links to improve site structure.'];
            $score -= 30;
        } else {
            $items[] = ['type' => 'info', 'message' => "{$intCount} internal link(s) found.", 'meta' => ['internal_links' => $intCount]];
        }
        if ($extCount === 0) {
            $items[] = ['type' => 'info', 'message' => 'No external links. Consider linking to authoritative sources.'];
            $score -= 10;
        } else {
            $items[] = ['type' => 'info', 'message' => "{$extCount} external link(s) found.", 'meta' => ['external_links' => $extCount]];
        }

        return max(0.0, $score);
    }

    /** @param array<int, array{type: string, message: string, suggestion?: string, meta?: array<string, mixed>}> $items */
    private function scoreImageAltText(string $html, array &$items): float
    {
        preg_match_all('/<img[^>]+>/i', $html, $allImgs);
        $totalImgs = count($allImgs[0]);
        if ($totalImgs === 0) {
            $items[] = ['type' => 'info', 'message' => 'No images found in content.'];

            return 100.0;
        }
        preg_match_all('/<img[^>]+alt=["\x27][^"\x27]+["\x27][^>]*>/i', $html, $altImgs);
        $withAlt = count($altImgs[0]);
        $coverage = $withAlt / $totalImgs;
        $pct = round($coverage * 100);
        $missing = $totalImgs - $withAlt;
        if ($coverage >= 1.0) {
            $items[] = ['type' => 'info', 'message' => "All {$totalImgs} image(s) have alt text."];

            return 100.0;
        }
        if ($coverage >= 0.80) {
            $items[] = ['type' => 'warning', 'message' => "{$missing} image(s) missing alt text ({$pct}% coverage).", 'suggestion' => 'Add descriptive alt text to all images.', 'meta' => ['missing_alt' => $missing, 'coverage_pct' => $pct]];

            return 75.0;
        }
        if ($coverage >= 0.50) {
            $items[] = ['type' => 'warning', 'message' => "{$missing} image(s) missing alt text ({$pct}% coverage).", 'suggestion' => 'Add alt text to all images for accessibility and SEO.', 'meta' => ['missing_alt' => $missing, 'coverage_pct' => $pct]];

            return 50.0;
        }
        $items[] = ['type' => 'error', 'message' => "Most images lack alt text ({$pct}% coverage).", 'suggestion' => 'Add descriptive alt text to every image.', 'meta' => ['missing_alt' => $missing, 'coverage_pct' => $pct]];

        return 20.0;
    }
}
