/**
 * Repurpose resource module.
 * Manage repurposed content, generate, list formats.
 */

import type { NumenClient, RequestOptions } from '../core/client.js'
import type { PaginatedResponse } from '../types/api.js'

export interface FormatTemplate {
  id: string
  name: string
  description?: string
  [key: string]: unknown
}

export class RepurposeResource {
  constructor(private readonly client: NumenClient) {}

  /** List supported format templates. */
  async formats(): Promise<{ data: FormatTemplate[] }> {
    return this.client.request<{ data: FormatTemplate[] }>('GET', '/v1/format-templates/supported')
  }
}
