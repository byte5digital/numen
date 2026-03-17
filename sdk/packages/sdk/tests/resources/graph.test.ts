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
