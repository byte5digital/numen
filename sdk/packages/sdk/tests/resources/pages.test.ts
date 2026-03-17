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

describe('PagesResource', () => {
  it('list() calls GET /v1/pages', async () => {
    const data = { data: [], meta: { total: 0, page: 1, perPage: 10, lastPage: 1 } }
    const { client, mockFetch } = createMockClient(data)

    const result = await client.pages.list()
    expect(result).toEqual(data)
    const url = new URL(mockFetch.mock.calls[0][0])
    expect(url.pathname).toBe('/v1/pages')
  })

  it('get() calls GET /v1/pages/:slug', async () => {
    const { client, mockFetch } = createMockClient({ data: { id: '1' } })

    await client.pages.get('about')
    const url = new URL(mockFetch.mock.calls[0][0])
    expect(url.pathname).toBe('/v1/pages/about')
  })

  it('create() calls POST /v1/pages', async () => {
    const { client, mockFetch } = createMockClient({ data: {} })

    await client.pages.create({ title: 'About Us' })
    expect(mockFetch.mock.calls[0][1].method).toBe('POST')
    const url = new URL(mockFetch.mock.calls[0][0])
    expect(url.pathname).toBe('/v1/pages')
  })

  it('update() calls PUT /v1/pages/:id', async () => {
    const { client, mockFetch } = createMockClient({ data: {} })

    await client.pages.update('p1', { title: 'Updated' })
    expect(mockFetch.mock.calls[0][1].method).toBe('PUT')
    const url = new URL(mockFetch.mock.calls[0][0])
    expect(url.pathname).toBe('/v1/pages/p1')
  })

  it('delete() calls DELETE /v1/pages/:id', async () => {
    const mockFetch = vi.fn().mockResolvedValue(new Response(null, { status: 204 }))
    const client = new NumenClient({ baseUrl: 'https://api.test', fetch: mockFetch })

    await client.pages.delete('p1')
    expect(mockFetch.mock.calls[0][1].method).toBe('DELETE')
  })

  it('children() passes parent_id param', async () => {
    const data = { data: [], meta: { total: 0, page: 1, perPage: 10, lastPage: 1 } }
    const { client, mockFetch } = createMockClient(data)

    await client.pages.children('parent-1')
    const url = new URL(mockFetch.mock.calls[0][0])
    expect(url.searchParams.get('parent_id')).toBe('parent-1')
  })

  it('reorder() calls POST /v1/pages/reorder', async () => {
    const mockFetch = vi.fn().mockResolvedValue(new Response(null, { status: 204 }))
    const client = new NumenClient({ baseUrl: 'https://api.test', fetch: mockFetch })

    await client.pages.reorder({ order: ['a', 'b', 'c'] })
    expect(mockFetch.mock.calls[0][1].method).toBe('POST')
    const url = new URL(mockFetch.mock.calls[0][0])
    expect(url.pathname).toBe('/v1/pages/reorder')
  })
})
