<?php

namespace App\Services\Graph;

use App\Models\Content;
use App\Models\ContentGraphEdge;
use App\Models\ContentGraphNode;
use App\Models\Space;
use Illuminate\Support\Facades\Log;

/**
 * Orchestrates entity extraction and knowledge-graph node upsert for a Content record.
 */
class GraphIndexingService
{
    public function __construct(
        private readonly EntityExtractor $entityExtractor,
        private readonly EdgeCalculator $edgeCalculator,
    ) {}

    /**
     * Index a Content record into the knowledge graph.
     *
     * Creates or updates the ContentGraphNode for the given content, storing
     * extracted entity labels and core metadata. Sets indexed_at to now.
     * After upserting the node, computes and persists all edges.
     */
    public function indexContent(Content $content): ContentGraphNode
    {
        // Extract entities (returns [] on any failure — safe to continue)
        $entities = $this->entityExtractor->extract($content);

        /** @var array<int, string> $entityLabels */
        $entityLabels = array_values(
            array_map(fn (array $e): string => $e['entity'], $entities)
        );

        $version = $content->currentVersion;

        /** @var array<string, mixed> $nodeMetadata */
        $nodeMetadata = [
            'title' => $version !== null ? $version->title : '',
            'slug' => $content->slug,
            'content_type' => $content->content_type_id,
            'published_at' => $content->published_at?->toIso8601String(),
            'entities' => $entities,
        ];

        /** @var ContentGraphNode $node */
        $node = ContentGraphNode::updateOrCreate(
            ['content_id' => $content->id],
            [
                'space_id' => $content->space_id,
                'locale' => $content->locale,
                'entity_labels' => $entityLabels,
                'node_metadata' => $nodeMetadata,
                'indexed_at' => now(),
            ],
        );

        Log::info('GraphIndexingService: node upserted', [
            'content_id' => $content->id,
            'node_id' => $node->id,
            'entity_count' => count($entityLabels),
        ]);

        // Ensure the content relationship is loaded for EdgeCalculator
        $node->load('content');

        $space = Space::find($content->space_id);
        if ($space !== null) {
            $this->persistEdges($node, $space);
        }

        return $node;
    }

    /**
     * Compute edges for the node and bulk-upsert them, capped at max_edges_per_type.
     */
    private function persistEdges(ContentGraphNode $node, Space $space): void
    {
        $maxPerType = (int) config('numen.graph.max_edges_per_type', 20);

        try {
            $allEdges = $this->edgeCalculator->computeEdges($node, $space);

            // Group by edge_type
            /** @var array<string, array<int, ContentGraphEdge>> $grouped */
            $grouped = [];
            foreach ($allEdges as $edge) {
                $grouped[$edge->edge_type][] = $edge;
            }

            $saved = 0;
            foreach ($grouped as $edgeType => $edges) {
                // Sort descending by weight, keep top-N
                usort($edges, fn (ContentGraphEdge $a, ContentGraphEdge $b): int => $b->weight <=> $a->weight);
                $topEdges = array_slice($edges, 0, $maxPerType);

                foreach ($topEdges as $edge) {
                    ContentGraphEdge::updateOrCreate(
                        [
                            'source_id' => $edge->source_id,
                            'target_id' => $edge->target_id,
                            'edge_type' => $edge->edge_type,
                        ],
                        [
                            'space_id' => $edge->space_id,
                            'weight' => $edge->weight,
                            'edge_metadata' => $edge->edge_metadata,
                        ],
                    );
                    $saved++;
                }
            }

            Log::info('GraphIndexingService: edges persisted', [
                'node_id' => $node->id,
                'edge_types' => array_keys($grouped),
                'saved' => $saved,
            ]);
        } catch (\Throwable $e) {
            Log::warning('GraphIndexingService: edge persistence failed', [
                'node_id' => $node->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
