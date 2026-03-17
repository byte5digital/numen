/**
 * Search resource module.
 * Keyword search, semantic suggestion, and conversational search for Numen.
 */

import type { NumenClient } from '../core/client.js'

export interface SearchParams {
  q: string
  type?: string
  page?: number
  per_page?: number
  [key: string]: string | number | boolean | undefined
}

export interface SearchResult {
  id: string
  title: string
  slug: string
  type: string
  excerpt?: string
  score?: number
  highlights?: Record<string, string[]>
  [key: string]: unknown
}

export interface SearchResponse {
  data: SearchResult[]
  meta: {
    total: number
    page: number
    perPage: number
    query: string
  }
}

export interface SuggestResponse {
  data: string[]
}

export interface AskPayload {
  question: string
  context?: string
  conversation_id?: string
}

export interface AskResponse {
  data: {
    answer: string
    sources: SearchResult[]
    conversation_id?: string
  }
}

export class SearchResource {
  constructor(private readonly client: NumenClient) {}

  /** Keyword search across content. */
  async search(params: SearchParams): Promise<SearchResponse> {
    return this.client.request<SearchResponse>('GET', '/v1/search', {
      params: params as Record<string, string | number | boolean | undefined>,
    })
  }

  /** Get search suggestions / autocomplete. */
  async suggest(params: { q: string }): Promise<SuggestResponse> {
    return this.client.request<SuggestResponse>('GET', '/v1/search/suggest', {
      params: params as Record<string, string | number | boolean | undefined>,
    })
  }

  /** Conversational search — ask a question and get an AI-generated answer. */
  async ask(data: AskPayload): Promise<AskResponse> {
    return this.client.request<AskResponse>('POST', '/v1/search/ask', { body: data })
  }

  /** Record a click event for search analytics. */
  async recordClick(data: { query: string; content_id: string; position?: number }): Promise<void> {
    return this.client.request<void>('POST', '/v1/search/click', { body: data })
  }
}
