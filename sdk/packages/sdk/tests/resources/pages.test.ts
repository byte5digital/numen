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

describe('PagesResource - Edge Cases', () => {
  describe('list() with various filters', () => {
    it('passes multiple filter parameters', async () => {
      const data = { data: [], meta: { total: 0, page: 1, perPage: 10, lastPage: 1 } }
      const { client, mockFetch } = createMockClient(data)
      await client.pages.list({ page: 1, per_page: 20, parent_id: 'p1', status: 'published', search: 'query' })
      const url = new URL(mockFetch.mock.calls[0][0])
      expect(url.searchParams.get('page')).toBe('1')
      expect(url.searchParams.get('per_page')).toBe('20')
      expect(url.searchParams.get('parent_id')).toBe('p1')
      expect(url.searchParams.get('status')).toBe('published')
      expect(url.searchParams.get('search')).toBe('query')
    })

    it('handles undefined filter parameters', async () => {
      const data = { data: [], meta: { total: 0, page: 1, perPage: 10, lastPage: 1 } }
      const { client, mockFetch } = createMockClient(data)
      await client.pages.list({ parent_id: undefined, search: undefined })
      const url = new URL(mockFetch.mock.calls[0][0])
      expect(url.searchParams.get('parent_id')).toBeNull()
      expect(url.searchParams.get('search')).toBeNull()
    })

    it('handles empty search query', async () => {
      const data = { data: [], meta: { total: 0, page: 1, perPage: 10, lastPage: 1 } }
      const { client, mockFetch } = createMockClient(data)
      await client.pages.list({ search: '' })
      const url = new URL(mockFetch.mock.calls[0][0])
      expect(url.searchParams.get('search')).toBe('')
    })
  })

  describe('get() with special characters', () => {
    it('encodes slug with special characters', async () => {
      const { client, mockFetch } = createMockClient({ data: { id: '1' } })
      await client.pages.get('about-us/page#1')
      const url = new URL(mockFetch.mock.calls[0][0])
      expect(url.pathname).toContain('about-us%2Fpage%231')
    })

    it('handles empty slug', async () => {
      const { client, mockFetch } = createMockClient({ data: { id: '1' } })
      await client.pages.get('')
      const url = new URL(mockFetch.mock.calls[0][0])
      expect(url.pathname).toContain('/v1/pages/')
    })

    it('handles slug with spaces', async () => {
      const { client, mockFetch } = createMockClient({ data: { id: '1' } })
      await client.pages.get('my page')
      const url = new URL(mockFetch.mock.calls[0][0])
      expect(url.pathname).toContain('my%20page')
    })
  })

  describe('create() with nested body', () => {
    it('handles complex nested body structure', async () => {
      const { client, mockFetch } = createMockClient({ data: { id: 'p1' } })
      const payload = {
        title: 'Test',
        body: {
          sections: [
            { type: 'text', content: 'Hello' },
            { type: 'image', url: 'https://example.com/img.jpg' }
          ]
        }
      }
      await client.pages.create(payload)
      const body = JSON.parse(mockFetch.mock.calls[0][1].body)
      expect(body.body.sections).toHaveLength(2)
      expect(body.body.sections[1].type).toBe('image')
    })

    it('handles null parent_id in create', async () => {
      const { client, mockFetch } = createMockClient({ data: { id: 'p1' } })
      await client.pages.create({ title: 'Root', parent_id: null })
      const body = JSON.parse(mockFetch.mock.calls[0][1].body)
      expect(body.parent_id).toBeNull()
    })

    it('handles custom meta fields', async () => {
      const { client, mockFetch } = createMockClient({ data: { id: 'p1' } })
      const meta = { seo_title: 'Custom Title', keywords: ['a', 'b'] }
      await client.pages.create({ title: 'Test', meta })
      const body = JSON.parse(mockFetch.mock.calls[0][1].body)
      expect(body.meta.seo_title).toBe('Custom Title')
      expect(body.meta.keywords).toHaveLength(2)
    })
  })

  describe('update() edge cases', () => {
    it('handles partial update with only title', async () => {
      const { client, mockFetch } = createMockClient({ data: { id: 'p1' } })
      await client.pages.update('p1', { title: 'New Title' })
      const body = JSON.parse(mockFetch.mock.calls[0][1].body)
      expect(body.title).toBe('New Title')
      expect(Object.keys(body)).toContain('title')
    })

    it('handles update with zero order value', async () => {
      const { client, mockFetch } = createMockClient({ data: { id: 'p1' } })
      await client.pages.update('p1', { order: 0 })
      const body = JSON.parse(mockFetch.mock.calls[0][1].body)
      expect(body.order).toBe(0)
    })
  })

  describe('reorder() edge cases', () => {
    it('handles reorder with empty array', async () => {
      const mockFetch = vi.fn().mockResolvedValue(new Response(null, { status: 204 }))
      const client = new NumenClient({ baseUrl: 'https://api.test', fetch: mockFetch })
      await client.pages.reorder({ order: [] })
      const body = JSON.parse(mockFetch.mock.calls[0][1].body)
      expect(body.order).toEqual([])
    })

    it('handles reorder with single item', async () => {
      const mockFetch = vi.fn().mockResolvedValue(new Response(null, { status: 204 }))
      const client = new NumenClient({ baseUrl: 'https://api.test', fetch: mockFetch })
      await client.pages.reorder({ order: ['p1'] })
      const body = JSON.parse(mockFetch.mock.calls[0][1].body)
      expect(body.order).toEqual(['p1'])
    })

    it('handles reorder with many items', async () => {
      const mockFetch = vi.fn().mockResolvedValue(new Response(null, { status: 204 }))
      const client = new NumenClient({ baseUrl: 'https://api.test', fetch: mockFetch })
      const ids = Array.from({ length: 100 }, (_, i) => `p${i}`)
      await client.pages.reorder({ order: ids })
      const body = JSON.parse(mockFetch.mock.calls[0][1].body)
      expect(body.order).toHaveLength(100)
    })
  })
})
