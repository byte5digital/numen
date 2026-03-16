<?php

namespace App\Services\Competitor;

use App\Models\CompetitorContentItem;
use App\Models\ContentFingerprint;
use Illuminate\Support\Collection;

class SimilarContentFinder
{
    private const BATCH_SIZE = 200;

    public function __construct(
        private readonly SimilarityCalculator $calculator,
    ) {}

    /**
     * Find competitor content items similar to the given fingerprint.
     *
     * @param  float  $threshold  Minimum similarity score (0-1)
     * @param  int  $limit  Maximum number of results to return
     * @return Collection<int, array{item: CompetitorContentItem, score: float, fingerprint: ContentFingerprint}>
     */
    public function findSimilar(
        ContentFingerprint $fingerprint,
        float $threshold = 0.3,
        int $limit = 10
    ): Collection {
        $threshold = max(0.0, min(1.0, $threshold));
        $limit = max(1, $limit);

        /** @var array<int, array{item: CompetitorContentItem, score: float, fingerprint: ContentFingerprint}> $scored */
        $scored = [];

        ContentFingerprint::query()
            ->where('fingerprintable_type', CompetitorContentItem::class)
            ->where('id', '!=', $fingerprint->id)
            ->with('fingerprintable')
            ->chunkById(self::BATCH_SIZE, function (Collection $chunk) use ($fingerprint, $threshold, &$scored): void {
                foreach ($chunk as $candidate) {
                    /** @var ContentFingerprint $candidate */
                    $item = $candidate->fingerprintable;

                    if (! $item instanceof CompetitorContentItem) {
                        continue;
                    }

                    $score = $this->calculator->calculateSimilarity($fingerprint, $candidate);

                    if ($score >= $threshold) {
                        $scored[] = [
                            'item' => $item,
                            'score' => $score,
                            'fingerprint' => $candidate,
                        ];
                    }
                }
            });

        usort($scored, fn (array $a, array $b) => $b['score'] <=> $a['score']);

        return collect(array_slice($scored, 0, $limit));
    }
}
