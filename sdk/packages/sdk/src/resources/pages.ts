/**
 * Pages resource module.
 * CRUD + tree operations for Numen pages.
 */

import type { NumenClient } from '../core/client.js'
import type { PaginatedResponse } from '../types/api.js'

export interface Page {
  id: string
  title: string
  slug: string
  parent_id?: string | null
  body?: unknown
  meta?: Record<string, unknown>
  order?: number
  status: 'draft' | 'published'
  created_at: string
  updated_at: string
  [key: string]: unknown
}

export interface PageListParams {
  page?: number
  per_page?: number
  parent_id?: string
  status?: string
  search?: string
}

export interface PageCreatePayload {
  title: string
  slug?: string
  parent_id?: string | null
  body?: unknown
  meta?: Record<string, unknown>
  order?: number
  [key: string]: unknown
}

export interface PageUpdatePayload {
  title?: string
  slug?: string
  parent_id?: string | null
  body?: unknown
  meta?: Record<string, unknown>
  order?: number
  [key: string]: unknown
}

export interface PageReorderPayload {
  /** Ordered list of page IDs in their new position. */
  order: string[]
}

export class PagesResource {
  constructor(private readonly client: NumenClient) {}

  /** List pages with optional filters. */
  async list(params: PageListParams = {}): Promise<PaginatedResponse<Page>> {
    return this.client.request<PaginatedResponse<Page>>('GET', '/v1/pages', {
      params: params as Record<string, string | number | boolean | undefined>,
    })
  }

  /** Get a single page by slug. */
  async get(slug: string): Promise<{ data: Page }> {
    return this.client.request<{ data: Page }>('GET', `/v1/pages/${encodeURIComponent(slug)}`)
  }

  /** Create a new page. */
  async create(data: PageCreatePayload): Promise<{ data: Page }> {
    return this.client.request<{ data: Page }>('POST', '/v1/pages', { body: data })
  }

  /** Update an existing page. */
  async update(id: string, data: PageUpdatePayload): Promise<{ data: Page }> {
    return this.client.request<{ data: Page }>('PUT', `/v1/pages/${encodeURIComponent(id)}`, { body: data })
  }

  /** Delete a page. */
  async delete(id: string): Promise<void> {
    return this.client.request<void>('DELETE', `/v1/pages/${encodeURIComponent(id)}`)
  }

  /** Get child pages of a parent. */
  async children(parentId: string, params: PageListParams = {}): Promise<PaginatedResponse<Page>> {
    return this.client.request<PaginatedResponse<Page>>('GET', '/v1/pages', {
      params: { parent_id: parentId, ...params } as Record<string, string | number | boolean | undefined>,
    })
  }

  /** Reorder pages under a parent. */
  async reorder(data: PageReorderPayload): Promise<void> {
    return this.client.request<void>('POST', '/v1/pages/reorder', { body: data })
  }
}
