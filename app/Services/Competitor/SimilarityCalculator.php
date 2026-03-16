<?php

namespace App\Services\Competitor;

use App\Models\ContentFingerprint;

class SimilarityCalculator
{
    private const JACCARD_WEIGHT = 0.4;

    private const COSINE_WEIGHT = 0.6;

    public function calculateSimilarity(ContentFingerprint $a, ContentFingerprint $b): float
    {
        $jaccardScore = $this->jaccardSimilarity($a, $b);
        $cosineScore = $this->cosineSimilarity($a, $b);

        return round(
            (self::JACCARD_WEIGHT * $jaccardScore) + (self::COSINE_WEIGHT * $cosineScore),
            6
        );
    }

    public function jaccardSimilarity(ContentFingerprint $a, ContentFingerprint $b): float
    {
        $setA = $this->buildTermSet($a);
        $setB = $this->buildTermSet($b);

        if (empty($setA) && empty($setB)) {
            return 0.0;
        }

        $intersection = count(array_intersect($setA, $setB));
        $union = count(array_unique(array_merge($setA, $setB)));

        if ($union === 0) {
            return 0.0;
        }

        return $intersection / $union;
    }

    public function cosineSimilarity(ContentFingerprint $a, ContentFingerprint $b): float
    {
        $vecA = $this->buildKeywordVector($a);
        $vecB = $this->buildKeywordVector($b);

        if (empty($vecA) || empty($vecB)) {
            return 0.0;
        }

        $allTerms = array_unique(array_merge(array_keys($vecA), array_keys($vecB)));

        $dotProduct = 0.0;
        $normA = 0.0;
        $normB = 0.0;

        foreach ($allTerms as $term) {
            $scoreA = $vecA[$term] ?? 0.0;
            $scoreB = $vecB[$term] ?? 0.0;

            $dotProduct += $scoreA * $scoreB;
            $normA += $scoreA * $scoreA;
            $normB += $scoreB * $scoreB;
        }

        $denominator = sqrt($normA) * sqrt($normB);

        if ($denominator < 1e-10) {
            return 0.0;
        }

        return max(0.0, min(1.0, $dotProduct / $denominator));
    }

    /** @return array<string> */
    private function buildTermSet(ContentFingerprint $fp): array
    {
        $topics = array_map('strtolower', array_map('trim', $fp->topics ?? []));
        $entities = array_map('strtolower', array_map('trim', $fp->entities ?? []));

        return array_values(array_unique(array_merge($topics, $entities)));
    }

    /** @return array<string, float> */
    private function buildKeywordVector(ContentFingerprint $fp): array
    {
        $raw = $fp->keywords ?? [];

        $vector = [];
        foreach ($raw as $term => $score) {
            // Handle both formats:
            // 1. Associative: ['machine learning' => 0.5, ...]  (term => score)
            // 2. Numeric-indexed: ['machine learning', 'beginner', ...]  (plain list)
            if (is_int($term)) {
                $key = strtolower(trim((string) $score));
                $val = 1.0;
            } else {
                $key = strtolower(trim((string) $term));
                $val = (float) $score;
            }

            if ($key !== '') {
                $vector[$key] = $val;
            }
        }

        return $vector;
    }
}
