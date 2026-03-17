/**
 * Graph resource module.
 * Query knowledge graph, get node, relationships, clusters, gaps.
 */

import type { NumenClient, RequestOptions } from '../core/client.js'

export interface GraphNode {
  id: string
  content_id: string
  label?: string
  type?: string
  relationships?: GraphRelationship[]
  [key: string]: unknown
}

export interface GraphRelationship {
  id: string
  from: string
  to: string
  type: string
  weight?: number
  [key: string]: unknown
}

export interface GraphCluster {
  id: string
  name?: string
  contents: string[]
  [key: string]: unknown
}

export class GraphResource {
  constructor(private readonly client: NumenClient) {}

  /** Get related content for a content item. */
  async related(contentId: string): Promise<{ data: unknown[] }> {
    return this.client.request<{ data: unknown[] }>(
      'GET',
      `/v1/graph/related/${encodeURIComponent(contentId)}`,
    )
  }

  /** List topic clusters. */
  async clusters(): Promise<{ data: GraphCluster[] }> {
    return this.client.request<{ data: GraphCluster[] }>('GET', '/v1/graph/clusters')
  }

  /** Get contents within a cluster. */
  async clusterContents(clusterId: string): Promise<{ data: unknown[] }> {
    return this.client.request<{ data: unknown[] }>(
      'GET',
      `/v1/graph/clusters/${encodeURIComponent(clusterId)}`,
    )
  }

  /** Get content gaps in the knowledge graph. */
  async gaps(): Promise<{ data: unknown[] }> {
    return this.client.request<{ data: unknown[] }>('GET', '/v1/graph/gaps')
  }

  /** Get path between two content items. */
  async path(fromId: string, toId: string): Promise<{ data: unknown }> {
    return this.client.request<{ data: unknown }>(
      'GET',
      `/v1/graph/path/${encodeURIComponent(fromId)}/${encodeURIComponent(toId)}`,
    )
  }

  /** Get a single graph node by content ID. */
  async node(contentId: string): Promise<{ data: GraphNode }> {
    return this.client.request<{ data: GraphNode }>(
      'GET',
      `/v1/graph/node/${encodeURIComponent(contentId)}`,
    )
  }

  /** Get graph for a space. */
  async space(spaceId: string): Promise<{ data: unknown }> {
    return this.client.request<{ data: unknown }>(
      'GET',
      `/v1/graph/space/${encodeURIComponent(spaceId)}`,
    )
  }

  /** Reindex a content item in the knowledge graph. */
  async reindex(contentId: string): Promise<{ data: unknown }> {
    return this.client.request<{ data: unknown }>(
      'POST',
      `/v1/graph/reindex/${encodeURIComponent(contentId)}`,
    )
  }
}
