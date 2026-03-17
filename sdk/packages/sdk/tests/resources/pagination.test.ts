/**
 * Pagination edge cases: pages, empty results, last page.
 */
import { describe, it, expect, vi } from 'vitest'
import { NumenClient } from '../../src/core/client.js'

function createClient(responseData: unknown, status = 200) {
  const mockFetch = vi.fn().mockResolvedValue(
    new Response(JSON.stringify(responseData), {
      status,
      headers: { 'Content-Type': 'application/json' },
    }),
  )
  return { client: new NumenClient({ baseUrl: 'https://api.test', fetch: mockFetch }), mockFetch }
}

const emptyPage = { data: [], meta: { total: 0, page: 1, perPage: 10, lastPage: 1 } }
const firstPage = {
  data: [{ id: '1' }, { id: '2' }],
  meta: { total: 5, page: 1, perPage: 2, lastPage: 3 },
}
const middlePage = {
  data: [{ id: '3' }, { id: '4' }],
  meta: { total: 5, page: 2, perPage: 2, lastPage: 3 },
}
const lastPage = {
  data: [{ id: '5' }],
  meta: { total: 5, page: 3, perPage: 2, lastPage: 3 },
}

describe('Pagination — content.list()', () => {
  it('returns empty data array for no results', async () => {
    const { client } = createClient(emptyPage)
    const result = await client.content.list()
    expect(result.data).toEqual([])
    expect(result.meta.total).toBe(0)
    expect(result.meta.lastPage).toBe(1)
  })

  it('returns first page with correct meta', async () => {
    const { client } = createClient(firstPage)
    const result = await client.content.list({ page: 1, per_page: 2 })
    expect(result.data).toHaveLength(2)
    expect(result.meta.page).toBe(1)
    expect(result.meta.lastPage).toBe(3)
  })

  it('passes page param to API', async () => {
    const { client, mockFetch } = createClient(middlePage)
    await client.content.list({ page: 2, per_page: 2 })
    const url = new URL(mockFetch.mock.calls[0][0])
    expect(url.searchParams.get('page')).toBe('2')
    expect(url.searchParams.get('per_page')).toBe('2')
  })

  it('returns last page with fewer items', async () => {
    const { client } = createClient(lastPage)
    const result = await client.content.list({ page: 3, per_page: 2 })
    expect(result.data).toHaveLength(1)
    expect(result.meta.page).toBe(3)
    expect(result.meta.page).toBe(result.meta.lastPage)
  })
})

describe('Pagination — media.list()', () => {
  it('returns empty list', async () => {
    const { client } = createClient(emptyPage)
    const result = await client.media.list()
    expect(result.data).toEqual([])
  })

  it('passes pagination params', async () => {
    const { client, mockFetch } = createClient(firstPage)
    await client.media.list({ page: 2, per_page: 5 })
    const url = new URL(mockFetch.mock.calls[0][0])
    expect(url.searchParams.get('page')).toBe('2')
    expect(url.searchParams.get('per_page')).toBe('5')
  })
})

describe('Pagination — taxonomies.list()', () => {
  it('returns paginated taxonomy list', async () => {
    const data = { data: [{ id: 't1', name: 'Tag', slug: 'tag' }], meta: { total: 1, page: 1, perPage: 10, lastPage: 1 } }
    const { client } = createClient(data)
    const result = await client.taxonomies.list()
    expect(result.data).toHaveLength(1)
  })
})

describe('Pagination — search results', () => {
  it('returns empty search results', async () => {
    const data = { data: [], meta: { total: 0, page: 1, perPage: 10, query: 'nonexistent' } }
    const { client } = createClient(data)
    const result = await client.search.search({ q: 'nonexistent' })
    expect(result.data).toEqual([])
  })

  it('passes page param to search', async () => {
    const data = { data: [{ id: '1' }], meta: { total: 50, page: 3, perPage: 10, query: 'test' } }
    const { client, mockFetch } = createClient(data)
    await client.search.search({ q: 'test', page: 3 })
    const url = new URL(mockFetch.mock.calls[0][0])
    expect(url.searchParams.get('page')).toBe('3')
  })
})
