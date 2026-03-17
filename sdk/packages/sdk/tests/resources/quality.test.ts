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

describe('QualityResource - Edge Cases', () => {
  describe('scores() with filters', () => {
    it('handles pagination parameters', async () => {
      const data = { data: [], meta: { total: 0, page: 2, perPage: 50, lastPage: 1 } }
      const { client, mockFetch } = createMockClient(data)
      await client.quality.scores({ page: 2, per_page: 50 })
      const url = new URL(mockFetch.mock.calls[0][0])
      expect(url.searchParams.get('page')).toBe('2')
      expect(url.searchParams.get('per_page')).toBe('50')
    })

    it('handles empty response', async () => {
      const data = { data: [], meta: { total: 0, page: 1, perPage: 10, lastPage: 1 } }
      const { client, mockFetch } = createMockClient(data)
      const result = await client.quality.scores()
      expect(result.data).toEqual([])
    })
  })

  describe('getScore() edge cases', () => {
    it('handles special characters in score ID', async () => {
      const { client, mockFetch } = createMockClient({ data: { id: 'qs-1' } })
      await client.quality.getScore('qs-1/special')
      const url = new URL(mockFetch.mock.calls[0][0])
      expect(url.pathname).toContain('qs-1%2Fspecial')
    })

    it('handles missing fields in score response', async () => {
      const data = { data: { id: 'qs1' } }
      const { client, mockFetch } = createMockClient(data)
      const result = await client.quality.getScore('qs1')
      expect(result.data.id).toBe('qs1')
    })
  })

  describe('score() with various payloads', () => {
    it('handles minimal score payload', async () => {
      const { client, mockFetch } = createMockClient({ data: { id: 'qs1' } })
      await client.quality.score({ content_id: 'c1' })
      const body = JSON.parse(mockFetch.mock.calls[0][1].body)
      expect(body.content_id).toBe('c1')
    })

    it('handles extended score payload', async () => {
      const { client, mockFetch } = createMockClient({ data: { id: 'qs1' } })
      const payload = {
        content_id: 'c1',
        include_suggestions: true,
        focus_areas: ['structure', 'readability']
      }
      await client.quality.score(payload)
      const body = JSON.parse(mockFetch.mock.calls[0][1].body)
      expect(body.focus_areas).toHaveLength(2)
    })

    it('handles empty focus_areas', async () => {
      const { client, mockFetch } = createMockClient({ data: { id: 'qs1' } })
      await client.quality.score({ content_id: 'c1', focus_areas: [] })
      const body = JSON.parse(mockFetch.mock.calls[0][1].body)
      expect(body.focus_areas).toEqual([])
    })
  })

  describe('trends() edge cases', () => {
    it('handles empty trends response', async () => {
      const data = { data: {} }
      const { client, mockFetch } = createMockClient(data)
      const result = await client.quality.trends()
      expect(result.data).toEqual({})
    })

    it('handles trends with time series data', async () => {
      const data = {
        data: {
          daily: [
            { date: '2024-01-01', avg_score: 75 },
            { date: '2024-01-02', avg_score: 78 }
          ]
        }
      }
      const { client, mockFetch } = createMockClient(data)
      const result = await client.quality.trends()
      expect((result.data as any).daily).toBeDefined()
    })
  })

  describe('getConfig() edge cases', () => {
    it('handles empty config', async () => {
      const data = { data: {} }
      const { client, mockFetch } = createMockClient(data)
      const result = await client.quality.getConfig()
      expect(result.data).toEqual({})
    })

    it('handles config with nested settings', async () => {
      const data = {
        data: {
          thresholds: { critical: 50, warning: 75 },
          enabled_checks: ['structure', 'grammar']
        }
      }
      const { client, mockFetch } = createMockClient(data)
      const result = await client.quality.getConfig()
      expect((result.data as any).thresholds?.critical).toBe(50)
    })
  })

  describe('updateConfig() edge cases', () => {
    it('handles partial config update', async () => {
      const { client, mockFetch } = createMockClient({ data: {} })
      await client.quality.updateConfig({ threshold: 80 })
      const body = JSON.parse(mockFetch.mock.calls[0][1].body)
      expect(body.threshold).toBe(80)
    })

    it('handles zero threshold value', async () => {
      const { client, mockFetch } = createMockClient({ data: {} })
      await client.quality.updateConfig({ threshold: 0 })
      const body = JSON.parse(mockFetch.mock.calls[0][1].body)
      expect(body.threshold).toBe(0)
    })

    it('handles boolean config values', async () => {
      const { client, mockFetch } = createMockClient({ data: {} })
      await client.quality.updateConfig({ enabled: false, auto_check: true })
      const body = JSON.parse(mockFetch.mock.calls[0][1].body)
      expect(body.enabled).toBe(false)
      expect(body.auto_check).toBe(true)
    })
  })
})
