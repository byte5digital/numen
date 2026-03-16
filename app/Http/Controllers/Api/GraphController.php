<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Content;
use App\Models\ContentGraphNode;
use App\Models\User;
use App\Services\Graph\GraphIndexingService;
use App\Services\Graph\GraphQueryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GraphController extends Controller
{
    public function __construct(
        private readonly GraphQueryService $queryService,
        private readonly GraphIndexingService $indexingService,
    ) {}

    /**
     * GET /api/v1/graph/related/{contentId}
     * Returns content related to the given content ID.
     */
    public function related(Request $request, string $contentId): JsonResponse
    {
        $validated = $request->validate([
            'space_id' => ['required', 'string'],
            'edge_type' => ['nullable', 'string'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $results = $this->queryService->relatedContent(
            contentId: $contentId,
            spaceId: $validated['space_id'],
            edgeType: $validated['edge_type'] ?? null,
            limit: (int) ($validated['limit'] ?? 10),
        );

        return response()->json(['data' => $results]);
    }

    /**
     * GET /api/v1/graph/clusters?space_id=X
     * Returns topic cluster summaries for a space.
     */
    public function clusters(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'space_id' => ['required', 'string'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $results = $this->queryService->topicClusters(
            spaceId: $validated['space_id'],
            limit: (int) ($validated['limit'] ?? 20),
        );

        return response()->json(['data' => $results]);
    }

    /**
     * GET /api/v1/graph/clusters/{clusterId}
     * Returns all content nodes within a specific cluster.
     */
    public function clusterContents(string $clusterId): JsonResponse
    {
        $results = $this->queryService->clusterContents($clusterId);

        return response()->json(['data' => $results]);
    }

    /**
     * GET /api/v1/graph/gaps?space_id=X
     * Returns content gap clusters for a space.
     */
    public function gaps(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'space_id' => ['required', 'string'],
        ]);

        $results = $this->queryService->contentGaps($validated['space_id']);

        return response()->json(['data' => $results]);
    }

    /**
     * GET /api/v1/graph/path/{fromId}/{toId}
     * Returns shortest path between two content nodes.
     */
    public function path(Request $request, string $fromId, string $toId): JsonResponse
    {
        $validated = $request->validate([
            'space_id' => ['required', 'string'],
            'max_depth' => ['nullable', 'integer', 'min:1', 'max:10'],
        ]);

        $path = $this->queryService->shortestPath(
            fromContentId: $fromId,
            toContentId: $toId,
            spaceId: $validated['space_id'],
            maxDepth: (int) ($validated['max_depth'] ?? 5),
        );

        if ($path === null) {
            return response()->json(['data' => null, 'message' => 'No path found'], 404);
        }

        return response()->json(['data' => ['path' => $path, 'length' => count($path)]]);
    }

    /**
     * GET /api/v1/graph/node/{contentId}
     * Returns the graph node for a content ID.
     */
    public function node(Request $request, string $contentId): JsonResponse
    {
        $validated = $request->validate([
            'space_id' => ['required', 'string'],
        ]);

        $node = ContentGraphNode::where('content_id', $contentId)
            ->where('space_id', $validated['space_id'])
            ->first();

        if ($node === null) {
            return response()->json(['message' => 'Node not found'], 404);
        }

        return response()->json(['data' => [
            'node_id' => $node->id,
            'content_id' => $node->content_id,
            'space_id' => $node->space_id,
            'locale' => $node->locale,
            'cluster_id' => $node->cluster_id,
            'entity_labels' => $node->entity_labels,
            'node_metadata' => $node->node_metadata,
            'indexed_at' => $node->indexed_at?->toIso8601String(),
        ]]);
    }

    /**
     * POST /api/v1/graph/reindex/{contentId}
     * Triggers re-indexing of a content node (admin only).
     */
    public function reindex(Request $request, string $contentId): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (! $user->hasPermission('search.admin')) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'space_id' => ['required', 'string'],
        ]);

        $content = Content::where('id', $contentId)
            ->where('space_id', $validated['space_id'])
            ->firstOrFail();

        $node = $this->indexingService->indexContent($content);

        return response()->json(['data' => [
            'node_id' => $node->id,
            'content_id' => $node->content_id,
            'indexed_at' => $node->indexed_at?->toIso8601String(),
        ]]);
    }

    /**
     * GET /api/v1/graph/space/{spaceId}
     * Returns all nodes and edges for a space (for the knowledge graph visualiser).
     */
    public function space(Request $request, string $spaceId): JsonResponse
    {
        $nodes = ContentGraphNode::where('space_id', $spaceId)
            ->limit(500)
            ->get()
            ->map(fn (ContentGraphNode $n): array => [
                'id' => $n->content_id,
                'title' => $n->node_metadata['title'] ?? 'Untitled',
                'content_type' => $n->node_metadata['content_type'] ?? null,
                'entity_labels' => $n->entity_labels ?? [],
                'cluster_id' => $n->cluster_id ?? 0,
                'cluster_label' => $n->cluster_id,
                'edge_count' => 0,
            ]);

        $nodeIds = ContentGraphNode::where('space_id', $spaceId)->pluck('id');

        $edges = \App\Models\ContentGraphEdge::where('space_id', $spaceId)
            ->whereIn('source_id', $nodeIds)
            ->limit(2000)
            ->get()
            ->map(fn (\App\Models\ContentGraphEdge $e): array => [
                'source_id' => $e->sourceNode->content_id ?? $e->source_id,
                'target_id' => $e->targetNode->content_id ?? $e->target_id,
                'weight' => $e->weight,
                'edge_type' => $e->edge_type,
            ]);

        return response()->json(['data' => [
            'nodes' => $nodes->values(),
            'edges' => $edges->values(),
        ]]);
    }
}
