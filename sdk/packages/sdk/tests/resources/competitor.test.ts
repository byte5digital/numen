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

describe('CompetitorResource - Additional Coverage', () => {
  describe('content()', () => {
    it('calls GET /v1/competitor/content', async () => {
      const data = { data: [{ id: 'c1', title: 'Content' }] }
      const { client, mockFetch } = createMockClient(data)
      const result = await client.competitor.content()
      expect(result.data).toHaveLength(1)
      const url = new URL(mockFetch.mock.calls[0][0])
      expect(url.pathname).toBe('/v1/competitor/content')
    })

    it('handles empty content list', async () => {
      const data = { data: [] }
      const { client, mockFetch } = createMockClient(data)
      const result = await client.competitor.content()
      expect(result.data).toEqual([])
    })
  })

  describe('createAlert()', () => {
    it('calls POST /v1/competitor/alerts with data', async () => {
      const data = { data: { id: 'a1', type: 'price_change' } }
      const { client, mockFetch } = createMockClient(data)
      await client.competitor.createAlert({ type: 'price_change' })
      expect(mockFetch.mock.calls[0][1].method).toBe('POST')
      const url = new URL(mockFetch.mock.calls[0][0])
      expect(url.pathname).toBe('/v1/competitor/alerts')
    })

    it('handles empty alert data', async () => {
      const data = { data: { id: 'a1' } }
      const { client, mockFetch } = createMockClient(data)
      await client.competitor.createAlert({})
      expect(mockFetch.mock.calls[0][1].method).toBe('POST')
    })
  })

  describe('deleteAlert()', () => {
    it('calls DELETE /v1/competitor/alerts/:id', async () => {
      const mockFetch = vi.fn().mockResolvedValue(new Response(null, { status: 204 }))
      const client = new NumenClient({ baseUrl: 'https://api.test', fetch: mockFetch })
      await client.competitor.deleteAlert('a1')
      const url = new URL(mockFetch.mock.calls[0][0])
      expect(url.pathname).toBe('/v1/competitor/alerts/a1')
      expect(mockFetch.mock.calls[0][1].method).toBe('DELETE')
    })

    it('handles special characters in alert ID', async () => {
      const mockFetch = vi.fn().mockResolvedValue(new Response(null, { status: 204 }))
      const client = new NumenClient({ baseUrl: 'https://api.test', fetch: mockFetch })
      await client.competitor.deleteAlert('a-1/special')
      const url = new URL(mockFetch.mock.calls[0][0])
      expect(url.pathname).toContain('a-1%2Fspecial')
    })
  })

  describe('differentiationSummary()', () => {
    it('calls GET /v1/competitor/differentiation/summary', async () => {
      const data = { data: { total_analyzed: 10, avg_score: 85.5 } }
      const { client, mockFetch } = createMockClient(data)
      const result = await client.competitor.differentiationSummary()
      expect(result.data).toBeDefined()
      const url = new URL(mockFetch.mock.calls[0][0])
      expect(url.pathname).toBe('/v1/competitor/differentiation/summary')
    })

    it('handles empty summary response', async () => {
      const data = { data: {} }
      const { client, mockFetch } = createMockClient(data)
      const result = await client.competitor.differentiationSummary()
      expect(result.data).toEqual({})
    })
  })

  describe('getDifferentiation()', () => {
    it('calls GET /v1/competitor/differentiation/:id', async () => {
      const data = { data: { id: 'd1', score: 95 } }
      const { client, mockFetch } = createMockClient(data)
      const result = await client.competitor.getDifferentiation('d1')
      expect(result.data.id).toBe('d1')
      const url = new URL(mockFetch.mock.calls[0][0])
      expect(url.pathname).toBe('/v1/competitor/differentiation/d1')
    })

    it('handles missing content_id in response', async () => {
      const data = { data: { id: 'd1', score: 50 } }
      const { client, mockFetch } = createMockClient(data)
      const result = await client.competitor.getDifferentiation('d1')
      expect(result.data.content_id).toBeUndefined()
    })
  })

  describe('Edge cases', () => {
    it('handles pagination with sources()', async () => {
      const data = { data: [], meta: { total: 0, page: 2, perPage: 50, lastPage: 1 } }
      const { client, mockFetch } = createMockClient(data)
      await client.competitor.sources({ page: 2, per_page: 50 })
      const url = new URL(mockFetch.mock.calls[0][0])
      expect(url.searchParams.get('page')).toBe('2')
      expect(url.searchParams.get('per_page')).toBe('50')
    })

    it('handles zero pagination values', async () => {
      const data = { data: [], meta: { total: 0, page: 0, perPage: 0, lastPage: 1 } }
      const { client, mockFetch } = createMockClient(data)
      await client.competitor.sources({ page: 0, per_page: 0 })
      const url = new URL(mockFetch.mock.calls[0][0])
      expect(url.searchParams.get('page')).toBe('0')
    })
  })
})
