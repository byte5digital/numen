/**
 * Pipeline resource module.
 * List runs, get run, start, cancel, retry step.
 */

import type { NumenClient, RequestOptions } from '../core/client.js'
import type { PaginatedResponse } from '../types/api.js'

export interface PipelineRun {
  id: string
  status: string
  brief_id?: string
  steps?: unknown[]
  started_at?: string | null
  completed_at?: string | null
  created_at: string
  updated_at: string
  [key: string]: unknown
}

export interface PipelineRunListParams {
  page?: number
  per_page?: number
  status?: string
}

export class PipelineResource {
  constructor(private readonly client: NumenClient) {}

  /** Get a pipeline run by ID. */
  async get(id: string): Promise<{ data: PipelineRun }> {
    return this.client.request<{ data: PipelineRun }>('GET', `/v1/pipeline-runs/${encodeURIComponent(id)}`)
  }

  /** Approve/start a pipeline run. */
  async approve(id: string): Promise<{ data: PipelineRun }> {
    return this.client.request<{ data: PipelineRun }>(
      'POST',
      `/v1/pipeline-runs/${encodeURIComponent(id)}/approve`,
    )
  }
}
