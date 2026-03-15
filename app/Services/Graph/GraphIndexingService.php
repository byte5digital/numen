<?php

namespace App\Services\Graph;

use App\Models\Content;
use App\Models\ContentGraphNode;
use Illuminate\Support\Facades\Log;

/**
 * Orchestrates entity extraction and knowledge-graph node upsert for a Content record.
 */
class GraphIndexingService
{
    public function __construct(
        private readonly EntityExtractor $entityExtractor,
    ) {}

    /**
     * Index a Content record into the knowledge graph.
     *
     * Creates or updates the ContentGraphNode for the given content, storing
     * extracted entity labels and core metadata. Sets `indexed_at` to now.
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

        return $node;
    }
}
