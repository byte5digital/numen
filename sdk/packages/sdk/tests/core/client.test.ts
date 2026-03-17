/**
 * Tests for NumenClient core functionality.
 */
import { describe, it, expect, beforeEach } from 'vitest'
import { NumenClient } from '../../src/core/client.js'
import type { NumenClientOptions } from '../../src/types/sdk.js'

const BASE_URL = 'https://api.numen.test'

function makeMockFetch(response: Partial<Response> & { json?: () => Promise<unknown> }): typeof globalThis.fetch {
  return async (_input: RequestInfo | URL, _init?: RequestInit) => {
    return {
      ok: true,
      status: 200,
      headers: new Headers(),
      json: response.json ?? (() => Promise.resolve({})),
      ...response,
    } as Response
  }
}

describe('NumenClient', () => {
  describe('constructor', () => {
    it('creates a client with valid options', () => {
      const client = new NumenClient({ baseUrl: BASE_URL })
      expect(client).toBeInstanceOf(NumenClient)
    })

    it('throws if baseUrl is missing', () => {
      expect(() => new NumenClient({ baseUrl: '' })).toThrow('[numen/sdk] baseUrl is required')
    })

    it('accepts an apiKey', () => {
      const client = new NumenClient({ baseUrl: BASE_URL, apiKey: 'sk-test' })
      expect(client).toBeInstanceOf(NumenClient)
    })

    it('uses the provided fetch implementation', () => {
      const mockFetch = makeMockFetch({})
      const client = new NumenClient({ baseUrl: BASE_URL, fetch: mockFetch })
      expect(client).toBeInstanceOf(NumenClient)
    })
  })

  describe('setToken / clearToken / getToken', () => {
    let client: NumenClient

    beforeEach(() => {
      client = new NumenClient({ baseUrl: BASE_URL, fetch: makeMockFetch({}) })
    })

    it('starts with no token when none is provided', () => {
      expect(client.getToken()).toBeNull()
    })

    it('stores a token via setToken()', () => {
      client.setToken('tok-abc')
      expect(client.getToken()).toBe('tok-abc')
    })

    it('clears the token via clearToken()', () => {
      client.setToken('tok-abc')
      client.clearToken()
      expect(client.getToken()).toBeNull()
    })

    it('sets initial token from options', () => {
      const c = new NumenClient({ baseUrl: BASE_URL, token: 'initial-tok', fetch: makeMockFetch({}) })
      expect(c.getToken()).toBe('initial-tok')
    })
  })

  describe('request()', () => {
    it('includes Authorization header when token is set', async () => {
      let capturedHeaders: Headers | undefined

      const mockFetch: typeof globalThis.fetch = async (_input, init) => {
        capturedHeaders = new Headers(init?.headers)
        return {
          ok: true,
          status: 200,
          headers: new Headers(),
          json: () => Promise.resolve({ hello: 'world' }),
        } as Response
      }

      const client = new NumenClient({ baseUrl: BASE_URL, fetch: mockFetch })
      client.setToken('my-token')

      await client.request<{ hello: string }>('GET', '/v1/test')

      expect(capturedHeaders?.get('Authorization')).toBe('Bearer my-token')
    })

    it('includes X-Api-Key header when apiKey is set and no token', async () => {
      let capturedHeaders: Headers | undefined

      const mockFetch: typeof globalThis.fetch = async (_input, init) => {
        capturedHeaders = new Headers(init?.headers)
        return {
          ok: true,
          status: 200,
          headers: new Headers(),
          json: () => Promise.resolve({}),
        } as Response
      }

      const client = new NumenClient({ baseUrl: BASE_URL, apiKey: 'sk-key', fetch: mockFetch })
      await client.request('GET', '/v1/test')

      expect(capturedHeaders?.get('X-Api-Key')).toBe('sk-key')
    })

    it('sends query params', async () => {
      let capturedUrl: string | undefined

      const mockFetch: typeof globalThis.fetch = async (input, _init) => {
        capturedUrl = input.toString()
        return {
          ok: true,
          status: 200,
          headers: new Headers(),
          json: () => Promise.resolve([]),
        } as Response
      }

      const client = new NumenClient({ baseUrl: BASE_URL, fetch: mockFetch })
      await client.request('GET', '/v1/items', { params: { page: 2, limit: 10 } })

      expect(capturedUrl).toContain('page=2')
      expect(capturedUrl).toContain('limit=10')
    })

    it('throws NumenAuthError on 401', async () => {
      const mockFetch: typeof globalThis.fetch = async () => ({
        ok: false,
        status: 401,
        headers: new Headers(),
        json: () => Promise.resolve({ message: 'Unauthorized' }),
      } as Response)

      const client = new NumenClient({ baseUrl: BASE_URL, fetch: mockFetch })
      const { NumenAuthError } = await import('../../src/core/errors.js')

      await expect(client.request('GET', '/v1/secure')).rejects.toThrow(NumenAuthError)
    })
  })

  describe('resource stubs', () => {
    it('exposes content, pages, media, search stubs', () => {
      const client = new NumenClient({ baseUrl: BASE_URL, fetch: makeMockFetch({}) })
      expect(client.content).toBeDefined()
      expect(client.pages).toBeDefined()
      expect(client.media).toBeDefined()
      expect(client.search).toBeDefined()
    })
  })
})
