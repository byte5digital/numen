/**
 * Taxonomies resource module.
 * CRUD for vocabularies + terms, attach/detach from content.
 */

import type { NumenClient } from '../core/client.js'
import type { PaginatedResponse } from '../types/api.js'

export interface Taxonomy {
  id: string
  name: string
  slug: string
  description?: string
  created_at: string
  updated_at: string
  [key: string]: unknown
}

export interface TaxonomyTerm {
  id: string
  taxonomy_id: string
  name: string
  slug: string
  parent_id?: string | null
  order?: number
  created_at: string
  updated_at: string
  [key: string]: unknown
}

export interface TaxonomyCreatePayload {
  name: string
  slug?: string
  description?: string
}

export interface TaxonomyUpdatePayload {
  name?: string
  slug?: string
  description?: string
}

export interface TermCreatePayload {
  name: string
  slug?: string
  parent_id?: string | null
  order?: number
}

export interface TermUpdatePayload {
  name?: string
  slug?: string
  parent_id?: string | null
  order?: number
}

export class TaxonomiesResource {
  constructor(private readonly client: NumenClient) {}

  // ── Vocabularies ──

  /** List all taxonomies. */
  async list(): Promise<{ data: Taxonomy[] }> {
    return this.client.request<{ data: Taxonomy[] }>('GET', '/v1/taxonomies')
  }

  /** Get a single taxonomy by slug. */
  async get(vocabSlug: string): Promise<{ data: Taxonomy }> {
    return this.client.request<{ data: Taxonomy }>('GET', `/v1/taxonomies/${encodeURIComponent(vocabSlug)}`)
  }

  /** Create a new taxonomy vocabulary. */
  async create(data: TaxonomyCreatePayload): Promise<{ data: Taxonomy }> {
    return this.client.request<{ data: Taxonomy }>('POST', '/v1/taxonomies', { body: data })
  }

  /** Update a taxonomy vocabulary. */
  async update(id: string, data: TaxonomyUpdatePayload): Promise<{ data: Taxonomy }> {
    return this.client.request<{ data: Taxonomy }>('PUT', `/v1/taxonomies/${encodeURIComponent(id)}`, { body: data })
  }

  /** Delete a taxonomy vocabulary. */
  async delete(id: string): Promise<void> {
    return this.client.request<void>('DELETE', `/v1/taxonomies/${encodeURIComponent(id)}`)
  }

  // ── Terms ──

  /** List terms in a taxonomy. */
  async listTerms(vocabSlug: string): Promise<{ data: TaxonomyTerm[] }> {
    return this.client.request<{ data: TaxonomyTerm[] }>(
      'GET',
      `/v1/taxonomies/${encodeURIComponent(vocabSlug)}/terms`,
    )
  }

  /** Get a single term by slug within a vocabulary. */
  async getTerm(vocabSlug: string, termSlug: string): Promise<{ data: TaxonomyTerm }> {
    return this.client.request<{ data: TaxonomyTerm }>(
      'GET',
      `/v1/taxonomies/${encodeURIComponent(vocabSlug)}/terms/${encodeURIComponent(termSlug)}`,
    )
  }

  /** Create a new term in a vocabulary. */
  async createTerm(vocabId: string, data: TermCreatePayload): Promise<{ data: TaxonomyTerm }> {
    return this.client.request<{ data: TaxonomyTerm }>(
      'POST',
      `/v1/taxonomies/${encodeURIComponent(vocabId)}/terms`,
      { body: data },
    )
  }

  /** Update a term. */
  async updateTerm(termId: string, data: TermUpdatePayload): Promise<{ data: TaxonomyTerm }> {
    return this.client.request<{ data: TaxonomyTerm }>(
      'PUT',
      `/v1/terms/${encodeURIComponent(termId)}`,
      { body: data },
    )
  }

  /** Delete a term. */
  async deleteTerm(termId: string): Promise<void> {
    return this.client.request<void>('DELETE', `/v1/terms/${encodeURIComponent(termId)}`)
  }

  /** Move a term to a new parent. */
  async moveTerm(termId: string, parentId: string | null): Promise<{ data: TaxonomyTerm }> {
    return this.client.request<{ data: TaxonomyTerm }>(
      'POST',
      `/v1/terms/${encodeURIComponent(termId)}/move`,
      { body: { parent_id: parentId } },
    )
  }

  /** Reorder terms. */
  async reorderTerms(order: string[]): Promise<void> {
    return this.client.request<void>('POST', '/v1/terms/reorder', { body: { order } })
  }

  // ── Content ↔ Taxonomy ──

  /** Get terms attached to a content item. */
  async contentTerms(contentSlug: string): Promise<{ data: TaxonomyTerm[] }> {
    return this.client.request<{ data: TaxonomyTerm[] }>(
      'GET',
      `/v1/content/${encodeURIComponent(contentSlug)}/terms`,
    )
  }

  /** Assign terms to a content item (additive). */
  async assignTerms(contentId: string, termIds: string[]): Promise<void> {
    return this.client.request<void>(
      'POST',
      `/v1/content/${encodeURIComponent(contentId)}/terms`,
      { body: { term_ids: termIds } },
    )
  }

  /** Sync terms on a content item (replace all). */
  async syncTerms(contentId: string, termIds: string[]): Promise<void> {
    return this.client.request<void>(
      'PUT',
      `/v1/content/${encodeURIComponent(contentId)}/terms`,
      { body: { term_ids: termIds } },
    )
  }

  /** Remove a single term from a content item. */
  async removeTerm(contentId: string, termId: string): Promise<void> {
    return this.client.request<void>(
      'DELETE',
      `/v1/content/${encodeURIComponent(contentId)}/terms/${encodeURIComponent(termId)}`,
    )
  }

  /** Get content items tagged with a specific term. */
  async termContent(vocabSlug: string, termSlug: string): Promise<{ data: unknown[] }> {
    return this.client.request<{ data: unknown[] }>(
      'GET',
      `/v1/taxonomies/${encodeURIComponent(vocabSlug)}/terms/${encodeURIComponent(termSlug)}/content`,
    )
  }
}
