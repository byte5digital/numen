/**
 * Versions resource module.
 * List, get, restore, compare versions for Numen content items.
 */

import type { NumenClient } from '../core/client.js'

export interface ContentVersion {
  id: string
  content_id: string
  version_number: number
  status: 'draft' | 'published' | 'scheduled' | 'archived'
  body?: unknown
  label?: string | null
  created_at: string
  updated_at: string
  published_at?: string | null
  scheduled_at?: string | null
  [key: string]: unknown
}

export interface VersionListParams {
  page?: number
  per_page?: number
}

export interface VersionDiff {
  from_version: string
  to_version: string
  changes: unknown
  [key: string]: unknown
}

export class VersionsResource {
  constructor(private readonly client: NumenClient) {}

  /** List versions for a content item. */
  async list(contentId: string, params: VersionListParams = {}): Promise<{ data: ContentVersion[] }> {
    return this.client.request<{ data: ContentVersion[] }>(
      'GET',
      `/v1/content/${encodeURIComponent(contentId)}/versions`,
      { params: params as Record<string, string | number | boolean | undefined> },
    )
  }

  /** Get a specific version. */
  async get(contentId: string, versionId: string): Promise<{ data: ContentVersion }> {
    return this.client.request<{ data: ContentVersion }>(
      'GET',
      `/v1/content/${encodeURIComponent(contentId)}/versions/${encodeURIComponent(versionId)}`,
    )
  }

  /** Create a new draft version. */
  async createDraft(contentId: string, body?: unknown): Promise<{ data: ContentVersion }> {
    return this.client.request<{ data: ContentVersion }>(
      'POST',
      `/v1/content/${encodeURIComponent(contentId)}/versions/draft`,
      body !== undefined ? { body } : {},
    )
  }

  /** Update a version. */
  async update(contentId: string, versionId: string, data: Partial<ContentVersion>): Promise<{ data: ContentVersion }> {
    return this.client.request<{ data: ContentVersion }>(
      'PATCH',
      `/v1/content/${encodeURIComponent(contentId)}/versions/${encodeURIComponent(versionId)}`,
      { body: data },
    )
  }

  /** Publish a version. */
  async publish(contentId: string, versionId: string): Promise<{ data: ContentVersion }> {
    return this.client.request<{ data: ContentVersion }>(
      'POST',
      `/v1/content/${encodeURIComponent(contentId)}/versions/${encodeURIComponent(versionId)}/publish`,
    )
  }

  /** Rollback to a specific version. */
  async rollback(contentId: string, versionId: string): Promise<{ data: ContentVersion }> {
    return this.client.request<{ data: ContentVersion }>(
      'POST',
      `/v1/content/${encodeURIComponent(contentId)}/versions/${encodeURIComponent(versionId)}/rollback`,
    )
  }

  /** Compare two versions (diff). */
  async compare(contentId: string, params: { from?: string; to?: string } = {}): Promise<{ data: VersionDiff }> {
    return this.client.request<{ data: VersionDiff }>(
      'GET',
      `/v1/content/${encodeURIComponent(contentId)}/diff`,
      { params: params as Record<string, string | number | boolean | undefined> },
    )
  }

  /** Label a version. */
  async label(contentId: string, versionId: string, label: string): Promise<{ data: ContentVersion }> {
    return this.client.request<{ data: ContentVersion }>(
      'POST',
      `/v1/content/${encodeURIComponent(contentId)}/versions/${encodeURIComponent(versionId)}/label`,
      { body: { label } },
    )
  }

  /** Schedule a version for future publication. */
  async schedule(contentId: string, versionId: string, scheduledAt: string): Promise<{ data: ContentVersion }> {
    return this.client.request<{ data: ContentVersion }>(
      'POST',
      `/v1/content/${encodeURIComponent(contentId)}/versions/${encodeURIComponent(versionId)}/schedule`,
      { body: { scheduled_at: scheduledAt } },
    )
  }

  /** Cancel a scheduled version. */
  async cancelSchedule(contentId: string, versionId: string): Promise<void> {
    return this.client.request<void>(
      'DELETE',
      `/v1/content/${encodeURIComponent(contentId)}/versions/${encodeURIComponent(versionId)}/schedule`,
    )
  }

  /** Branch from a version (create a new draft based on an existing version). */
  async branch(contentId: string, versionId: string): Promise<{ data: ContentVersion }> {
    return this.client.request<{ data: ContentVersion }>(
      'POST',
      `/v1/content/${encodeURIComponent(contentId)}/versions/${encodeURIComponent(versionId)}/branch`,
    )
  }
}
