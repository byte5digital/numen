/**
 * Auth middleware tests: token refresh, single-flight mutex, 401 retry.
 */
import { describe, it, expect, vi } from 'vitest'
import { createAuthMiddleware } from '../../src/core/auth.js'

function mockResponse(status: number, body: unknown = {}): Response {
  return new Response(JSON.stringify(body), {
    status,
    headers: { 'Content-Type': 'application/json' },
  })
}

describe('createAuthMiddleware', () => {
  it('attaches Bearer token to requests', async () => {
    const middleware = createAuthMiddleware({ getToken: () => 'tok-123' })
    const inner = vi.fn().mockResolvedValue(mockResponse(200))
    const fetchWithAuth = middleware(inner)

    await fetchWithAuth('https://api.test/v1/test', {})
    const headers = new Headers(inner.mock.calls[0][1].headers)
    expect(headers.get('Authorization')).toBe('Bearer tok-123')
  })

  it('does not attach header when token is null', async () => {
    const middleware = createAuthMiddleware({ getToken: () => null })
    const inner = vi.fn().mockResolvedValue(mockResponse(200))
    const fetchWithAuth = middleware(inner)

    await fetchWithAuth('https://api.test/v1/test', {})
    const headers = new Headers(inner.mock.calls[0][1].headers)
    expect(headers.get('Authorization')).toBeNull()
  })

  it('retries with new token on 401 when onTokenExpired is provided', async () => {
    let currentToken = 'expired-tok'
    const middleware = createAuthMiddleware({
      getToken: () => currentToken,
      onTokenExpired: async () => {
        currentToken = 'fresh-tok'
        return 'fresh-tok'
      },
    })

    const inner = vi.fn()
      .mockResolvedValueOnce(mockResponse(401))
      .mockResolvedValueOnce(mockResponse(200, { data: 'ok' }))

    const fetchWithAuth = middleware(inner)
    const response = await fetchWithAuth('https://api.test/v1/test', {})

    expect(response.status).toBe(200)
    expect(inner).toHaveBeenCalledTimes(2)
    // Second call should have the fresh token
    const retryHeaders = new Headers(inner.mock.calls[1][1].headers)
    expect(retryHeaders.get('Authorization')).toBe('Bearer fresh-tok')
  })

  it('returns 401 response when no onTokenExpired handler', async () => {
    const middleware = createAuthMiddleware({ getToken: () => 'tok' })
    const inner = vi.fn().mockResolvedValue(mockResponse(401))
    const fetchWithAuth = middleware(inner)

    const response = await fetchWithAuth('https://api.test/v1/test', {})
    expect(response.status).toBe(401)
    expect(inner).toHaveBeenCalledTimes(1)
  })

  it('returns 401 when token refresh fails', async () => {
    const middleware = createAuthMiddleware({
      getToken: () => 'tok',
      onTokenExpired: async () => { throw new Error('Refresh failed') },
    })
    const inner = vi.fn().mockResolvedValue(mockResponse(401))
    const fetchWithAuth = middleware(inner)

    const response = await fetchWithAuth('https://api.test/v1/test', {})
    expect(response.status).toBe(401)
  })

  it('single-flights concurrent 401 refresh calls', async () => {
    let refreshCount = 0
    const middleware = createAuthMiddleware({
      getToken: () => 'tok',
      onTokenExpired: async () => {
        refreshCount++
        await new Promise(r => setTimeout(r, 50))
        return 'new-tok'
      },
    })

    const inner = vi.fn()
      .mockResolvedValue(mockResponse(401))

    const fetchWithAuth = middleware(inner)

    // Fire 3 requests that all get 401 concurrently
    // After refresh, they all retry — but refresh should only happen once
    // We mock inner to return 401 first then 200 after refresh
    inner
      .mockResolvedValueOnce(mockResponse(401))
      .mockResolvedValueOnce(mockResponse(200))
      .mockResolvedValueOnce(mockResponse(401))
      .mockResolvedValueOnce(mockResponse(200))
      .mockResolvedValueOnce(mockResponse(401))
      .mockResolvedValueOnce(mockResponse(200))

    await Promise.all([
      fetchWithAuth('https://api.test/1', {}),
      fetchWithAuth('https://api.test/2', {}),
      fetchWithAuth('https://api.test/3', {}),
    ])

    // Only 1 refresh call despite 3 concurrent 401s
    expect(refreshCount).toBe(1)
  })
})
