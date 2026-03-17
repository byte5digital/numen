/**
 * Competitor resource module.
 * Sources CRUD, crawl, content, alerts, differentiation.
 */

import type { NumenClient, RequestOptions } from '../core/client.js'
import type { PaginatedResponse } from '../types/api.js'

export interface CompetitorSource {
  id: string
  name: string
  url: string
  active: boolean
  created_at: string
  updated_at: string
  [key: string]: unknown
}

export interface CompetitorAlert {
  id: string
  type: string
  message?: string
  created_at: string
  [key: string]: unknown
}

export interface Differentiation {
  id: string
  content_id?: string
  score?: number
  [key: string]: unknown
}

export interface CompetitorSourceCreatePayload {
  name: string
  url: string
  [key: string]: unknown
}

export interface CompetitorSourceUpdatePayload {
  name?: string
  url?: string
  active?: boolean
  [key: string]: unknown
}

export interface CompetitorSourceListParams {
  page?: number
  per_page?: number
}

export class CompetitorResource {
  constructor(private readonly client: NumenClient) {}

  /** List competitor sources. */
  async sources(params: CompetitorSourceListParams = {}): Promise<PaginatedResponse<CompetitorSource>> {
    return this.client.request<PaginatedResponse<CompetitorSource>>('GET', '/v1/competitor/sources', {
      params: params as Record<string, string | number | boolean | undefined>,
    })
  }

  /** Get a competitor source by ID. */
  async getSource(id: string): Promise<{ data: CompetitorSource }> {
    return this.client.request<{ data: CompetitorSource }>(
      'GET',
      `/v1/competitor/sources/${encodeURIComponent(id)}`,
    )
  }

  /** Create a competitor source. */
  async createSource(data: CompetitorSourceCreatePayload): Promise<{ data: CompetitorSource }> {
    return this.client.request<{ data: CompetitorSource }>('POST', '/v1/competitor/sources', { body: data })
  }

  /** Update a competitor source. */
  async updateSource(id: string, data: CompetitorSourceUpdatePayload): Promise<{ data: CompetitorSource }> {
    return this.client.request<{ data: CompetitorSource }>(
      'PATCH',
      `/v1/competitor/sources/${encodeURIComponent(id)}`,
      { body: data },
    )
  }

  /** Delete a competitor source. */
  async deleteSource(id: string): Promise<void> {
    return this.client.request<void>('DELETE', `/v1/competitor/sources/${encodeURIComponent(id)}`)
  }

  /** Trigger a crawl for a source. */
  async crawl(id: string): Promise<{ data: unknown }> {
    return this.client.request<{ data: unknown }>(
      'POST',
      `/v1/competitor/sources/${encodeURIComponent(id)}/crawl`,
    )
  }

  /** List competitor content. */
  async content(): Promise<{ data: unknown[] }> {
    return this.client.request<{ data: unknown[] }>('GET', '/v1/competitor/content')
  }

  /** List competitor alerts. */
  async alerts(): Promise<{ data: CompetitorAlert[] }> {
    return this.client.request<{ data: CompetitorAlert[] }>('GET', '/v1/competitor/alerts')
  }

  /** Create a competitor alert. */
  async createAlert(data: Record<string, unknown>): Promise<{ data: CompetitorAlert }> {
    return this.client.request<{ data: CompetitorAlert }>('POST', '/v1/competitor/alerts', { body: data })
  }

  /** Delete a competitor alert. */
  async deleteAlert(id: string): Promise<void> {
    return this.client.request<void>('DELETE', `/v1/competitor/alerts/${encodeURIComponent(id)}`)
  }

  /** List differentiation analysis. */
  async differentiation(): Promise<{ data: Differentiation[] }> {
    return this.client.request<{ data: Differentiation[] }>('GET', '/v1/competitor/differentiation')
  }

  /** Get differentiation summary. */
  async differentiationSummary(): Promise<{ data: unknown }> {
    return this.client.request<{ data: unknown }>('GET', '/v1/competitor/differentiation/summary')
  }

  /** Get a specific differentiation analysis. */
  async getDifferentiation(id: string): Promise<{ data: Differentiation }> {
    return this.client.request<{ data: Differentiation }>(
      'GET',
      `/v1/competitor/differentiation/${encodeURIComponent(id)}`,
    )
  }
}
