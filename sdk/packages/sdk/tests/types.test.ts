import { describe, it, expect } from 'vitest'
import {
  NumenClient,
  NumenError,
  NumenAuthError,
  NumenNotFoundError,
  NumenValidationError,
  NumenRateLimitError,
  NumenNetworkError,
  createAuthMiddleware,
  mapResponseToError,
} from '../src/index.js'
import type {
  NumenClientOptions,
  CacheOptions,
  ApiResponse,
  PaginatedResponse,
  ApiError,
  RequestOptions,
  AuthMiddlewareOptions,
} from '../src/index.js'

describe('TypeScript exports verification', () => {
  it('NumenClient is exported and constructible', () => {
    const client = new NumenClient({ baseUrl: 'https://test.api' })
    expect(client).toBeInstanceOf(NumenClient)
  })

  it('Error classes are exported', () => {
    expect(NumenError).toBeDefined()
    expect(NumenAuthError).toBeDefined()
    expect(NumenNotFoundError).toBeDefined()
    expect(NumenValidationError).toBeDefined()
    expect(NumenRateLimitError).toBeDefined()
    expect(NumenNetworkError).toBeDefined()
  })

  it('createAuthMiddleware is exported', () => {
    expect(typeof createAuthMiddleware).toBe('function')
  })

  it('mapResponseToError is exported', () => {
    expect(typeof mapResponseToError).toBe('function')
  })

  it('NumenClient has expected resource modules', () => {
    const client = new NumenClient({ baseUrl: 'https://test.api' })
    expect(client.content).toBeDefined()
    expect(client.pages).toBeDefined()
    expect(client.media).toBeDefined()
    expect(client.search).toBeDefined()
    expect(client.versions).toBeDefined()
    expect(client.taxonomies).toBeDefined()
    expect(client.briefs).toBeDefined()
    expect(client.pipeline).toBeDefined()
    expect(client.webhooks).toBeDefined()
    expect(client.graph).toBeDefined()
    expect(client.chat).toBeDefined()
    expect(client.repurpose).toBeDefined()
    expect(client.translations).toBeDefined()
    expect(client.quality).toBeDefined()
    expect(client.competitor).toBeDefined()
    expect(client.admin).toBeDefined()
  })

  it('NumenClient has realtime module', () => {
    const client = new NumenClient({ baseUrl: 'https://test.api' })
    expect(client.realtime).toBeDefined()
    expect(typeof client.realtime.subscribe).toBe('function')
  })

  it('NumenClient has cache', () => {
    const client = new NumenClient({ baseUrl: 'https://test.api' })
    expect(client.cache).toBeDefined()
    expect(typeof client.cache.get).toBe('function')
    expect(typeof client.cache.set).toBe('function')
  })

  it('Type interfaces are usable at compile time', () => {
    const opts: NumenClientOptions = { baseUrl: 'https://test.api', apiKey: 'sk-test' }
    const cache: CacheOptions = { ttl: 300, maxSize: 50 }
    const reqOpts: RequestOptions = { params: { page: 1 }, noCache: true }
    const authOpts: AuthMiddlewareOptions = { getToken: () => 'tok' }
    expect(opts.baseUrl).toBe('https://test.api')
    expect(cache.ttl).toBe(300)
    expect(reqOpts.noCache).toBe(true)
    expect(typeof authOpts.getToken).toBe('function')
  })

  it('Error class instanceof chain works correctly', () => {
    const auth = new NumenAuthError('test', 401, null)
    expect(auth instanceof Error).toBe(true)
    expect(auth instanceof NumenError).toBe(true)
    expect(auth instanceof NumenAuthError).toBe(true)
    expect(auth instanceof NumenNotFoundError).toBe(false)
  })
})
