/**
 * Quality resource module.
 * Get scores, trends, score content, manage config.
 */

import type { NumenClient, RequestOptions } from '../core/client.js'
import type { PaginatedResponse } from '../types/api.js'

export interface QualityScore {
  id: string
  content_id?: string
  overall: number
  dimensions?: Record<string, number>
  created_at: string
  [key: string]: unknown
}

export interface QualityScoreListParams {
  page?: number
  per_page?: number
}

export interface QualityConfig {
  [key: string]: unknown
}

export class QualityResource {
  constructor(private readonly client: NumenClient) {}

  /** List quality scores. */
  async scores(params: QualityScoreListParams = {}): Promise<PaginatedResponse<QualityScore>> {
    return this.client.request<PaginatedResponse<QualityScore>>('GET', '/v1/quality/scores', {
      params: params as Record<string, string | number | boolean | undefined>,
    })
  }

  /** Get a single quality score. */
  async getScore(id: string): Promise<{ data: QualityScore }> {
    return this.client.request<{ data: QualityScore }>(
      'GET',
      `/v1/quality/scores/${encodeURIComponent(id)}`,
    )
  }

  /** Score a content item (recalculate). */
  async score(data: { content_id: string; [key: string]: unknown }): Promise<{ data: QualityScore }> {
    return this.client.request<{ data: QualityScore }>('POST', '/v1/quality/score', { body: data })
  }

  /** Get quality trends. */
  async trends(): Promise<{ data: unknown }> {
    return this.client.request<{ data: unknown }>('GET', '/v1/quality/trends')
  }

  /** Get quality config. */
  async getConfig(): Promise<{ data: QualityConfig }> {
    return this.client.request<{ data: QualityConfig }>('GET', '/v1/quality/config')
  }

  /** Update quality config. */
  async updateConfig(data: QualityConfig): Promise<{ data: QualityConfig }> {
    return this.client.request<{ data: QualityConfig }>('PUT', '/v1/quality/config', { body: data })
  }
}
