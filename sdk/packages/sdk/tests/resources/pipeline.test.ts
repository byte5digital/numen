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

describe('PipelineResource', () => {
  it('get() calls GET /v1/pipeline-runs/:id', async () => {
    const data = { data: { id: 'r1', status: 'running' } }
    const { client, mockFetch } = createMockClient(data)
    const result = await client.pipeline.get('r1')
    expect(result).toEqual(data)
    const url = new URL(mockFetch.mock.calls[0][0])
    expect(url.pathname).toBe('/v1/pipeline-runs/r1')
  })

  it('approve() calls POST /v1/pipeline-runs/:id/approve', async () => {
    const { client, mockFetch } = createMockClient({ data: {} })
    await client.pipeline.approve('r1')
    expect(mockFetch.mock.calls[0][1].method).toBe('POST')
    const url = new URL(mockFetch.mock.calls[0][0])
    expect(url.pathname).toBe('/v1/pipeline-runs/r1/approve')
  })
})
