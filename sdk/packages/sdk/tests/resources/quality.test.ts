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

describe('QualityResource', () => {
  it('scores() calls GET /v1/quality/scores', async () => {
    const data = { data: [], meta: { total: 0, page: 1, perPage: 10, lastPage: 1 } }
    const { client, mockFetch } = createMockClient(data)
    await client.quality.scores()
    const url = new URL(mockFetch.mock.calls[0][0])
    expect(url.pathname).toBe('/v1/quality/scores')
  })

  it('getScore() calls GET /v1/quality/scores/:id', async () => {
    const { client, mockFetch } = createMockClient({ data: { id: 'qs1' } })
    await client.quality.getScore('qs1')
    const url = new URL(mockFetch.mock.calls[0][0])
    expect(url.pathname).toBe('/v1/quality/scores/qs1')
  })

  it('score() calls POST /v1/quality/score', async () => {
    const { client, mockFetch } = createMockClient({ data: { id: 'qs1' } })
    await client.quality.score({ content_id: 'c1' })
    expect(mockFetch.mock.calls[0][1].method).toBe('POST')
    const url = new URL(mockFetch.mock.calls[0][0])
    expect(url.pathname).toBe('/v1/quality/score')
  })

  it('trends() calls GET /v1/quality/trends', async () => {
    const { client, mockFetch } = createMockClient({ data: {} })
    await client.quality.trends()
    const url = new URL(mockFetch.mock.calls[0][0])
    expect(url.pathname).toBe('/v1/quality/trends')
  })

  it('getConfig() calls GET /v1/quality/config', async () => {
    const { client, mockFetch } = createMockClient({ data: {} })
    await client.quality.getConfig()
    const url = new URL(mockFetch.mock.calls[0][0])
    expect(url.pathname).toBe('/v1/quality/config')
  })

  it('updateConfig() calls PUT /v1/quality/config', async () => {
    const { client, mockFetch } = createMockClient({ data: {} })
    await client.quality.updateConfig({ threshold: 80 })
    expect(mockFetch.mock.calls[0][1].method).toBe('PUT')
    const url = new URL(mockFetch.mock.calls[0][0])
    expect(url.pathname).toBe('/v1/quality/config')
  })
})
