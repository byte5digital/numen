/**
 * Search edge cases: empty query, special characters, no results.
 */
import { describe, it, expect, vi } from 'vitest'
import { NumenClient } from '../../src/core/client.js'

function createClient(responseData: unknown) {
  const mockFetch = vi.fn().mockResolvedValue(
    new Response(JSON.stringify(responseData), {
      status: 200,
      headers: { 'Content-Type': 'application/json' },
    }),
  )
  return { client: new NumenClient({ baseUrl: 'https://api.test', fetch: mockFetch }), mockFetch }
}

describe('Search edge cases', () => {
  it('handles empty query string', async () => {
    const data = { data: [], meta: { total: 0, page: 1, perPage: 10, query: '' } }
    const { client, mockFetch } = createClient(data)
    const result = await client.search.search({ q: '' })
    expect(result.data).toEqual([])
    const url = new URL(mockFetch.mock.calls[0][0])
    expect(url.searchParams.get('q')).toBe('')
  })

  it('handles special characters in query', async () => {
    const data = { data: [], meta: { total: 0, page: 1, perPage: 10, query: 'hello & world <script>' } }
    const { client, mockFetch } = createClient(data)
    await client.search.search({ q: 'hello & world <script>' })
    const url = new URL(mockFetch.mock.calls[0][0])
    expect(url.searchParams.get('q')).toBe('hello & world <script>')
  })

  it('handles Unicode characters in query', async () => {
    const data = { data: [], meta: { total: 0, page: 1, perPage: 10, query: '日本語テスト' } }
    const { client, mockFetch } = createClient(data)
    await client.search.search({ q: '日本語テスト' })
    const url = new URL(mockFetch.mock.calls[0][0])
    expect(url.searchParams.get('q')).toBe('日本語テスト')
  })

  it('handles very long query strings', async () => {
    const longQuery = 'a'.repeat(500)
    const data = { data: [], meta: { total: 0, page: 1, perPage: 10, query: longQuery } }
    const { client, mockFetch } = createClient(data)
    await client.search.search({ q: longQuery })
    const url = new URL(mockFetch.mock.calls[0][0])
    expect(url.searchParams.get('q')).toBe(longQuery)
  })

  it('suggest returns empty array for no suggestions', async () => {
    const { client } = createClient({ data: [] })
    const result = await client.search.suggest({ q: 'zzzzzzz' })
    expect(result.data).toEqual([])
  })

  it('passes type filter to search', async () => {
    const data = { data: [], meta: { total: 0, page: 1, perPage: 10, query: 'test' } }
    const { client, mockFetch } = createClient(data)
    await client.search.search({ q: 'test', type: 'article' })
    const url = new URL(mockFetch.mock.calls[0][0])
    expect(url.searchParams.get('type')).toBe('article')
  })

  it('passes per_page to search', async () => {
    const data = { data: [], meta: { total: 0, page: 1, perPage: 5, query: 'test' } }
    const { client, mockFetch } = createClient(data)
    await client.search.search({ q: 'test', per_page: 5 })
    const url = new URL(mockFetch.mock.calls[0][0])
    expect(url.searchParams.get('per_page')).toBe('5')
  })

  it('ask() handles empty question gracefully', async () => {
    const data = { data: { answer: '', sources: [] } }
    const { client } = createClient(data)
    const result = await client.search.ask({ question: '' })
    expect(result.data.answer).toBe('')
  })
})
