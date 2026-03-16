<?php

namespace App\Services\Competitor;

use App\Models\CompetitorContentItem;
use App\Models\ContentGraphEdge;
use App\Models\ContentGraphNode;
use Illuminate\Support\Facades\Log;

/**
 * Indexes competitor content items into the knowledge graph,
 * creating virtual nodes and competitor_similarity edges.
 */
class CompetitorGraphIndexer
{
    private const NODE_PREFIX = 'competitor:';

    private const EDGE_TYPE = 'competitor_similarity';

    /**
     * Index a competitor content item as a virtual graph node and link it
     * to similar owned content nodes via competitor_similarity edges.
     *
     * @param  array<int, array{content_id: string, similarity_score: float}>  $similarContentPairs
     */
    public function index(CompetitorContentItem $item, array $similarContentPairs = []): string
    {
        $nodeId = $this->nodeIdForItem($item);

        // Upsert the competitor graph node (virtual — no owned content_id)
        $this->upsertNode($nodeId, $item);

        // Create competitor_similarity edges to owned content nodes
        foreach ($similarContentPairs as $pair) {
            $this->upsertSimilarityEdge($nodeId, $pair['content_id'], (float) $pair['similarity_score'], ($item->source !== null ? $item->source->space_id : ''));
        }

        Log::info('CompetitorGraphIndexer: indexed item', [
            'competitor_content_id' => $item->id,
            'node_id' => $nodeId,
            'similarity_pairs' => count($similarContentPairs),
        ]);

        return $nodeId;
    }

    /**
     * Remove all graph nodes and edges for a competitor source.
     */
    public function removeSourceNodes(string $sourceId): int
    {
        // Find all node ids belonging to this source
        $nodeIds = ContentGraphNode::where('node_metadata->source_id', $sourceId)
            ->pluck('id');

        if ($nodeIds->isEmpty()) {
            return 0;
        }

        // Delete edges
        ContentGraphEdge::whereIn('source_id', $nodeIds)
            ->orWhereIn('target_id', $nodeIds)
            ->delete();

        // Delete nodes
        ContentGraphNode::whereIn('id', $nodeIds)->delete();

        return $nodeIds->count();
    }

    // ─────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────

    private function nodeIdForItem(CompetitorContentItem $item): string
    {
        // Stable deterministic ID based on competitor content ID
        // We use a ULID-compatible 26-char string derived from the item id
        return substr(sha1(self::NODE_PREFIX.$item->id), 0, 26);
    }

    private function upsertNode(string $nodeId, CompetitorContentItem $item): void
    {
        $source = $item->source;
        $spaceId = $source !== null ? $source->space_id : '';

        /** @var array<string, mixed> $metadata */
        $metadata = [
            'competitor' => true,
            'source_id' => $item->source_id,
            'source_name' => $source !== null ? $source->name : null,
            'external_url' => $item->external_url,
            'title' => $item->title,
            'published_at' => $item->published_at?->toIso8601String(),
        ];

        // Build entity labels from title words (simple keyword extraction)
        $entityLabels = $item->title !== null
            ? array_values(array_unique(array_filter(
                explode(' ', preg_replace('/[^\w\s]/u', '', $item->title) ?? ''),
                fn (string $w): bool => mb_strlen($w) > 3,
            )))
            : [];

        // Use updateOrCreate keyed on the virtual node id
        ContentGraphNode::updateOrCreate(
            ['id' => $nodeId],
            [
                'id' => $nodeId,
                'content_id' => $nodeId,  // virtual — same as node id
                'space_id' => $spaceId,
                'locale' => 'en',
                'entity_labels' => $entityLabels,
                'node_metadata' => $metadata,
                'indexed_at' => now(),
            ],
        );
    }

    private function upsertSimilarityEdge(
        string $competitorNodeId,
        string $ownedContentId,
        float $similarityScore,
        string $spaceId,
    ): void {
        // Find the owned content's graph node
        $ownedNode = ContentGraphNode::where('content_id', $ownedContentId)->first();

        if (! $ownedNode) {
            return;
        }

        ContentGraphEdge::updateOrCreate(
            [
                'source_id' => $competitorNodeId,
                'target_id' => $ownedNode->id,
                'edge_type' => self::EDGE_TYPE,
            ],
            [
                'space_id' => $spaceId,
                'weight' => round($similarityScore, 6),
                'edge_metadata' => [
                    'competitor_node_id' => $competitorNodeId,
                    'owned_node_id' => $ownedNode->id,
                    'owned_content_id' => $ownedContentId,
                    'indexed_at' => now()->toIso8601String(),
                ],
            ],
        );
    }
}
