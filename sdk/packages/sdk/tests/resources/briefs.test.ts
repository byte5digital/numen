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

describe('BriefsResource', () => {
  it('list() calls GET /v1/briefs', async () => {
    const data = { data: [], meta: { total: 0, page: 1, perPage: 10, lastPage: 1 } }
    const { client, mockFetch } = createMockClient(data)
    const result = await client.briefs.list()
    expect(result).toEqual(data)
    const url = new URL(mockFetch.mock.calls[0][0])
    expect(url.pathname).toBe('/v1/briefs')
  })

  it('get() calls GET /v1/briefs/:id', async () => {
    const data = { data: { id: 'b1', title: 'Brief' } }
    const { client, mockFetch } = createMockClient(data)
    await client.briefs.get('b1')
    const url = new URL(mockFetch.mock.calls[0][0])
    expect(url.pathname).toBe('/v1/briefs/b1')
  })

  it('create() calls POST /v1/briefs', async () => {
    const data = { data: { id: 'b1' } }
    const { client, mockFetch } = createMockClient(data)
    await client.briefs.create({ title: 'New Brief' })
    expect(mockFetch.mock.calls[0][1].method).toBe('POST')
    const url = new URL(mockFetch.mock.calls[0][0])
    expect(url.pathname).toBe('/v1/briefs')
  })

  it('approve() calls POST /v1/pipeline-runs/:id/approve', async () => {
    const { client, mockFetch } = createMockClient({ data: {} })
    await client.briefs.approve('run1')
    expect(mockFetch.mock.calls[0][1].method).toBe('POST')
    const url = new URL(mockFetch.mock.calls[0][0])
    expect(url.pathname).toBe('/v1/pipeline-runs/run1/approve')
  })
})
