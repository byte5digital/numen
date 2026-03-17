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

describe('WebhooksResource', () => {
  it('list() calls GET /v1/webhooks', async () => {
    const data = { data: [], meta: { total: 0, page: 1, perPage: 10, lastPage: 1 } }
    const { client, mockFetch } = createMockClient(data)
    await client.webhooks.list()
    const url = new URL(mockFetch.mock.calls[0][0])
    expect(url.pathname).toBe('/v1/webhooks')
  })

  it('get() calls GET /v1/webhooks/:id', async () => {
    const { client, mockFetch } = createMockClient({ data: { id: 'w1' } })
    await client.webhooks.get('w1')
    const url = new URL(mockFetch.mock.calls[0][0])
    expect(url.pathname).toBe('/v1/webhooks/w1')
  })

  it('create() calls POST /v1/webhooks', async () => {
    const { client, mockFetch } = createMockClient({ data: { id: 'w1' } })
    await client.webhooks.create({ url: 'https://hook.test', events: ['content.created'] })
    expect(mockFetch.mock.calls[0][1].method).toBe('POST')
    const url = new URL(mockFetch.mock.calls[0][0])
    expect(url.pathname).toBe('/v1/webhooks')
  })

  it('update() calls PUT /v1/webhooks/:id', async () => {
    const { client, mockFetch } = createMockClient({ data: { id: 'w1' } })
    await client.webhooks.update('w1', { url: 'https://new.test' })
    expect(mockFetch.mock.calls[0][1].method).toBe('PUT')
    const url = new URL(mockFetch.mock.calls[0][0])
    expect(url.pathname).toBe('/v1/webhooks/w1')
  })

  it('delete() calls DELETE /v1/webhooks/:id', async () => {
    const mockFetch = vi.fn().mockResolvedValue(new Response(null, { status: 204 }))
    const client = new NumenClient({ baseUrl: 'https://api.test', fetch: mockFetch })
    await client.webhooks.delete('w1')
    expect(mockFetch.mock.calls[0][1].method).toBe('DELETE')
  })

  it('rotateSecret() calls POST /v1/webhooks/:id/rotate-secret', async () => {
    const { client, mockFetch } = createMockClient({ data: { id: 'w1' } })
    await client.webhooks.rotateSecret('w1')
    expect(mockFetch.mock.calls[0][1].method).toBe('POST')
    const url = new URL(mockFetch.mock.calls[0][0])
    expect(url.pathname).toBe('/v1/webhooks/w1/rotate-secret')
  })

  it('deliveries() calls GET /v1/webhooks/:id/deliveries', async () => {
    const data = { data: [], meta: { total: 0, page: 1, perPage: 10, lastPage: 1 } }
    const { client, mockFetch } = createMockClient(data)
    await client.webhooks.deliveries('w1')
    const url = new URL(mockFetch.mock.calls[0][0])
    expect(url.pathname).toBe('/v1/webhooks/w1/deliveries')
  })

  it('redeliver() calls POST /v1/webhooks/:id/deliveries/:did/redeliver', async () => {
    const { client, mockFetch } = createMockClient({ data: {} })
    await client.webhooks.redeliver('w1', 'd1')
    expect(mockFetch.mock.calls[0][1].method).toBe('POST')
    const url = new URL(mockFetch.mock.calls[0][0])
    expect(url.pathname).toBe('/v1/webhooks/w1/deliveries/d1/redeliver')
  })
})
