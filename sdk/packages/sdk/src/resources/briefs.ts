/**
 * Briefs resource module.
 * CRUD briefs, generate, approve.
 */

import type { NumenClient, RequestOptions } from '../core/client.js'
import type { PaginatedResponse } from '../types/api.js'

export interface Brief {
  id: string
  title: string
  status: string
  content?: unknown
  meta?: Record<string, unknown>
  created_at: string
  updated_at: string
  [key: string]: unknown
}

export interface BriefListParams {
  page?: number
  per_page?: number
  status?: string
  search?: string
}

export interface BriefCreatePayload {
  title: string
  content?: unknown
  meta?: Record<string, unknown>
  [key: string]: unknown
}

export class BriefsResource {
  constructor(private readonly client: NumenClient) {}

  /** List briefs with optional filters. */
  async list(params: BriefListParams = {}): Promise<PaginatedResponse<Brief>> {
    return this.client.request<PaginatedResponse<Brief>>('GET', '/v1/briefs', {
      params: params as Record<string, string | number | boolean | undefined>,
    })
  }

  /** Get a single brief by ID. */
  async get(id: string): Promise<{ data: Brief }> {
    return this.client.request<{ data: Brief }>('GET', `/v1/briefs/${encodeURIComponent(id)}`)
  }

  /** Create a new brief. */
  async create(data: BriefCreatePayload): Promise<{ data: Brief }> {
    return this.client.request<{ data: Brief }>('POST', '/v1/briefs', { body: data })
  }

  /** Approve a pipeline run (associated with a brief). */
  async approve(runId: string): Promise<{ data: unknown }> {
    return this.client.request<{ data: unknown }>(
      'POST',
      `/v1/pipeline-runs/${encodeURIComponent(runId)}/approve`,
    )
  }
}
