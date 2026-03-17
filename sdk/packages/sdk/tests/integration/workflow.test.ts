import { describe, it, expect, vi } from 'vitest'
import { NumenClient } from '../../src/core/client.js'

describe('SDK Integration - Complete Workflows', () => {
  it('creates content and retrieves it', async () => {
    const mockFetch = vi.fn()
      .mockResolvedValueOnce(
        new Response(JSON.stringify({ data: { id: 'c1', title: 'New Content', type: 'article' } }), {
          status: 201,
          headers: { 'Content-Type': 'application/json' },
        }),
      )
      .mockResolvedValueOnce(
        new Response(JSON.stringify({ data: { id: 'c1', title: 'New Content', type: 'article' } }), {
          status: 200,
          headers: { 'Content-Type': 'application/json' },
        }),
      )

    const client = new NumenClient({ baseUrl: 'https://api.test', fetch: mockFetch })

    const createResult = await client.content.create({ title: 'New Content', type: 'article' })
    expect(createResult.data.id).toBe('c1')

    const getResult = await client.content.get('c1')
    expect(getResult.data.title).toBe('New Content')
  })

  it('lists content with pagination', async () => {
    const page1 = {
      data: [{ id: 'c1', type: 'article' }, { id: 'c2', type: 'blog' }],
      meta: { total: 25, page: 1, perPage: 2, lastPage: 13 },
    }

    const mockFetch = vi.fn()
      .mockResolvedValueOnce(
        new Response(JSON.stringify(page1), {
          status: 200,
          headers: { 'Content-Type': 'application/json' },
        }),
      )

    const client = new NumenClient({ baseUrl: 'https://api.test', fetch: mockFetch })

    const result = await client.content.list({ page: 1, per_page: 2 })
    expect(result.data).toHaveLength(2)
    expect(result.meta.lastPage).toBe(13)
  })

  it('finds related content and analyzes path', async () => {
    const mockFetch = vi.fn()
      .mockResolvedValueOnce(
        new Response(JSON.stringify({ data: [{ id: 'c2', similarity: 0.92 }] }), {
          status: 200,
          headers: { 'Content-Type': 'application/json' },
        }),
      )
      .mockResolvedValueOnce(
        new Response(JSON.stringify({ data: { path: ['c1', 'c3', 'c2'], distance: 2 } }), {
          status: 200,
          headers: { 'Content-Type': 'application/json' },
        }),
      )

    const client = new NumenClient({ baseUrl: 'https://api.test', fetch: mockFetch })

    const relatedResult = await client.graph.related('c1')
    expect(relatedResult.data).toHaveLength(1)

    const pathResult = await client.graph.path('c1', 'c2')
    expect((pathResult.data as any).distance).toBe(2)
  })

  it('creates page hierarchy and reorders', async () => {
    const mockFetch = vi.fn()
      .mockResolvedValueOnce(
        new Response(JSON.stringify({ data: { id: 'p1', title: 'Parent' } }), {
          status: 201,
          headers: { 'Content-Type': 'application/json' },
        }),
      )
      .mockResolvedValueOnce(
        new Response(JSON.stringify({ data: { id: 'p2', parent_id: 'p1' } }), {
          status: 201,
          headers: { 'Content-Type': 'application/json' },
        }),
      )
      .mockResolvedValueOnce(
        new Response(null, { status: 204 }),
      )

    const client = new NumenClient({ baseUrl: 'https://api.test', fetch: mockFetch })

    const parentResult = await client.pages.create({ title: 'Parent' })
    expect(parentResult.data.id).toBe('p1')

    const childResult = await client.pages.create({
      title: 'Child',
      parent_id: parentResult.data.id,
    })
    expect(childResult.data.parent_id).toBe('p1')

    await client.pages.reorder({ order: ['p2', 'p1'] })
    expect(mockFetch).toHaveBeenCalledTimes(3)
  })

  it('executes parallel resource calls', async () => {
    const mockFetch = vi.fn()
      .mockResolvedValueOnce(
        new Response(JSON.stringify({ data: { id: 'c1' } }), {
          status: 200,
          headers: { 'Content-Type': 'application/json' },
        }),
      )
      .mockResolvedValueOnce(
        new Response(JSON.stringify({ data: { id: 'p1' } }), {
          status: 200,
          headers: { 'Content-Type': 'application/json' },
        }),
      )
      .mockResolvedValueOnce(
        new Response(JSON.stringify({ data: { id: 'w1' } }), {
          status: 200,
          headers: { 'Content-Type': 'application/json' },
        }),
      )

    const client = new NumenClient({ baseUrl: 'https://api.test', fetch: mockFetch })

    const [content, page, webhook] = await Promise.all([
      client.content.get('c1'),
      client.pages.get('p1'),
      client.webhooks.get('w1'),
    ])

    expect(content.data.id).toBe('c1')
    expect(page.data.id).toBe('p1')
    expect(webhook.data.id).toBe('w1')
  })

  it('searches and analyzes competitors', async () => {
    const mockFetch = vi.fn()
      .mockResolvedValueOnce(
        new Response(JSON.stringify({ data: [{ id: 'c1', relevance: 0.95 }] }), {
          status: 200,
          headers: { 'Content-Type': 'application/json' },
        }),
      )
      .mockResolvedValueOnce(
        new Response(JSON.stringify({ data: [{ id: 'd1', score: 85 }] }), {
          status: 200,
          headers: { 'Content-Type': 'application/json' },
        }),
      )

    const client = new NumenClient({ baseUrl: 'https://api.test', fetch: mockFetch })

    const searchResult = await client.search.search({ q: 'test' })
    expect(searchResult.data).toHaveLength(1)

    const diffResult = await client.competitor.differentiation()
    expect(diffResult.data).toHaveLength(1)
  })

  it('quality checks and analyzes trends', async () => {
    const mockFetch = vi.fn()
      .mockResolvedValueOnce(
        new Response(JSON.stringify({ data: { id: 'qs1', score: 85 } }), {
          status: 200,
          headers: { 'Content-Type': 'application/json' },
        }),
      )
      .mockResolvedValueOnce(
        new Response(JSON.stringify({ data: { daily: [] } }), {
          status: 200,
          headers: { 'Content-Type': 'application/json' },
        }),
      )

    const client = new NumenClient({ baseUrl: 'https://api.test', fetch: mockFetch })

    const scoreResult = await client.quality.score({ content_id: 'c1' })
    expect((scoreResult.data as any).score).toBe(85)

    const trendsResult = await client.quality.trends()
    expect((trendsResult.data as any).daily).toBeDefined()
  })
})
