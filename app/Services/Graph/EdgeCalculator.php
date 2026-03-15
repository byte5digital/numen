<?php

namespace App\Services\Graph;

use App\Models\Content;
use App\Models\ContentGraphEdge;
use App\Models\ContentGraphNode;
use App\Models\Space;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Computes all graph edges (relationships) for a ContentGraphNode.
 *
 * Supported edge types:
 *  - SIMILAR_TO   : cosine similarity via pgvector embeddings
 *  - SHARES_TOPIC : Jaccard similarity of taxonomy terms
 *  - CITES        : internal hyperlink detection in content body
 *  - CO_MENTIONS  : overlapping NER entity labels
 *  - PRECEDES     : skipped — requires explicit series metadata (future chunk)
 */
class EdgeCalculator
{
    /**
     * Compute all edges for a newly indexed/updated node.
     *
     * @return array<int, ContentGraphEdge>
     */
    public function computeEdges(ContentGraphNode $node, Space $space): array
    {
        /** @var \Illuminate\Database\Eloquent\Collection<int, ContentGraphNode> $existingNodes */
        $existingNodes = ContentGraphNode::where('space_id', $space->id)
            ->where('id', '!=', $node->id)
            ->with('content')
            ->get();

        $edges = [];

        // SIMILAR_TO — pgvector cosine similarity
        $edges = array_merge($edges, $this->computeSimilarTo($node, $existingNodes));

        foreach ($existingNodes as $targetNode) {
            /** @var ContentGraphNode $targetNode */
            $targetContent = $targetNode->content;

            if ($targetContent === null) {
                continue;
            }

            $sourceContent = $node->content;
            if ($sourceContent !== null) {
                // SHARES_TOPIC — Jaccard on taxonomy terms
                $edges = array_merge($edges, $this->computeSharesTopic($node, $sourceContent, $targetNode, $targetContent));

                // CITES — internal link parsing
                $edges = array_merge($edges, $this->computeCites($node, $sourceContent, $targetNode, $targetContent, $space));
            }

            // CO_MENTIONS — overlapping NER entity labels
            $coMentionEdge = $this->computeCoMentions($node, $targetNode);
            if ($coMentionEdge !== null) {
                $edges[] = $coMentionEdge;
            }
        }

        return $edges;
    }

    /**
     * SIMILAR_TO edges via pgvector cosine similarity.
     *
     * Falls back to [] when pgvector is unavailable (SQLite or no extension).
     *
     * @param  \Illuminate\Support\Collection<int, ContentGraphNode>  $existingNodes
     * @return array<int, ContentGraphEdge>
     */
    public function computeSimilarTo(ContentGraphNode $node, Collection $existingNodes): array
    {
        try {
            if (DB::getDriverName() !== 'pgsql') {
                return [];
            }

            $ext = DB::select("SELECT 1 FROM pg_extension WHERE extname = 'vector'");
            if (empty($ext)) {
                return [];
            }
        } catch (\Throwable $e) {
            Log::debug('EdgeCalculator: pgvector check failed', ['error' => $e->getMessage()]);

            return [];
        }

        $threshold = (float) config('numen.graph.similarity_threshold', 0.75);

        try {
            $existingContentIds = $existingNodes->pluck('content_id')->filter()->values()->all();

            if (empty($existingContentIds)) {
                return [];
            }

            $placeholders = implode(',', array_fill(0, count($existingContentIds), '?'));

            /** @var array<int, \stdClass> $rows */
            $rows = DB::select(
                <<<SQL
                SELECT
                    target_ce.content_id AS target_content_id,
                    1 - (
                        (SELECT embedding FROM content_embeddings
                         WHERE content_id = ?
                         ORDER BY chunk_index ASC LIMIT 1)
                        <=>
                        target_ce.embedding
                    ) AS similarity
                FROM content_embeddings target_ce
                WHERE target_ce.content_id IN ({$placeholders})
                ORDER BY similarity DESC
                SQL,
                array_merge([$node->content_id], $existingContentIds),
            );

            $bestScores = [];
            foreach ($rows as $row) {
                $contentId = $row->target_content_id;
                $similarity = (float) $row->similarity;
                if (! isset($bestScores[$contentId]) || $similarity > $bestScores[$contentId]) {
                    $bestScores[$contentId] = $similarity;
                }
            }

            /** @var array<string, ContentGraphNode> $nodeMap */
            $nodeMap = [];
            foreach ($existingNodes as $n) {
                $nodeMap[$n->content_id] = $n;
            }

            $edges = [];
            foreach ($bestScores as $contentId => $similarity) {
                if ($similarity < $threshold) {
                    continue;
                }

                $targetNode = $nodeMap[$contentId] ?? null;
                if ($targetNode === null) {
                    continue;
                }

                $edges[] = new ContentGraphEdge([
                    'space_id' => $node->space_id,
                    'source_id' => $node->id,
                    'target_id' => $targetNode->id,
                    'edge_type' => 'SIMILAR_TO',
                    'weight' => min(1.0, max(0.0, $similarity)),
                    'edge_metadata' => ['similarity' => $similarity],
                ]);
            }

            return $edges;
        } catch (\Throwable $e) {
            Log::warning('EdgeCalculator: computeSimilarTo failed', ['error' => $e->getMessage()]);

            return [];
        }
    }

    /**
     * SHARES_TOPIC edges via Jaccard similarity of taxonomy term IDs.
     *
     * @return array<int, ContentGraphEdge>
     */
    public function computeSharesTopic(
        ContentGraphNode $sourceNode,
        Content $sourceContent,
        ContentGraphNode $targetNode,
        Content $targetContent,
    ): array {
        try {
            $sourceTermIds = $sourceContent->taxonomyTerms()->pluck('taxonomy_terms.id')->all();
            $targetTermIds = $targetContent->taxonomyTerms()->pluck('taxonomy_terms.id')->all();

            if (empty($sourceTermIds) || empty($targetTermIds)) {
                return [];
            }

            $intersection = array_intersect($sourceTermIds, $targetTermIds);
            $union = array_unique(array_merge($sourceTermIds, $targetTermIds));

            if (empty($union)) {
                return [];
            }

            $jaccard = count($intersection) / count($union);

            if ($jaccard <= 0.2) {
                return [];
            }

            return [
                new ContentGraphEdge([
                    'space_id' => $sourceNode->space_id,
                    'source_id' => $sourceNode->id,
                    'target_id' => $targetNode->id,
                    'edge_type' => 'SHARES_TOPIC',
                    'weight' => min(1.0, max(0.0, $jaccard)),
                    'edge_metadata' => [
                        'jaccard' => $jaccard,
                        'shared_terms' => count($intersection),
                        'total_terms' => count($union),
                    ],
                ]),
            ];
        } catch (\Throwable $e) {
            Log::warning('EdgeCalculator: computeSharesTopic failed', ['error' => $e->getMessage()]);

            return [];
        }
    }

    /**
     * CITES edges via internal hyperlink detection in the content body (HTML).
     *
     * Parses href attributes matching /content/{slug} patterns.
     * Directed edge: source to target.
     *
     * @return array<int, ContentGraphEdge>
     */
    public function computeCites(
        ContentGraphNode $sourceNode,
        Content $sourceContent,
        ContentGraphNode $targetNode,
        Content $targetContent,
        Space $space,
    ): array {
        try {
            $version = $sourceContent->currentVersion;
            if ($version === null) {
                return [];
            }

            $body = $version->body;
            if (empty($body)) {
                return [];
            }

            $targetSlug = $targetContent->slug;
            if (empty($targetSlug)) {
                return [];
            }

            $quotedSlug = preg_quote($targetSlug, '/');
            $pattern = '/href=["\'][^"\']*(?:\/content\/'.$quotedSlug.'|\/'.$quotedSlug.')["\'\/]/i';

            if (! preg_match($pattern, $body)) {
                return [];
            }

            return [
                new ContentGraphEdge([
                    'space_id' => $sourceNode->space_id,
                    'source_id' => $sourceNode->id,
                    'target_id' => $targetNode->id,
                    'edge_type' => 'CITES',
                    'weight' => 1.0,
                    'edge_metadata' => ['cited_slug' => $targetSlug],
                ]),
            ];
        } catch (\Throwable $e) {
            Log::warning('EdgeCalculator: computeCites failed', ['error' => $e->getMessage()]);

            return [];
        }
    }

    /**
     * CO_MENTIONS edge via overlapping NER entity_labels arrays.
     *
     * overlap = |A intersect B| / |A union B| — only emits edge when overlap > 0.3.
     */
    public function computeCoMentions(ContentGraphNode $sourceNode, ContentGraphNode $targetNode): ?ContentGraphEdge
    {
        try {
            /** @var array<int, string> $sourceLabels */
            $sourceLabels = $sourceNode->entity_labels ?? [];
            /** @var array<int, string> $targetLabels */
            $targetLabels = $targetNode->entity_labels ?? [];

            if (empty($sourceLabels) || empty($targetLabels)) {
                return null;
            }

            $intersection = array_intersect($sourceLabels, $targetLabels);
            $union = array_unique(array_merge($sourceLabels, $targetLabels));

            if (empty($union)) {
                return null;
            }

            $overlap = count($intersection) / count($union);

            if ($overlap <= 0.3) {
                return null;
            }

            return new ContentGraphEdge([
                'space_id' => $sourceNode->space_id,
                'source_id' => $sourceNode->id,
                'target_id' => $targetNode->id,
                'edge_type' => 'CO_MENTIONS',
                'weight' => min(1.0, max(0.0, $overlap)),
                'edge_metadata' => [
                    'overlap' => $overlap,
                    'shared_entities' => count($intersection),
                    'total_entities' => count($union),
                ],
            ]);
        } catch (\Throwable $e) {
            Log::warning('EdgeCalculator: computeCoMentions failed', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * PRECEDES edge — skipped in this chunk.
     *
     * Requires explicit series metadata (series_id + series_position fields).
     * Will be implemented in a future chunk once that metadata is available.
     *
     * @return array<int, ContentGraphEdge>
     */
    public function computePrecedes(): array
    {
        // Future chunk: requires series metadata
        return [];
    }
}
