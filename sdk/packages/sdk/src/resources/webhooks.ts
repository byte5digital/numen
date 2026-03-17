/**
 * Webhooks resource module.
 * CRUD webhooks, rotate secret, deliveries.
 */

import type { NumenClient, RequestOptions } from '../core/client.js'
import type { PaginatedResponse } from '../types/api.js'

export interface Webhook {
  id: string
  url: string
  events: string[]
  secret?: string
  active: boolean
  created_at: string
  updated_at: string
  [key: string]: unknown
}

export interface WebhookDelivery {
  id: string
  webhook_id: string
  event: string
  status: number
  response_body?: string
  delivered_at: string
  [key: string]: unknown
}

export interface WebhookListParams {
  page?: number
  per_page?: number
}

export interface WebhookCreatePayload {
  url: string
  events: string[]
  secret?: string
  [key: string]: unknown
}

export interface WebhookUpdatePayload {
  url?: string
  events?: string[]
  active?: boolean
  [key: string]: unknown
}

export class WebhooksResource {
  constructor(private readonly client: NumenClient) {}

  /** List webhooks. */
  async list(params: WebhookListParams = {}): Promise<PaginatedResponse<Webhook>> {
    return this.client.request<PaginatedResponse<Webhook>>('GET', '/v1/webhooks', {
      params: params as Record<string, string | number | boolean | undefined>,
    })
  }

  /** Get a webhook by ID. */
  async get(id: string): Promise<{ data: Webhook }> {
    return this.client.request<{ data: Webhook }>('GET', `/v1/webhooks/${encodeURIComponent(id)}`)
  }

  /** Create a webhook. */
  async create(data: WebhookCreatePayload): Promise<{ data: Webhook }> {
    return this.client.request<{ data: Webhook }>('POST', '/v1/webhooks', { body: data })
  }

  /** Update a webhook. */
  async update(id: string, data: WebhookUpdatePayload): Promise<{ data: Webhook }> {
    return this.client.request<{ data: Webhook }>('PUT', `/v1/webhooks/${encodeURIComponent(id)}`, { body: data })
  }

  /** Delete a webhook. */
  async delete(id: string): Promise<void> {
    return this.client.request<void>('DELETE', `/v1/webhooks/${encodeURIComponent(id)}`)
  }

  /** Rotate webhook secret. */
  async rotateSecret(id: string): Promise<{ data: Webhook }> {
    return this.client.request<{ data: Webhook }>(
      'POST',
      `/v1/webhooks/${encodeURIComponent(id)}/rotate-secret`,
    )
  }

  /** List deliveries for a webhook. */
  async deliveries(id: string, params: WebhookListParams = {}): Promise<PaginatedResponse<WebhookDelivery>> {
    return this.client.request<PaginatedResponse<WebhookDelivery>>(
      'GET',
      `/v1/webhooks/${encodeURIComponent(id)}/deliveries`,
      { params: params as Record<string, string | number | boolean | undefined> },
    )
  }

  /** Get a specific delivery. */
  async getDelivery(webhookId: string, deliveryId: string): Promise<{ data: WebhookDelivery }> {
    return this.client.request<{ data: WebhookDelivery }>(
      'GET',
      `/v1/webhooks/${encodeURIComponent(webhookId)}/deliveries/${encodeURIComponent(deliveryId)}`,
    )
  }

  /** Redeliver a webhook delivery. */
  async redeliver(webhookId: string, deliveryId: string): Promise<{ data: WebhookDelivery }> {
    return this.client.request<{ data: WebhookDelivery }>(
      'POST',
      `/v1/webhooks/${encodeURIComponent(webhookId)}/deliveries/${encodeURIComponent(deliveryId)}/redeliver`,
    )
  }
}
