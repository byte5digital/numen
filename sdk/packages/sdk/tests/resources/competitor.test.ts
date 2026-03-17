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

describe('CompetitorResource', () => {
  it('sources() calls GET /v1/competitor/sources', async () => {
    const data = { data: [], meta: { total: 0, page: 1, perPage: 10, lastPage: 1 } }
    const { client, mockFetch } = createMockClient(data)
    await client.competitor.sources()
    const url = new URL(mockFetch.mock.calls[0][0])
    expect(url.pathname).toBe('/v1/competitor/sources')
  })

  it('getSource() calls GET /v1/competitor/sources/:id', async () => {
    const { client, mockFetch } = createMockClient({ data: { id: 's1' } })
    await client.competitor.getSource('s1')
    const url = new URL(mockFetch.mock.calls[0][0])
    expect(url.pathname).toBe('/v1/competitor/sources/s1')
  })

  it('createSource() calls POST /v1/competitor/sources', async () => {
    const { client, mockFetch } = createMockClient({ data: { id: 's1' } })
    await client.competitor.createSource({ name: 'Acme', url: 'https://acme.test' })
    expect(mockFetch.mock.calls[0][1].method).toBe('POST')
  })

  it('updateSource() calls PATCH /v1/competitor/sources/:id', async () => {
    const { client, mockFetch } = createMockClient({ data: { id: 's1' } })
    await client.competitor.updateSource('s1', { name: 'Updated' })
    expect(mockFetch.mock.calls[0][1].method).toBe('PATCH')
    const url = new URL(mockFetch.mock.calls[0][0])
    expect(url.pathname).toBe('/v1/competitor/sources/s1')
  })

  it('deleteSource() calls DELETE /v1/competitor/sources/:id', async () => {
    const mockFetch = vi.fn().mockResolvedValue(new Response(null, { status: 204 }))
    const client = new NumenClient({ baseUrl: 'https://api.test', fetch: mockFetch })
    await client.competitor.deleteSource('s1')
    expect(mockFetch.mock.calls[0][1].method).toBe('DELETE')
  })

  it('crawl() calls POST /v1/competitor/sources/:id/crawl', async () => {
    const { client, mockFetch } = createMockClient({ data: {} })
    await client.competitor.crawl('s1')
    expect(mockFetch.mock.calls[0][1].method).toBe('POST')
    const url = new URL(mockFetch.mock.calls[0][0])
    expect(url.pathname).toBe('/v1/competitor/sources/s1/crawl')
  })

  it('differentiation() calls GET /v1/competitor/differentiation', async () => {
    const { client, mockFetch } = createMockClient({ data: [] })
    await client.competitor.differentiation()
    const url = new URL(mockFetch.mock.calls[0][0])
    expect(url.pathname).toBe('/v1/competitor/differentiation')
  })

  it('alerts() calls GET /v1/competitor/alerts', async () => {
    const { client, mockFetch } = createMockClient({ data: [] })
    await client.competitor.alerts()
    const url = new URL(mockFetch.mock.calls[0][0])
    expect(url.pathname).toBe('/v1/competitor/alerts')
  })
})
