<?php

namespace App\Services\Versioning;

use App\Models\ContentVersion;
use Jfcherng\Diff\Differ;

class DiffEngine
{
    /**
     * Compare two ContentVersions and produce a VersionDiff.
     */
    public function compare(ContentVersion $a, ContentVersion $b): VersionDiff
    {
        return new VersionDiff(
            versionA: $a,
            versionB: $b,
            fieldDiffs: $this->diffFields($a, $b),
            blockDiffs: $this->diffBlocks($a, $b),
            seoDiffs: $this->diffSeoData($a->seo_data ?? [], $b->seo_data ?? []),
        );
    }

    /**
     * Diff scalar fields: title, excerpt, body.
     *
     * @return array<string, array<string, mixed>>
     */
    private function diffFields(ContentVersion $a, ContentVersion $b): array
    {
        $diffs = [];

        foreach (['title', 'excerpt'] as $field) {
            if ($a->$field !== $b->$field) {
                $diffs[$field] = [
                    'type' => 'changed',
                    'old' => $a->$field,
                    'new' => $b->$field,
                    'hunks' => $this->wordDiff((string) ($a->$field ?? ''), (string) ($b->$field ?? '')),
                ];
            }
        }

        if ($a->body !== $b->body) {
            $diffs['body'] = [
                'type' => 'changed',
                'hunks' => $this->lineDiff((string) $a->body, (string) $b->body),
                'stats' => $this->diffStats((string) $a->body, (string) $b->body),
            ];
        }

        return $diffs;
    }

    /**
     * Diff content blocks by matching on sort_order + type.
     * Returns added, removed, modified blocks.
     *
     * @return array<int, array<string, mixed>>
     */
    private function diffBlocks(ContentVersion $a, ContentVersion $b): array
    {
        /** @var array<int|string, array<string, mixed>> $blocksA */
        $blocksA = $a->blocks->keyBy('sort_order')->toArray();
        /** @var array<int|string, array<string, mixed>> $blocksB */
        $blocksB = $b->blocks->keyBy('sort_order')->toArray();

        $allKeys = array_unique(array_merge(array_keys($blocksA), array_keys($blocksB)));
        sort($allKeys);

        $diffs = [];

        foreach ($allKeys as $key) {
            $inA = isset($blocksA[$key]);
            $inB = isset($blocksB[$key]);

            if ($inA && ! $inB) {
                $diffs[] = ['type' => 'removed', 'position' => $key, 'block' => $blocksA[$key]];
            } elseif (! $inA && $inB) {
                $diffs[] = ['type' => 'added', 'position' => $key, 'block' => $blocksB[$key]];
            } elseif ($blocksA[$key]['data'] !== $blocksB[$key]['data']
                || $blocksA[$key]['type'] !== $blocksB[$key]['type']) {
                $diffs[] = [
                    'type' => 'modified',
                    'position' => $key,
                    'old' => $blocksA[$key],
                    'new' => $blocksB[$key],
                ];
            }
        }

        return $diffs;
    }

    /**
     * Line-level diff using jfcherng/php-diff.
     *
     * @return array<int, array<string, mixed>>
     */
    private function lineDiff(string $a, string $b): array
    {
        $differ = new Differ(
            explode("\n", $a),
            explode("\n", $b),
            ['context' => 3],
        );

        return $differ->getGroupedOpcodes();
    }

    /**
     * Word-level diff for short fields (title, excerpt).
     * Splits on word boundaries and compares tokens.
     *
     * @return array<int, array<string, mixed>>
     */
    private function wordDiff(string $a, string $b): array
    {
        $wordsA = preg_split('/(\s+)/', $a, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [];
        $wordsB = preg_split('/(\s+)/', $b, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [];

        $differ = new Differ($wordsA, $wordsB, ['context' => 5]);

        return $differ->getGroupedOpcodes();
    }

    /**
     * @return array<string, int>
     */
    private function diffStats(string $a, string $b): array
    {
        $linesA = substr_count($a, "\n") + 1;
        $linesB = substr_count($b, "\n") + 1;

        return [
            'lines_added' => max(0, $linesB - $linesA),
            'lines_removed' => max(0, $linesA - $linesB),
            'words_old' => str_word_count($a),
            'words_new' => str_word_count($b),
        ];
    }

    /**
     * @param  array<string, mixed>  $a
     * @param  array<string, mixed>  $b
     * @return array<string, array<string, mixed>>
     */
    private function diffSeoData(array $a, array $b): array
    {
        $diffs = [];
        $allKeys = array_unique(array_merge(array_keys($a), array_keys($b)));

        foreach ($allKeys as $key) {
            if (($a[$key] ?? null) !== ($b[$key] ?? null)) {
                $diffs[$key] = ['old' => $a[$key] ?? null, 'new' => $b[$key] ?? null];
            }
        }

        return $diffs;
    }
}
