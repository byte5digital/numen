/**
 * Chat resource module.
 * Conversations CRUD, send message, confirm/cancel action, suggestions.
 */

import type { NumenClient, RequestOptions } from '../core/client.js'

export interface Conversation {
  id: string
  title?: string
  created_at: string
  updated_at: string
  [key: string]: unknown
}

export interface ChatMessage {
  id: string
  conversation_id: string
  role: 'user' | 'assistant' | 'system'
  content: string
  action?: unknown
  created_at: string
  [key: string]: unknown
}

export interface SendMessagePayload {
  content: string
  [key: string]: unknown
}

export interface CreateConversationPayload {
  title?: string
  [key: string]: unknown
}

export class ChatResource {
  constructor(private readonly client: NumenClient) {}

  /** List conversations. */
  async conversations(): Promise<{ data: Conversation[] }> {
    return this.client.request<{ data: Conversation[] }>('GET', '/v1/chat/conversations')
  }

  /** Create a conversation. */
  async createConversation(data: CreateConversationPayload = {}): Promise<{ data: Conversation }> {
    return this.client.request<{ data: Conversation }>('POST', '/v1/chat/conversations', { body: data })
  }

  /** Delete a conversation. */
  async deleteConversation(id: string): Promise<void> {
    return this.client.request<void>('DELETE', `/v1/chat/conversations/${encodeURIComponent(id)}`)
  }

  /** List messages in a conversation. */
  async messages(conversationId: string): Promise<{ data: ChatMessage[] }> {
    return this.client.request<{ data: ChatMessage[] }>(
      'GET',
      `/v1/chat/conversations/${encodeURIComponent(conversationId)}/messages`,
    )
  }

  /** Send a message to a conversation. */
  async sendMessage(conversationId: string, data: SendMessagePayload): Promise<{ data: ChatMessage }> {
    return this.client.request<{ data: ChatMessage }>(
      'POST',
      `/v1/chat/conversations/${encodeURIComponent(conversationId)}/messages`,
      { body: data },
    )
  }

  /** Confirm a pending action in a conversation. */
  async confirmAction(conversationId: string): Promise<{ data: unknown }> {
    return this.client.request<{ data: unknown }>(
      'POST',
      `/v1/chat/conversations/${encodeURIComponent(conversationId)}/confirm`,
    )
  }

  /** Cancel a pending action in a conversation. */
  async cancelAction(conversationId: string): Promise<void> {
    return this.client.request<void>(
      'DELETE',
      `/v1/chat/conversations/${encodeURIComponent(conversationId)}/confirm`,
    )
  }

  /** Get AI suggestions. */
  async suggestions(): Promise<{ data: unknown[] }> {
    return this.client.request<{ data: unknown[] }>('GET', '/v1/chat/suggestions')
  }
}
