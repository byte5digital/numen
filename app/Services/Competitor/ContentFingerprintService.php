<?php

namespace App\Services\Competitor;

use App\Models\CompetitorContentItem;
use App\Models\Content;
use App\Models\ContentBrief;
use App\Models\ContentFingerprint;
use App\Services\Graph\EntityExtractor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;

class ContentFingerprintService
{
    private const STOPWORDS = [
        'a', 'an', 'the', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for',
        'of', 'with', 'by', 'from', 'as', 'is', 'was', 'are', 'were', 'be',
        'been', 'being', 'have', 'has', 'had', 'do', 'does', 'did', 'will',
        'would', 'could', 'should', 'may', 'might', 'shall', 'can', 'not',
        'no', 'nor', 'so', 'yet', 'both', 'either', 'neither', 'each', 'few',
        'more', 'most', 'other', 'some', 'such', 'than', 'too', 'very', 'just',
        'that', 'this', 'these', 'those', 'it', 'its', 'also', 'about', 'into',
        'through', 'during', 'before', 'after', 'above', 'below', 'between',
        'out', 'up', 'then', 'once', 'here', 'there', 'when', 'where', 'who',
        'what', 'which', 'how', 'all', 'any', 'while', 'their', 'they', 'them',
        'our', 'we', 'you', 'your', 'my', 'me', 'he', 'him', 'she', 'her', 'his',
        'if', 'only', 'same', 'own', 'over', 'under', 'again', 'further',
    ];

    private const MIN_WORD_LENGTH = 3;

    private const TOP_KEYWORDS = 20;

    public function __construct(
        private readonly ?EntityExtractor $entityExtractor = null,
    ) {}

    public function fingerprint(Model $fingerprintable): ContentFingerprint
    {
        [$topics, $entities, $keywords] = match (true) {
            $fingerprintable instanceof ContentBrief => $this->extractFromBrief($fingerprintable),
            $fingerprintable instanceof Content => $this->extractFromContent($fingerprintable),
            $fingerprintable instanceof CompetitorContentItem => $this->extractFromCompetitorItem($fingerprintable),
            default => $this->extractFromText('', ''),
        };

        /** @var ContentFingerprint $fp */
        $fp = ContentFingerprint::updateOrCreate(
            [
                'fingerprintable_type' => $fingerprintable->getMorphClass(),
                'fingerprintable_id' => $fingerprintable->getKey(),
            ],
            [
                'topics' => $topics,
                'entities' => $entities,
                'keywords' => $keywords,
                'fingerprinted_at' => now(),
            ]
        );

        return $fp;
    }

    /** @return array{0: array<string>, 1: array<string>, 2: array<string, float>} */
    private function extractFromContent(Content $content): array
    {
        $version = $content->currentVersion;
        $title = ($version !== null) ? ($version->title ?? '') : '';
        $body = ($version !== null) ? strip_tags($version->body ?? '') : '';
        $excerpt = ($version !== null) ? ($version->excerpt ?? '') : '';
        $text = implode(' ', array_filter([$title, $excerpt, $body]));

        $extractor = $this->resolveEntityExtractor();
        if ($extractor !== null) {
            try {
                $extracted = $extractor->extract($content);

                $topics = array_values(array_map(
                    fn (array $e) => $e['entity'],
                    array_filter($extracted, fn (array $e) => in_array($e['type'], ['topic', 'concept'], true))
                ));

                $entities = array_values(array_map(
                    fn (array $e) => $e['entity'],
                    array_filter($extracted, fn (array $e) => in_array($e['type'], ['person', 'product', 'place'], true))
                ));

                $keywords = $this->extractKeywords($text);

                return [$topics, $entities, $keywords];
            } catch (\Throwable $e) {
                Log::warning('ContentFingerprintService: EntityExtractor failed, falling back to basic NLP', [
                    'content_id' => $content->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $this->extractFromText($title, $body);
    }

    /** @return array{0: array<string>, 1: array<string>, 2: array<string, float>} */
    private function extractFromBrief(ContentBrief $brief): array
    {
        $title = $brief->title ?? '';
        $description = $brief->description ?? '';
        $targetKeywords = $brief->target_keywords ?? [];

        [$topics, $entities, $extractedKeywords] = $this->extractFromText($title, $description);

        // Use target_keywords as primary topics (they are explicit intent signals)
        foreach ($targetKeywords as $kw) {
            $kw = trim($kw);
            if ($kw !== '' && ! in_array(strtolower($kw), array_map('strtolower', $topics), true)) {
                array_unshift($topics, $kw);
            }
        }

        $topics = array_slice($topics, 0, 15);

        // Merge explicit target_keywords with extracted ones (target keywords take priority)
        foreach ($targetKeywords as $kw) {
            $kw = strtolower(trim($kw));
            if ($kw !== '') {
                $extractedKeywords[$kw] = 1.0; // highest weight for explicit keywords
            }
        }

        arsort($extractedKeywords);

        return [$topics, $entities, array_slice($extractedKeywords, 0, self::TOP_KEYWORDS, true)];
    }

    /** @return array{0: array<string>, 1: array<string>, 2: array<string, float>} */
    private function extractFromCompetitorItem(CompetitorContentItem $item): array
    {
        $title = $item->title ?? '';
        $body = strip_tags($item->body ?? '');

        return $this->extractFromText($title, $body);
    }

    /** @return array{0: array<string>, 1: array<string>, 2: array<string, float>} */
    private function extractFromText(string $title, string $body): array
    {
        $fullText = implode(' ', array_filter([$title, $body]));

        $topics = $this->extractTopics($title, $body);
        $entities = $this->extractEntities($title, $body);
        $keywords = $this->extractKeywords($fullText);

        return [$topics, $entities, $keywords];
    }

    /** @return array<string> */
    private function extractTopics(string $title, string $body): array
    {
        $topics = [];

        if ($title !== '') {
            $segments = preg_split('/[:\-\x{2013}\x{2014}|]/u', $title);
            if ($segments !== false) {
                foreach ($segments as $segment) {
                    $clean = trim($segment);
                    if (mb_strlen($clean) >= 4 && mb_strlen($clean) <= 60) {
                        $topics[] = $clean;
                    }
                }
            }
        }

        $bigrams = $this->extractBigrams($body);
        foreach (array_slice($bigrams, 0, 10) as $bigram) {
            if (! in_array($bigram, $topics, true)) {
                $topics[] = $bigram;
            }
        }

        return array_values(array_unique(array_slice($topics, 0, 15)));
    }

    /** @return array<string> */
    private function extractEntities(string $title, string $body): array
    {
        $entities = [];

        $text = implode(' ', array_filter([$title, $body]));
        $text = preg_replace('/([.!?]\s+)[A-Z]/', '$1_', $text);
        if ($text === null) {
            $text = '';
        }

        if (preg_match_all('/\b([A-Z][a-z]+(?:\s+[A-Z][a-z]+)+)\b/', $text, $matches)) {
            foreach ($matches[1] as $match) {
                $match = trim($match);
                if (mb_strlen($match) >= 4 && mb_strlen($match) <= 50) {
                    $entities[] = $match;
                }
            }
        }

        if (preg_match_all('/(?<=[a-z,;]\s)([A-Z][a-z]{2,})\b/', $text, $matches)) {
            foreach ($matches[1] as $match) {
                if (! in_array(strtolower($match), self::STOPWORDS, true)) {
                    $entities[] = trim($match);
                }
            }
        }

        return array_values(array_unique(array_slice($entities, 0, 20)));
    }

    /** @return array<string, float> */
    private function extractKeywords(string $text): array
    {
        if (trim($text) === '') {
            return [];
        }

        $words = preg_split('/\W+/u', strtolower($text), -1, PREG_SPLIT_NO_EMPTY);
        if ($words === false) {
            return [];
        }

        $freq = [];
        foreach ($words as $word) {
            if (
                mb_strlen($word) >= self::MIN_WORD_LENGTH
                && ! in_array($word, self::STOPWORDS, true)
                && ! is_numeric($word)
            ) {
                $freq[$word] = ($freq[$word] ?? 0) + 1;
            }
        }

        if (empty($freq)) {
            return [];
        }

        $totalWords = max(1, count($words));

        $scored = [];
        foreach ($freq as $term => $count) {
            $tf = $count / $totalWords;
            $lengthBonus = min(1.0, mb_strlen($term) / 10);
            $scored[$term] = round($tf * (1 + $lengthBonus), 6);
        }

        arsort($scored);

        return array_slice($scored, 0, self::TOP_KEYWORDS, true);
    }

    /** @return array<string> */
    private function extractBigrams(string $text): array
    {
        $words = preg_split('/\W+/u', strtolower($text), -1, PREG_SPLIT_NO_EMPTY);
        if ($words === false) {
            return [];
        }

        $filtered = array_values(array_filter($words, fn (string $w) => mb_strlen($w) >= self::MIN_WORD_LENGTH
            && ! in_array($w, self::STOPWORDS, true)));

        $bigrams = [];
        $count = count($filtered);
        for ($i = 0; $i < $count - 1; $i++) {
            $bigrams[] = $filtered[$i].' '.$filtered[$i + 1];
        }

        $freq = array_count_values($bigrams);
        arsort($freq);

        $result = array_keys(array_filter($freq, fn (int $c) => $c > 1));

        return array_slice($result, 0, 10);
    }

    private function resolveEntityExtractor(): ?EntityExtractor
    {
        if ($this->entityExtractor !== null) {
            return $this->entityExtractor;
        }

        try {
            /** @var EntityExtractor $extractor */
            $extractor = App::make(EntityExtractor::class);

            return $extractor;
        } catch (\Throwable) {
            return null;
        }
    }
}
