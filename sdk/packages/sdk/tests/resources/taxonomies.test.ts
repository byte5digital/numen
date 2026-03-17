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

describe('TaxonomiesResource', () => {
  // Vocabularies
  it('list() calls GET /v1/taxonomies', async () => {
    const { client, mockFetch } = createMockClient({ data: [] })

    await client.taxonomies.list()
    const url = new URL(mockFetch.mock.calls[0][0])
    expect(url.pathname).toBe('/v1/taxonomies')
  })

  it('get() calls GET /v1/taxonomies/:slug', async () => {
    const { client, mockFetch } = createMockClient({ data: { id: '1' } })

    await client.taxonomies.get('categories')
    const url = new URL(mockFetch.mock.calls[0][0])
    expect(url.pathname).toBe('/v1/taxonomies/categories')
  })

  it('create() calls POST /v1/taxonomies', async () => {
    const { client, mockFetch } = createMockClient({ data: {} })

    await client.taxonomies.create({ name: 'Tags' })
    expect(mockFetch.mock.calls[0][1].method).toBe('POST')
    const url = new URL(mockFetch.mock.calls[0][0])
    expect(url.pathname).toBe('/v1/taxonomies')
  })

  it('update() calls PUT /v1/taxonomies/:id', async () => {
    const { client, mockFetch } = createMockClient({ data: {} })

    await client.taxonomies.update('t1', { name: 'Updated' })
    expect(mockFetch.mock.calls[0][1].method).toBe('PUT')
    const url = new URL(mockFetch.mock.calls[0][0])
    expect(url.pathname).toBe('/v1/taxonomies/t1')
  })

  it('delete() calls DELETE /v1/taxonomies/:id', async () => {
    const mockFetch = vi.fn().mockResolvedValue(new Response(null, { status: 204 }))
    const client = new NumenClient({ baseUrl: 'https://api.test', fetch: mockFetch })

    await client.taxonomies.delete('t1')
    expect(mockFetch.mock.calls[0][1].method).toBe('DELETE')
  })

  // Terms
  it('listTerms() calls GET /v1/taxonomies/:slug/terms', async () => {
    const { client, mockFetch } = createMockClient({ data: [] })

    await client.taxonomies.listTerms('categories')
    const url = new URL(mockFetch.mock.calls[0][0])
    expect(url.pathname).toBe('/v1/taxonomies/categories/terms')
  })

  it('getTerm() calls GET /v1/taxonomies/:vocab/terms/:term', async () => {
    const { client, mockFetch } = createMockClient({ data: {} })

    await client.taxonomies.getTerm('categories', 'tech')
    const url = new URL(mockFetch.mock.calls[0][0])
    expect(url.pathname).toBe('/v1/taxonomies/categories/terms/tech')
  })

  it('createTerm() calls POST /v1/taxonomies/:id/terms', async () => {
    const { client, mockFetch } = createMockClient({ data: {} })

    await client.taxonomies.createTerm('vocab-1', { name: 'JavaScript' })
    expect(mockFetch.mock.calls[0][1].method).toBe('POST')
    const url = new URL(mockFetch.mock.calls[0][0])
    expect(url.pathname).toBe('/v1/taxonomies/vocab-1/terms')
  })

  it('updateTerm() calls PUT /v1/terms/:id', async () => {
    const { client, mockFetch } = createMockClient({ data: {} })

    await client.taxonomies.updateTerm('term-1', { name: 'TypeScript' })
    expect(mockFetch.mock.calls[0][1].method).toBe('PUT')
    const url = new URL(mockFetch.mock.calls[0][0])
    expect(url.pathname).toBe('/v1/terms/term-1')
  })

  it('deleteTerm() calls DELETE /v1/terms/:id', async () => {
    const mockFetch = vi.fn().mockResolvedValue(new Response(null, { status: 204 }))
    const client = new NumenClient({ baseUrl: 'https://api.test', fetch: mockFetch })

    await client.taxonomies.deleteTerm('term-1')
    expect(mockFetch.mock.calls[0][1].method).toBe('DELETE')
    const url = new URL(mockFetch.mock.calls[0][0])
    expect(url.pathname).toBe('/v1/terms/term-1')
  })

  // Content <-> Taxonomy
  it('assignTerms() calls POST /v1/content/:id/terms', async () => {
    const mockFetch = vi.fn().mockResolvedValue(new Response(null, { status: 204 }))
    const client = new NumenClient({ baseUrl: 'https://api.test', fetch: mockFetch })

    await client.taxonomies.assignTerms('c1', ['t1', 't2'])
    expect(mockFetch.mock.calls[0][1].method).toBe('POST')
    const url = new URL(mockFetch.mock.calls[0][0])
    expect(url.pathname).toBe('/v1/content/c1/terms')
  })

  it('syncTerms() calls PUT /v1/content/:id/terms', async () => {
    const mockFetch = vi.fn().mockResolvedValue(new Response(null, { status: 204 }))
    const client = new NumenClient({ baseUrl: 'https://api.test', fetch: mockFetch })

    await client.taxonomies.syncTerms('c1', ['t1'])
    expect(mockFetch.mock.calls[0][1].method).toBe('PUT')
    const url = new URL(mockFetch.mock.calls[0][0])
    expect(url.pathname).toBe('/v1/content/c1/terms')
  })

  it('removeTerm() calls DELETE /v1/content/:id/terms/:termId', async () => {
    const mockFetch = vi.fn().mockResolvedValue(new Response(null, { status: 204 }))
    const client = new NumenClient({ baseUrl: 'https://api.test', fetch: mockFetch })

    await client.taxonomies.removeTerm('c1', 't1')
    expect(mockFetch.mock.calls[0][1].method).toBe('DELETE')
    const url = new URL(mockFetch.mock.calls[0][0])
    expect(url.pathname).toBe('/v1/content/c1/terms/t1')
  })

  it('contentTerms() calls GET /v1/content/:slug/terms', async () => {
    const { client, mockFetch } = createMockClient({ data: [] })

    await client.taxonomies.contentTerms('my-article')
    const url = new URL(mockFetch.mock.calls[0][0])
    expect(url.pathname).toBe('/v1/content/my-article/terms')
  })

  it('termContent() calls GET /v1/taxonomies/:vocab/terms/:term/content', async () => {
    const { client, mockFetch } = createMockClient({ data: [] })

    await client.taxonomies.termContent('categories', 'tech')
    const url = new URL(mockFetch.mock.calls[0][0])
    expect(url.pathname).toBe('/v1/taxonomies/categories/terms/tech/content')
  })
})
