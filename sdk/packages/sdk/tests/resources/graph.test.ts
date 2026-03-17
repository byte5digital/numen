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

describe('GraphResource', () => {
  it('related() calls GET /v1/graph/related/:contentId', async () => {
    const { client, mockFetch } = createMockClient({ data: [] })
    await client.graph.related('c1')
    const url = new URL(mockFetch.mock.calls[0][0])
    expect(url.pathname).toBe('/v1/graph/related/c1')
  })

  it('clusters() calls GET /v1/graph/clusters', async () => {
    const { client, mockFetch } = createMockClient({ data: [] })
    await client.graph.clusters()
    const url = new URL(mockFetch.mock.calls[0][0])
    expect(url.pathname).toBe('/v1/graph/clusters')
  })

  it('node() calls GET /v1/graph/node/:contentId', async () => {
    const { client, mockFetch } = createMockClient({ data: { id: 'n1' } })
    await client.graph.node('c1')
    const url = new URL(mockFetch.mock.calls[0][0])
    expect(url.pathname).toBe('/v1/graph/node/c1')
  })

  it('gaps() calls GET /v1/graph/gaps', async () => {
    const { client, mockFetch } = createMockClient({ data: [] })
    await client.graph.gaps()
    const url = new URL(mockFetch.mock.calls[0][0])
    expect(url.pathname).toBe('/v1/graph/gaps')
  })

  it('path() calls GET /v1/graph/path/:from/:to', async () => {
    const { client, mockFetch } = createMockClient({ data: {} })
    await client.graph.path('a', 'b')
    const url = new URL(mockFetch.mock.calls[0][0])
    expect(url.pathname).toBe('/v1/graph/path/a/b')
  })

  it('reindex() calls POST /v1/graph/reindex/:contentId', async () => {
    const { client, mockFetch } = createMockClient({ data: {} })
    await client.graph.reindex('c1')
    expect(mockFetch.mock.calls[0][1].method).toBe('POST')
    const url = new URL(mockFetch.mock.calls[0][0])
    expect(url.pathname).toBe('/v1/graph/reindex/c1')
  })
})

describe('GraphResource - Edge Cases', () => {
  describe('related() with various content IDs', () => {
    it('handles related content with empty response', async () => {
      const { client, mockFetch } = createMockClient({ data: [] })
      const result = await client.graph.related('c1')
      expect(result.data).toEqual([])
    })

    it('handles related content with multiple results', async () => {
      const data = {
        data: [
          { id: 'c2', similarity: 0.95 },
          { id: 'c3', similarity: 0.87 }
        ]
      }
      const { client, mockFetch } = createMockClient(data)
      const result = await client.graph.related('c1')
      expect(result.data).toHaveLength(2)
    })

    it('encodes special characters in content ID', async () => {
      const { client, mockFetch } = createMockClient({ data: [] })
      await client.graph.related('c-1/special')
      const url = new URL(mockFetch.mock.calls[0][0])
      expect(url.pathname).toContain('c-1%2Fspecial')
    })
  })

  describe('clusters() handling', () => {
    it('handles clusters with empty result', async () => {
      const data = { data: [] }
      const { client, mockFetch } = createMockClient(data)
      const result = await client.graph.clusters()
      expect(result.data).toEqual([])
    })

    it('handles clusters with multiple items', async () => {
      const data = {
        data: [
          { id: 'cl1', nodes: ['c1', 'c2', 'c3'] },
          { id: 'cl2', nodes: ['c4', 'c5'] }
        ]
      }
      const { client, mockFetch } = createMockClient(data)
      const result = await client.graph.clusters()
      expect(result.data).toHaveLength(2)
    })
  })

  describe('node() with various IDs', () => {
    it('returns node metadata', async () => {
      const data = {
        data: { id: 'n1', content_id: 'c1', incoming_edges: 5, outgoing_edges: 3 }
      }
      const { client, mockFetch } = createMockClient(data)
      const result = await client.graph.node('c1')
      expect((result.data as any).incoming_edges).toBe(5)
    })
  })

  describe('gaps() analysis', () => {
    it('handles gaps with empty result', async () => {
      const data = { data: [] }
      const { client, mockFetch } = createMockClient(data)
      const result = await client.graph.gaps()
      expect(result.data).toEqual([])
    })

    it('handles gaps with multiple entries', async () => {
      const data = {
        data: [
          { topic: 'Topic A', gap_score: 0.8 },
          { topic: 'Topic B', gap_score: 0.6 }
        ]
      }
      const { client, mockFetch } = createMockClient(data)
      const result = await client.graph.gaps()
      expect(result.data).toHaveLength(2)
    })
  })

  describe('path() between nodes', () => {
    it('returns path data', async () => {
      const data = {
        data: { path: ['c1', 'c2', 'c3'], distance: 2, strength: 0.85 }
      }
      const { client, mockFetch } = createMockClient(data)
      const result = await client.graph.path('c1', 'c3')
      expect((result.data as any).path).toHaveLength(3)
    })

    it('encodes special characters in both IDs', async () => {
      const { client, mockFetch } = createMockClient({ data: {} })
      await client.graph.path('c-1/start', 'c-2/end')
      const url = new URL(mockFetch.mock.calls[0][0])
      expect(url.pathname).toContain('c-1%2Fstart')
    })
  })

  describe('reindex() operation', () => {
    it('triggers reindex for content', async () => {
      const { client, mockFetch } = createMockClient({ data: { status: 'queued' } })
      const result = await client.graph.reindex('c1')
      expect((result.data as any).status).toBe('queued')
      expect(mockFetch.mock.calls[0][1].method).toBe('POST')
    })
  })
})
