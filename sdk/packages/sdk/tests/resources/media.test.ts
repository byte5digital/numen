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

describe('MediaResource', () => {
  it('list() calls GET /v1/media', async () => {
    const data = { data: [], meta: { total: 0, page: 1, perPage: 10, lastPage: 1 } }
    const { client, mockFetch } = createMockClient(data)

    const result = await client.media.list()
    expect(result).toEqual(data)
    const url = new URL(mockFetch.mock.calls[0][0])
    expect(url.pathname).toBe('/v1/media')
  })

  it('get() calls GET /v1/media/:id', async () => {
    const { client, mockFetch } = createMockClient({ data: { id: 'a1' } })

    await client.media.get('a1')
    const url = new URL(mockFetch.mock.calls[0][0])
    expect(url.pathname).toBe('/v1/media/a1')
  })

  it('update() calls PATCH /v1/media/:id', async () => {
    const { client, mockFetch } = createMockClient({ data: {} })

    await client.media.update('a1', { alt: 'photo' })
    expect(mockFetch.mock.calls[0][1].method).toBe('PATCH')
    const url = new URL(mockFetch.mock.calls[0][0])
    expect(url.pathname).toBe('/v1/media/a1')
  })

  it('delete() calls DELETE /v1/media/:id', async () => {
    const mockFetch = vi.fn().mockResolvedValue(new Response(null, { status: 204 }))
    const client = new NumenClient({ baseUrl: 'https://api.test', fetch: mockFetch })

    await client.media.delete('a1')
    expect(mockFetch.mock.calls[0][1].method).toBe('DELETE')
  })

  it('move() calls PATCH /v1/media/:id/move', async () => {
    const { client, mockFetch } = createMockClient({ data: {} })

    await client.media.move('a1', 'folder-2')
    expect(mockFetch.mock.calls[0][1].method).toBe('PATCH')
    const url = new URL(mockFetch.mock.calls[0][0])
    expect(url.pathname).toBe('/v1/media/a1/move')
  })

  it('usage() calls GET /v1/media/:id/usage', async () => {
    const { client, mockFetch } = createMockClient({ data: [] })

    await client.media.usage('a1')
    const url = new URL(mockFetch.mock.calls[0][0])
    expect(url.pathname).toBe('/v1/media/a1/usage')
  })
})
