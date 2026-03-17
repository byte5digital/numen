import { describe, it, expect } from 'vitest'
import { createNumenClient, SDK_VERSION } from '../src/index.js'
import type { NumenClientOptions, CacheOptions } from '../src/types/sdk.js'
import type { ApiResponse, PaginatedResponse, ApiError } from '../src/types/api.js'

describe('@numen/sdk — scaffold smoke tests', () => {
  it('exports SDK_VERSION', () => {
    expect(SDK_VERSION).toBe('0.1.0')
  })

  it('createNumenClient throws without baseUrl', () => {
    expect(() =>
      createNumenClient({} as NumenClientOptions)
    ).toThrow('[numen/sdk] baseUrl is required')
  })

  it('createNumenClient returns a client object', () => {
    const client = createNumenClient({ baseUrl: 'https://api.numen.ai' })
    expect(client).toBeDefined()
    expect(client._options.baseUrl).toBe('https://api.numen.ai')
    expect(client._version).toBe(SDK_VERSION)
  })

  it('NumenClientOptions type accepts all expected fields', () => {
    const opts: NumenClientOptions = {
      baseUrl: 'https://api.numen.ai',
      apiKey: 'test-key',
      token: 'bearer-token',
      timeout: 5000,
      headers: { 'X-Custom': 'value' },
      cache: {
        enabled: true,
        ttl: 60,
        maxSize: 50,
        storage: 'memory',
      } satisfies CacheOptions,
    }
    expect(opts.baseUrl).toBeDefined()
  })

  it('ApiResponse type is structurally correct', () => {
    const response: ApiResponse<{ id: string }> = {
      data: { id: 'abc' },
      status: 200,
      ok: true,
    }
    expect(response.ok).toBe(true)
  })

  it('PaginatedResponse type is structurally correct', () => {
    const page: PaginatedResponse<string> = {
      data: ['a', 'b'],
      meta: { total: 2, page: 1, perPage: 10, lastPage: 1 },
    }
    expect(page.meta.total).toBe(2)
  })

  it('ApiError type is structurally correct', () => {
    const err: ApiError = {
      message: 'Validation failed',
      code: 'VALIDATION_ERROR',
      errors: { email: ['is invalid'] },
    }
    expect(err.message).toBeDefined()
  })
})
