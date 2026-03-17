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

describe('SearchResource', () => {
  it('search() calls GET /v1/search with q param', async () => {
    const data = { data: [], meta: { total: 0, page: 1, perPage: 10, query: 'test' } }
    const { client, mockFetch } = createMockClient(data)

    const result = await client.search.search({ q: 'test' })
    expect(result).toEqual(data)
    const url = new URL(mockFetch.mock.calls[0][0])
    expect(url.pathname).toBe('/v1/search')
    expect(url.searchParams.get('q')).toBe('test')
  })

  it('suggest() calls GET /v1/search/suggest', async () => {
    const { client, mockFetch } = createMockClient({ data: ['suggestion1'] })

    await client.search.suggest({ q: 'hel' })
    const url = new URL(mockFetch.mock.calls[0][0])
    expect(url.pathname).toBe('/v1/search/suggest')
    expect(url.searchParams.get('q')).toBe('hel')
  })

  it('ask() calls POST /v1/search/ask', async () => {
    const data = { data: { answer: 'Yes', sources: [] } }
    const { client, mockFetch } = createMockClient(data)

    await client.search.ask({ question: 'What is Numen?' })
    expect(mockFetch.mock.calls[0][1].method).toBe('POST')
    const url = new URL(mockFetch.mock.calls[0][0])
    expect(url.pathname).toBe('/v1/search/ask')
    const body = JSON.parse(mockFetch.mock.calls[0][1].body)
    expect(body.question).toBe('What is Numen?')
  })

  it('recordClick() calls POST /v1/search/click', async () => {
    const mockFetch = vi.fn().mockResolvedValue(new Response(null, { status: 204 }))
    const client = new NumenClient({ baseUrl: 'https://api.test', fetch: mockFetch })

    await client.search.recordClick({ query: 'test', content_id: 'c1' })
    expect(mockFetch.mock.calls[0][1].method).toBe('POST')
    const url = new URL(mockFetch.mock.calls[0][0])
    expect(url.pathname).toBe('/v1/search/click')
  })
})
