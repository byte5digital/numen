import { describe, it, expect, vi } from 'vitest'
import { NumenClient } from '../../src/core/client.js'

function createMockClient(responseData: unknown = {}, status = 200) {
  const mockFetch = vi.fn().mockResolvedValue(
    new Response(JSON.stringify(responseData), {
      status,
      headers: { 'Content-Type': 'application/json' },
    }),
  )
  return {
    client: new NumenClient({ baseUrl: 'https://api.test', fetch: mockFetch }),
    mockFetch,
  }
}

describe('ChatResource', () => {
  it('conversations() calls GET /v1/chat/conversations', async () => {
    const { client, mockFetch } = createMockClient({ data: [] })
    await client.chat.conversations()
    const url = new URL(mockFetch.mock.calls[0][0])
    expect(url.pathname).toBe('/v1/chat/conversations')
  })

  it('createConversation() calls POST /v1/chat/conversations', async () => {
    const { client, mockFetch } = createMockClient({ data: { id: 'conv1' } })
    await client.chat.createConversation({ title: 'Test' })
    expect(mockFetch.mock.calls[0][1].method).toBe('POST')
    const url = new URL(mockFetch.mock.calls[0][0])
    expect(url.pathname).toBe('/v1/chat/conversations')
  })

  it('deleteConversation() calls DELETE /v1/chat/conversations/:id', async () => {
    const mockFetch = vi.fn().mockResolvedValue(new Response(null, { status: 204 }))
    const client = new NumenClient({ baseUrl: 'https://api.test', fetch: mockFetch })
    await client.chat.deleteConversation('conv1')
    expect(mockFetch.mock.calls[0][1].method).toBe('DELETE')
    const url = new URL(mockFetch.mock.calls[0][0])
    expect(url.pathname).toBe('/v1/chat/conversations/conv1')
  })

  it('messages() calls GET /v1/chat/conversations/:id/messages', async () => {
    const { client, mockFetch } = createMockClient({ data: [] })
    await client.chat.messages('conv1')
    const url = new URL(mockFetch.mock.calls[0][0])
    expect(url.pathname).toBe('/v1/chat/conversations/conv1/messages')
  })

  it('sendMessage() calls POST /v1/chat/conversations/:id/messages', async () => {
    const { client, mockFetch } = createMockClient({ data: { id: 'm1' } })
    await client.chat.sendMessage('conv1', { content: 'hello' })
    expect(mockFetch.mock.calls[0][1].method).toBe('POST')
    const url = new URL(mockFetch.mock.calls[0][0])
    expect(url.pathname).toBe('/v1/chat/conversations/conv1/messages')
  })

  it('confirmAction() calls POST /v1/chat/conversations/:id/confirm', async () => {
    const { client, mockFetch } = createMockClient({ data: {} })
    await client.chat.confirmAction('conv1')
    expect(mockFetch.mock.calls[0][1].method).toBe('POST')
    const url = new URL(mockFetch.mock.calls[0][0])
    expect(url.pathname).toBe('/v1/chat/conversations/conv1/confirm')
  })

  it('cancelAction() calls DELETE /v1/chat/conversations/:id/confirm', async () => {
    const mockFetch = vi.fn().mockResolvedValue(new Response(null, { status: 204 }))
    const client = new NumenClient({ baseUrl: 'https://api.test', fetch: mockFetch })
    await client.chat.cancelAction('conv1')
    expect(mockFetch.mock.calls[0][1].method).toBe('DELETE')
    const url = new URL(mockFetch.mock.calls[0][0])
    expect(url.pathname).toBe('/v1/chat/conversations/conv1/confirm')
  })

  it('suggestions() calls GET /v1/chat/suggestions', async () => {
    const { client, mockFetch } = createMockClient({ data: [] })
    await client.chat.suggestions()
    const url = new URL(mockFetch.mock.calls[0][0])
    expect(url.pathname).toBe('/v1/chat/suggestions')
  })
})
