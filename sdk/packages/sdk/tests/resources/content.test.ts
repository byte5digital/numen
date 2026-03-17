import { describe, it, expect, vi, beforeEach } from 'vitest'
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

describe('ContentResource', () => {
  it('list() calls GET /v1/content', async () => {
    const data = { data: [], meta: { total: 0, page: 1, perPage: 10, lastPage: 1 } }
    const { client, mockFetch } = createMockClient(data)

    const result = await client.content.list()
    expect(result).toEqual(data)
    expect(mockFetch).toHaveBeenCalledOnce()
    const url = new URL(mockFetch.mock.calls[0][0])
    expect(url.pathname).toBe('/v1/content')
  })

  it('list() passes query params', async () => {
    const data = { data: [], meta: { total: 0, page: 1, perPage: 10, lastPage: 1 } }
    const { client, mockFetch } = createMockClient(data)

    await client.content.list({ type: 'article', page: 2 })
    const url = new URL(mockFetch.mock.calls[0][0])
    expect(url.searchParams.get('type')).toBe('article')
    expect(url.searchParams.get('page')).toBe('2')
  })

  it('get() calls GET /v1/content/:slug', async () => {
    const data = { data: { id: '1', slug: 'hello' } }
    const { client, mockFetch } = createMockClient(data)

    const result = await client.content.get('hello')
    expect(result).toEqual(data)
    const url = new URL(mockFetch.mock.calls[0][0])
    expect(url.pathname).toBe('/v1/content/hello')
  })

  it('byType() calls GET /v1/content/type/:type', async () => {
    const data = { data: [], meta: { total: 0, page: 1, perPage: 10, lastPage: 1 } }
    const { client, mockFetch } = createMockClient(data)

    await client.content.byType('article')
    const url = new URL(mockFetch.mock.calls[0][0])
    expect(url.pathname).toBe('/v1/content/type/article')
  })

  it('create() calls POST /v1/content with body', async () => {
    const data = { data: { id: '1', title: 'New' } }
    const { client, mockFetch } = createMockClient(data)

    await client.content.create({ title: 'New', type: 'article' })
    expect(mockFetch.mock.calls[0][1].method).toBe('POST')
    const url = new URL(mockFetch.mock.calls[0][0])
    expect(url.pathname).toBe('/v1/content')
    const body = JSON.parse(mockFetch.mock.calls[0][1].body)
    expect(body.title).toBe('New')
  })

  it('update() calls PUT /v1/content/:id', async () => {
    const { client, mockFetch } = createMockClient({ data: {} })

    await client.content.update('abc', { title: 'Updated' })
    expect(mockFetch.mock.calls[0][1].method).toBe('PUT')
    const url = new URL(mockFetch.mock.calls[0][0])
    expect(url.pathname).toBe('/v1/content/abc')
  })

  it('delete() calls DELETE /v1/content/:id', async () => {
    const mockFetch = vi.fn().mockResolvedValue(new Response(null, { status: 204 }))
    const client = new NumenClient({ baseUrl: 'https://api.test', fetch: mockFetch })

    await client.content.delete('abc')
    expect(mockFetch.mock.calls[0][1].method).toBe('DELETE')
    const url = new URL(mockFetch.mock.calls[0][0])
    expect(url.pathname).toBe('/v1/content/abc')
  })

  it('publish() calls POST /v1/content/:id/versions/:vid/publish', async () => {
    const { client, mockFetch } = createMockClient({ data: {} })

    await client.content.publish('c1', 'v1')
    expect(mockFetch.mock.calls[0][1].method).toBe('POST')
    const url = new URL(mockFetch.mock.calls[0][0])
    expect(url.pathname).toBe('/v1/content/c1/versions/v1/publish')
  })

  it('unpublish() calls POST /v1/content/:id/versions/draft', async () => {
    const { client, mockFetch } = createMockClient({ data: {} })

    await client.content.unpublish('c1')
    const url = new URL(mockFetch.mock.calls[0][0])
    expect(url.pathname).toBe('/v1/content/c1/versions/draft')
  })
})
