/**
 * Content resource module.
 * CRUD + publish/unpublish for Numen content items.
 */

import type { NumenClient, RequestOptions } from '../core/client.js'
import type { PaginatedResponse } from '../types/api.js'

export interface ContentItem {
  id: string
  title: string
  slug: string
  type: string
  status: 'draft' | 'published' | 'scheduled' | 'archived'
  body?: unknown
  meta?: Record<string, unknown>
  created_at: string
  updated_at: string
  published_at?: string | null
  [key: string]: unknown
}

export interface ContentListParams {
  page?: number
  per_page?: number
  type?: string
  status?: string
  search?: string
  sort?: string
  order?: 'asc' | 'desc'
}

export interface ContentCreatePayload {
  title: string
  slug?: string
  type: string
  body?: unknown
  meta?: Record<string, unknown>
  [key: string]: unknown
}

export interface ContentUpdatePayload {
  title?: string
  slug?: string
  body?: unknown
  meta?: Record<string, unknown>
  [key: string]: unknown
}

export class ContentResource {
  constructor(private readonly client: NumenClient) {}

  /** List content items with optional filters. */
  async list(params: ContentListParams = {}): Promise<PaginatedResponse<ContentItem>> {
    return this.client.request<PaginatedResponse<ContentItem>>('GET', '/v1/content', {
      params: params as Record<string, string | number | boolean | undefined>,
    })
  }

  /** Get a single content item by slug. */
  async get(slug: string): Promise<{ data: ContentItem }> {
    return this.client.request<{ data: ContentItem }>('GET', `/v1/content/${encodeURIComponent(slug)}`)
  }

  /** Get content items by type. */
  async byType(type: string, params: ContentListParams = {}): Promise<PaginatedResponse<ContentItem>> {
    return this.client.request<PaginatedResponse<ContentItem>>('GET', `/v1/content/type/${encodeURIComponent(type)}`, {
      params: params as Record<string, string | number | boolean | undefined>,
    })
  }

  /** Create a new content item. */
  async create(data: ContentCreatePayload): Promise<{ data: ContentItem }> {
    return this.client.request<{ data: ContentItem }>('POST', '/v1/content', { body: data })
  }

  /** Update an existing content item. */
  async update(id: string, data: ContentUpdatePayload): Promise<{ data: ContentItem }> {
    return this.client.request<{ data: ContentItem }>('PUT', `/v1/content/${encodeURIComponent(id)}`, { body: data })
  }

  /** Delete a content item. */
  async delete(id: string): Promise<void> {
    return this.client.request<void>('DELETE', `/v1/content/${encodeURIComponent(id)}`)
  }

  /** Publish a content version. */
  async publish(contentId: string, versionId: string): Promise<{ data: ContentItem }> {
    return this.client.request<{ data: ContentItem }>(
      'POST',
      `/v1/content/${encodeURIComponent(contentId)}/versions/${encodeURIComponent(versionId)}/publish`,
    )
  }

  /** Unpublish — rollback to draft by creating a new draft version. */
  async unpublish(contentId: string): Promise<{ data: ContentItem }> {
    return this.client.request<{ data: ContentItem }>(
      'POST',
      `/v1/content/${encodeURIComponent(contentId)}/versions/draft`,
    )
  }
}
