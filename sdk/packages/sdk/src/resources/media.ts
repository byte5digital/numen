/**
 * Media resource module.
 * Upload, list, get, delete, update metadata for Numen media assets.
 */

import type { NumenClient } from '../core/client.js'
import type { PaginatedResponse } from '../types/api.js'

export interface MediaAsset {
  id: string
  filename: string
  mime_type: string
  size: number
  url: string
  alt?: string
  title?: string
  folder_id?: string | null
  meta?: Record<string, unknown>
  created_at: string
  updated_at: string
  [key: string]: unknown
}

export interface MediaListParams {
  page?: number
  per_page?: number
  folder_id?: string
  mime_type?: string
  search?: string
}

export interface MediaUpdatePayload {
  alt?: string
  title?: string
  folder_id?: string | null
  meta?: Record<string, unknown>
  [key: string]: unknown
}

export class MediaResource {
  constructor(private readonly client: NumenClient) {}

  /** List media assets with optional filters. */
  async list(params: MediaListParams = {}): Promise<PaginatedResponse<MediaAsset>> {
    return this.client.request<PaginatedResponse<MediaAsset>>('GET', '/v1/media', {
      params: params as Record<string, string | number | boolean | undefined>,
    })
  }

  /** Get a single media asset by ID. */
  async get(id: string): Promise<{ data: MediaAsset }> {
    return this.client.request<{ data: MediaAsset }>('GET', `/v1/media/${encodeURIComponent(id)}`)
  }

  /**
   * Upload a media file.
   * Accepts a File/Blob (browser) or a ReadableStream-based body.
   * Uses multipart/form-data so we bypass the default JSON content-type.
   */
  async upload(file: Blob | File, metadata?: { title?: string; alt?: string; folder_id?: string }): Promise<{ data: MediaAsset }> {
    const formData = new FormData()
    formData.append('file', file)

    if (metadata?.title) formData.append('title', metadata.title)
    if (metadata?.alt) formData.append('alt', metadata.alt)
    if (metadata?.folder_id) formData.append('folder_id', metadata.folder_id)

    // We need to use a raw request to send FormData instead of JSON
    return this.client.request<{ data: MediaAsset }>('POST', '/v1/media', {
      body: formData,
      headers: {
        // Let the browser/runtime set the multipart boundary
        'Content-Type': 'multipart/form-data',
      },
    })
  }

  /** Update media asset metadata. */
  async update(id: string, data: MediaUpdatePayload): Promise<{ data: MediaAsset }> {
    return this.client.request<{ data: MediaAsset }>('PATCH', `/v1/media/${encodeURIComponent(id)}`, { body: data })
  }

  /** Delete a media asset. */
  async delete(id: string): Promise<void> {
    return this.client.request<void>('DELETE', `/v1/media/${encodeURIComponent(id)}`)
  }

  /** Move a media asset to a different folder. */
  async move(id: string, folderId: string): Promise<{ data: MediaAsset }> {
    return this.client.request<{ data: MediaAsset }>(
      'PATCH',
      `/v1/media/${encodeURIComponent(id)}/move`,
      { body: { folder_id: folderId } },
    )
  }

  /** Get usage information for a media asset (which content items reference it). */
  async usage(id: string): Promise<{ data: unknown[] }> {
    return this.client.request<{ data: unknown[] }>('GET', `/v1/media/${encodeURIComponent(id)}/usage`)
  }
}
