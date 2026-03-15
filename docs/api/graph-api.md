# Knowledge Graph API Reference

**Version:** 1.0.0  
**Base URL:** `/api/v1/graph`  
**Added:** 2026-03-15

All Knowledge Graph endpoints require Sanctum authentication.

## Edge Types

| Edge Type | Computation Method | Description |
|-----------|-------------------|-------------|
| `semantic` | Cosine similarity on AI embeddings (threshold: `GRAPH_SIMILARITY_THRESHOLD`) | Content that is topically similar based on vector embeddings |
| `co_tag` | Shared taxonomy tags via `content_tag` pivot | Content sharing one or more taxonomy tags |
| `co_author` | Shared author via `author_id` | Content written by the same author |
| `sequential` | Ordered content within the same series/collection | Part-of-series relationships, ordered by `sort_order` |
| `co_entity` | Named entities extracted by AI from content body | Content sharing one or more named entities |

## Endpoints

### 1. GET /api/v1/graph/related/{contentId}

Returns content related to a given content item.

Query params: `space_id` (required), `edge_type` (optional), `limit` (optional, default 10)

Response:
```json
{"data":[{"content_id":"01J...","title":"...","slug":"...","edge_type":"semantic","weight":0.91,"locale":"en"}]}
```

### 2. GET /api/v1/graph/clusters

Returns topic cluster summaries for a space.

Query params: `space_id` (required), `limit` (optional, default 20)

Response:
```json
{"data":[{"cluster_id":"01J...","label":"DevOps","content_count":14,"top_entities":["Kubernetes","Docker"],"centroid_content_id":"01J..."}]}
```

### 3. GET /api/v1/graph/clusters/{clusterId}

Returns all content nodes within a specific cluster.

Response:
```json
{"data":[{"content_id":"01J...","title":"...","slug":"...","locale":"en","cluster_id":"01J...","entity_labels":["Kubernetes"],"indexed_at":"2026-03-15T14:30:00+00:00"}]}
```

### 4. GET /api/v1/graph/gaps

Identifies clusters with low content coverage (content gap analysis).

Query params: `space_id` (required)

Gap score (0-1): higher means more opportunity. Computed as avg_edge_weight_to_neighbors - own_content_density.

Response:
```json
{"data":[{"cluster_id":"01J...","label":"Frontend Perf","content_count":2,"gap_score":0.82,"suggested_topics":["Lazy Loading","Bundle Splitting"]}]}
```

### 5. GET /api/v1/graph/path/{fromId}/{toId}

Finds shortest path between two content nodes.

Query params: `space_id` (required), `max_depth` (optional, default 5, max 10)

Response 200:
```json
{"data":{"path":["01J...","01J...","01J..."],"length":3}}
```
Response 404: `{"data":null,"message":"No path found"}`

### 6. GET /api/v1/graph/node/{contentId}

Returns graph node metadata for a single content item.

Query params: `space_id` (required)

Response:
```json
{"data":{"node_id":"01J...","content_id":"01J...","space_id":"my-blog","locale":"en","cluster_id":"01J...","entity_labels":["Kubernetes","Docker"],"node_metadata":{"embedding_model":"text-embedding-3-small","entity_count":4,"edge_count":12},"indexed_at":"2026-03-15T14:30:00+00:00"}}
```

### 7. POST /api/v1/graph/reindex/{contentId}

Triggers re-indexing of a content node. Requires `search.admin` permission.

Request body: `{"space_id":"my-blog"}`

Response:
```json
{"data":{"node_id":"01J...","content_id":"01J...","indexed_at":"2026-03-15T15:00:00+00:00"}}
```

## Configuration

| Variable | Default | Description |
|----------|---------|-------------|
| `GRAPH_ENABLED` | `true` | Enable/disable the Knowledge Graph feature |
| `GRAPH_SIMILARITY_THRESHOLD` | `0.75` | Minimum cosine similarity to create a semantic edge |
| `GRAPH_MAX_EDGES_PER_TYPE` | `20` | Maximum edges of each type per content node |

## Visualization

The graph can be rendered using the built-in D3.js force-directed visualization at `/studio/graph/{spaceId}` in Numen Studio. Nodes are colour-coded by cluster; edge thickness indicates weight.

## Related Content Widget

Use `GET /api/v1/graph/related/{contentId}?edge_type=semantic&limit=5` to power a related content sidebar widget on headless frontends.
