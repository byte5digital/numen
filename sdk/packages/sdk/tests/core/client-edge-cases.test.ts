/**
 * Core client edge-case tests: auth, network errors, timeout, retry, AbortController.
 */
import { describe, it, expect, vi, beforeEach } from 'vitest'
import { NumenClient } from '../../src/core/client.js'
import {
  NumenError,
  NumenNetworkError,
  NumenAuthError,
  NumenNotFoundError,
  NumenValidationError,
  NumenRateLimitError,
} from '../../src/core/errors.js'

const BASE = 'https://api.test'

function mockFetchResponse(body: unknown, status = 200, headers: Record<string, string> = {}) {
  const h = new Headers({ 'Content-Type': 'application/json', ...headers })
  return vi.fn().mockResolvedValue(new Response(JSON.stringify(body), { status, headers: h }))
}

// ─── Auth token handling ─────────────────────────────────────

describe('Auth token handling', () => {
  it('sends Authorization header when token is set', async () => {
    const mockFetch = mockFetchResponse({ data: {} })
    const client = new NumenClient({ baseUrl: BASE, token: 'tok-123', fetch: mockFetch })
    await client.request('GET', '/v1/test')
    const headers = new Headers(mockFetch.mock.calls[0][1].headers)
    expect(headers.get('Authorization')).toBe('Bearer tok-123')
  })

  it('sends X-Api-Key when no token but apiKey provided', async () => {
    const mockFetch = mockFetchResponse({ data: {} })
    const client = new NumenClient({ baseUrl: BASE, apiKey: 'sk-key', fetch: mockFetch })
    await client.request('GET', '/v1/test')
    const headers = new Headers(mockFetch.mock.calls[0][1].headers)
    expect(headers.get('X-Api-Key')).toBe('sk-key')
  })

  it('prefers token over apiKey', async () => {
    const mockFetch = mockFetchResponse({ data: {} })
    const client = new NumenClient({ baseUrl: BASE, apiKey: 'sk-key', token: 'tok-123', fetch: mockFetch })
    await client.request('GET', '/v1/test')
    const headers = new Headers(mockFetch.mock.calls[0][1].headers)
    expect(headers.get('Authorization')).toBe('Bearer tok-123')
    expect(headers.get('X-Api-Key')).toBeNull()
  })

  it('updates token with setToken()', async () => {
    const mockFetch = mockFetchResponse({ data: {} })
    const client = new NumenClient({ baseUrl: BASE, fetch: mockFetch })
    client.setToken('new-tok')
    await client.request('GET', '/v1/test')
    const headers = new Headers(mockFetch.mock.calls[0][1].headers)
    expect(headers.get('Authorization')).toBe('Bearer new-tok')
  })

  it('clears token with clearToken()', async () => {
    const mockFetch = mockFetchResponse({ data: {} })
    const client = new NumenClient({ baseUrl: BASE, token: 'tok-123', fetch: mockFetch })
    client.clearToken()
    await client.request('GET', '/v1/test')
    const headers = new Headers(mockFetch.mock.calls[0][1].headers)
    expect(headers.get('Authorization')).toBeNull()
  })
})

// ─── Network error scenarios ─────────────────────────────────

describe('Network error scenarios', () => {
  it('throws NumenNetworkError on fetch failure', async () => {
    const mockFetch = vi.fn().mockRejectedValue(new TypeError('Failed to fetch'))
    const client = new NumenClient({ baseUrl: BASE, fetch: mockFetch })
    await expect(client.request('GET', '/v1/test')).rejects.toThrow(NumenNetworkError)
  })

  it('throws NumenNetworkError with message on DNS failure', async () => {
    const mockFetch = vi.fn().mockRejectedValue(new TypeError('getaddrinfo ENOTFOUND api.test'))
    const client = new NumenClient({ baseUrl: BASE, fetch: mockFetch })
    await expect(client.request('GET', '/v1/test')).rejects.toThrow('getaddrinfo ENOTFOUND api.test')
  })

  it('throws NumenNetworkError on timeout (AbortError)', async () => {
    const mockFetch = vi.fn().mockImplementation(() => {
      const err = new DOMException('The operation was aborted.', 'AbortError')
      return Promise.reject(err)
    })
    const client = new NumenClient({ baseUrl: BASE, fetch: mockFetch, timeout: 100 })
    await expect(client.request('GET', '/v1/test')).rejects.toThrow(NumenNetworkError)
  })

  it('maps 500 to generic NumenError', async () => {
    const mockFetch = mockFetchResponse({ message: 'Internal Server Error' }, 500)
    const client = new NumenClient({ baseUrl: BASE, fetch: mockFetch })
    try {
      await client.request('GET', '/v1/test')
      expect.fail('Should have thrown')
    } catch (err) {
      expect(err).toBeInstanceOf(NumenError)
      expect((err as NumenError).status).toBe(500)
    }
  })

  it('maps 502 Bad Gateway to NumenError', async () => {
    const mockFetch = mockFetchResponse({ message: 'Bad Gateway' }, 502)
    const client = new NumenClient({ baseUrl: BASE, fetch: mockFetch })
    try {
      await client.request('GET', '/v1/test')
      expect.fail('Should have thrown')
    } catch (err) {
      expect(err).toBeInstanceOf(NumenError)
      expect((err as NumenError).status).toBe(502)
    }
  })

  it('maps 503 Service Unavailable to NumenError', async () => {
    const mockFetch = mockFetchResponse({ message: 'Service Unavailable' }, 503)
    const client = new NumenClient({ baseUrl: BASE, fetch: mockFetch })
    try {
      await client.request('GET', '/v1/test')
      expect.fail('Should have thrown')
    } catch (err) {
      expect(err).toBeInstanceOf(NumenError)
      expect((err as NumenError).status).toBe(503)
    }
  })
})

// ─── Error response mapping ─────────────────────────────────

describe('Error response mapping', () => {
  it('maps 401 to NumenAuthError', async () => {
    const mockFetch = mockFetchResponse({ message: 'Unauthorized' }, 401)
    const client = new NumenClient({ baseUrl: BASE, fetch: mockFetch })
    await expect(client.request('GET', '/v1/test')).rejects.toThrow(NumenAuthError)
  })

  it('maps 403 to NumenAuthError', async () => {
    const mockFetch = mockFetchResponse({ message: 'Forbidden' }, 403)
    const client = new NumenClient({ baseUrl: BASE, fetch: mockFetch })
    try {
      await client.request('GET', '/v1/test')
      expect.fail('Should have thrown')
    } catch (err) {
      expect(err).toBeInstanceOf(NumenAuthError)
      expect((err as NumenAuthError).status).toBe(403)
    }
  })

  it('maps 404 to NumenNotFoundError', async () => {
    const mockFetch = mockFetchResponse({ message: 'Not found' }, 404)
    const client = new NumenClient({ baseUrl: BASE, fetch: mockFetch })
    await expect(client.request('GET', '/v1/test')).rejects.toThrow(NumenNotFoundError)
  })

  it('maps 422 to NumenValidationError with fields', async () => {
    const body = { message: 'Validation failed', errors: { title: ['required'] } }
    const mockFetch = mockFetchResponse(body, 422)
    const client = new NumenClient({ baseUrl: BASE, fetch: mockFetch })
    try {
      await client.request('POST', '/v1/test', { body: {} })
      expect.fail('Should have thrown')
    } catch (err) {
      expect(err).toBeInstanceOf(NumenValidationError)
      expect((err as NumenValidationError).fields).toEqual({ title: ['required'] })
    }
  })

  it('maps 429 to NumenRateLimitError with retryAfter', async () => {
    const h = new Headers({ 'Content-Type': 'application/json', 'Retry-After': '30' })
    const mockFetch = vi.fn().mockResolvedValue(
      new Response(JSON.stringify({ message: 'Rate limited' }), { status: 429, headers: h })
    )
    const client = new NumenClient({ baseUrl: BASE, fetch: mockFetch })
    try {
      await client.request('GET', '/v1/test')
      expect.fail('Should have thrown')
    } catch (err) {
      expect(err).toBeInstanceOf(NumenRateLimitError)
      expect((err as NumenRateLimitError).retryAfter).toBe(30)
    }
  })
})

// ─── Request options ─────────────────────────────────────────

describe('Request options', () => {
  it('passes query params correctly', async () => {
    const mockFetch = mockFetchResponse({ data: [] })
    const client = new NumenClient({ baseUrl: BASE, fetch: mockFetch })
    await client.request('GET', '/v1/test', { params: { page: 2, type: 'article' } })
    const url = new URL(mockFetch.mock.calls[0][0])
    expect(url.searchParams.get('page')).toBe('2')
    expect(url.searchParams.get('type')).toBe('article')
  })

  it('skips undefined query params', async () => {
    const mockFetch = mockFetchResponse({ data: [] })
    const client = new NumenClient({ baseUrl: BASE, fetch: mockFetch })
    await client.request('GET', '/v1/test', { params: { page: 1, type: undefined } })
    const url = new URL(mockFetch.mock.calls[0][0])
    expect(url.searchParams.has('type')).toBe(false)
  })

  it('sends JSON body for POST requests', async () => {
    const mockFetch = mockFetchResponse({ data: {} })
    const client = new NumenClient({ baseUrl: BASE, fetch: mockFetch })
    await client.request('POST', '/v1/test', { body: { title: 'Hello' } })
    const body = JSON.parse(mockFetch.mock.calls[0][1].body)
    expect(body.title).toBe('Hello')
  })

  it('handles 204 No Content responses', async () => {
    const mockFetch = vi.fn().mockResolvedValue(new Response(null, { status: 204 }))
    const client = new NumenClient({ baseUrl: BASE, fetch: mockFetch })
    const result = await client.request('DELETE', '/v1/test/123')
    expect(result).toBeUndefined()
  })

  it('merges custom headers', async () => {
    const mockFetch = mockFetchResponse({ data: {} })
    const client = new NumenClient({ baseUrl: BASE, fetch: mockFetch, headers: { 'X-Custom': 'base' } })
    await client.request('GET', '/v1/test', { headers: { 'X-Request': 'per-req' } })
    const headers = new Headers(mockFetch.mock.calls[0][1].headers)
    expect(headers.get('X-Custom')).toBe('base')
    expect(headers.get('X-Request')).toBe('per-req')
  })
})
