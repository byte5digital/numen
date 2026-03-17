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

describe('VersionsResource', () => {
  it('list() calls GET /v1/content/:id/versions', async () => {
    const { client, mockFetch } = createMockClient({ data: [] })

    await client.versions.list('c1')
    const url = new URL(mockFetch.mock.calls[0][0])
    expect(url.pathname).toBe('/v1/content/c1/versions')
  })

  it('get() calls GET /v1/content/:id/versions/:vid', async () => {
    const { client, mockFetch } = createMockClient({ data: { id: 'v1' } })

    await client.versions.get('c1', 'v1')
    const url = new URL(mockFetch.mock.calls[0][0])
    expect(url.pathname).toBe('/v1/content/c1/versions/v1')
  })

  it('createDraft() calls POST /v1/content/:id/versions/draft', async () => {
    const { client, mockFetch } = createMockClient({ data: {} })

    await client.versions.createDraft('c1')
    expect(mockFetch.mock.calls[0][1].method).toBe('POST')
    const url = new URL(mockFetch.mock.calls[0][0])
    expect(url.pathname).toBe('/v1/content/c1/versions/draft')
  })

  it('update() calls PATCH /v1/content/:id/versions/:vid', async () => {
    const { client, mockFetch } = createMockClient({ data: {} })

    await client.versions.update('c1', 'v1', { label: 'test' })
    expect(mockFetch.mock.calls[0][1].method).toBe('PATCH')
    const url = new URL(mockFetch.mock.calls[0][0])
    expect(url.pathname).toBe('/v1/content/c1/versions/v1')
  })

  it('publish() calls POST .../publish', async () => {
    const { client, mockFetch } = createMockClient({ data: {} })

    await client.versions.publish('c1', 'v1')
    const url = new URL(mockFetch.mock.calls[0][0])
    expect(url.pathname).toBe('/v1/content/c1/versions/v1/publish')
  })

  it('rollback() calls POST .../rollback', async () => {
    const { client, mockFetch } = createMockClient({ data: {} })

    await client.versions.rollback('c1', 'v1')
    expect(mockFetch.mock.calls[0][1].method).toBe('POST')
    const url = new URL(mockFetch.mock.calls[0][0])
    expect(url.pathname).toBe('/v1/content/c1/versions/v1/rollback')
  })

  it('compare() calls GET /v1/content/:id/diff', async () => {
    const { client, mockFetch } = createMockClient({ data: { changes: {} } })

    await client.versions.compare('c1', { from: 'v1', to: 'v2' })
    const url = new URL(mockFetch.mock.calls[0][0])
    expect(url.pathname).toBe('/v1/content/c1/diff')
    expect(url.searchParams.get('from')).toBe('v1')
    expect(url.searchParams.get('to')).toBe('v2')
  })

  it('label() calls POST .../label', async () => {
    const { client, mockFetch } = createMockClient({ data: {} })

    await client.versions.label('c1', 'v1', 'release-1.0')
    expect(mockFetch.mock.calls[0][1].method).toBe('POST')
    const url = new URL(mockFetch.mock.calls[0][0])
    expect(url.pathname).toBe('/v1/content/c1/versions/v1/label')
  })

  it('schedule() calls POST .../schedule', async () => {
    const { client, mockFetch } = createMockClient({ data: {} })

    await client.versions.schedule('c1', 'v1', '2026-04-01T12:00:00Z')
    const url = new URL(mockFetch.mock.calls[0][0])
    expect(url.pathname).toBe('/v1/content/c1/versions/v1/schedule')
  })

  it('cancelSchedule() calls DELETE .../schedule', async () => {
    const mockFetch = vi.fn().mockResolvedValue(new Response(null, { status: 204 }))
    const client = new NumenClient({ baseUrl: 'https://api.test', fetch: mockFetch })

    await client.versions.cancelSchedule('c1', 'v1')
    expect(mockFetch.mock.calls[0][1].method).toBe('DELETE')
    const url = new URL(mockFetch.mock.calls[0][0])
    expect(url.pathname).toBe('/v1/content/c1/versions/v1/schedule')
  })

  it('branch() calls POST .../branch', async () => {
    const { client, mockFetch } = createMockClient({ data: {} })

    await client.versions.branch('c1', 'v1')
    const url = new URL(mockFetch.mock.calls[0][0])
    expect(url.pathname).toBe('/v1/content/c1/versions/v1/branch')
  })
})
