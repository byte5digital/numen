/**
 * @numen/sdk — NumenClient
 * Core HTTP client for the Numen API.
 */

import type { NumenClientOptions } from '../types/sdk.js'
import { mapResponseToError, NumenNetworkError } from './errors.js'
import { createAuthMiddleware } from './auth.js'
import { SWRCache } from './cache.js'
import { ContentResource } from '../resources/content.js'
import { PagesResource } from '../resources/pages.js'
import { MediaResource } from '../resources/media.js'
import { SearchResource } from '../resources/search.js'
import { VersionsResource } from '../resources/versions.js'
import { TaxonomiesResource } from '../resources/taxonomies.js'

export interface RequestOptions {
  /** Query parameters */
  params?: Record<string, string | number | boolean | undefined>
  /** Request body (will be JSON-serialised) */
  body?: unknown
  /** Additional per-request headers */
  headers?: Record<string, string>
  /** Per-request cache TTL override (ms) */
  cacheTtl?: number
  /** Skip cache for this request */
  noCache?: boolean
}

/**
 * Core Numen API client.
 *
 * @example
 * ```ts
 * const client = new NumenClient({ baseUrl: 'https://api.numen.ai', apiKey: 'sk-...' })
 * const result = await client.request<MyType>('GET', '/v1/content')
 * ```
 */
export class NumenClient {
  private readonly options: NumenClientOptions
  private token: string | null
  private fetchFn: typeof globalThis.fetch
  readonly cache: SWRCache

  // Resource modules
  readonly content: ContentResource
  readonly pages: PagesResource
  readonly media: MediaResource
  readonly search: SearchResource
  readonly versions: VersionsResource
  readonly taxonomies: TaxonomiesResource

  constructor(options: NumenClientOptions) {
    if (!options.baseUrl) {
      throw new Error('[numen/sdk] baseUrl is required')
    }

    this.options = options
    this.token = options.token ?? null

    // Cache
    this.cache = new SWRCache({
      maxSize: options.cache?.maxSize ?? 100,
      ttl: options.cache?.ttl ? options.cache.ttl * 1000 : 300_000,
    })

    // Build the fetch pipeline
    const baseFetch: typeof globalThis.fetch = options.fetch ?? globalThis.fetch

    if (!baseFetch) {
      throw new Error('[numen/sdk] No fetch implementation found. Pass options.fetch or run in an environment with globalThis.fetch.')
    }

    // Wrap with auth middleware
    const authMiddleware = createAuthMiddleware({
      getToken: () => this.token,
    })

    this.fetchFn = authMiddleware(baseFetch)

    // Initialize resource modules
    this.content = new ContentResource(this)
    this.pages = new PagesResource(this)
    this.media = new MediaResource(this)
    this.search = new SearchResource(this)
    this.versions = new VersionsResource(this)
    this.taxonomies = new TaxonomiesResource(this)
  }

  /**
   * Set the Bearer token used for subsequent requests.
   */
  setToken(token: string): void {
    this.token = token
  }

  /**
   * Clear the current Bearer token.
   */
  clearToken(): void {
    this.token = null
  }

  /**
   * Returns the current token (may be null).
   */
  getToken(): string | null {
    return this.token
  }

  /**
   * Make a typed HTTP request to the Numen API.
   *
   * @param method  HTTP method
   * @param path    Path relative to baseUrl (must start with /)
   * @param options Request options
   */
  async request<T>(method: string, path: string, options: RequestOptions = {}): Promise<T> {
    const url = new URL(path, this.options.baseUrl)

    // Append query params
    if (options.params) {
      for (const [key, value] of Object.entries(options.params)) {
        if (value !== undefined) {
          url.searchParams.set(key, String(value))
        }
      }
    }

    const defaultHeaders: Record<string, string> = {
      'Content-Type': 'application/json',
      Accept: 'application/json',
      ...this.options.headers,
      ...options.headers,
    }

    // API key header (if token is absent)
    if (!this.token && this.options.apiKey) {
      defaultHeaders['X-Api-Key'] = this.options.apiKey
    }

    const init: RequestInit = {
      method: method.toUpperCase(),
      headers: defaultHeaders,
    }

    if (options.body !== undefined) {
      init.body = JSON.stringify(options.body)
    }

    // Timeout support
    const timeout = this.options.timeout ?? 30_000
    const controller = new AbortController()
    const timer = setTimeout(() => controller.abort(), timeout)
    init.signal = controller.signal

    let response: Response
    try {
      response = await this.fetchFn(url.toString(), init)
    } catch (err: unknown) {
      clearTimeout(timer)
      if (err instanceof Error && err.name === 'AbortError') {
        throw new NumenNetworkError(`Request timed out after ${timeout}ms`, err)
      }
      throw new NumenNetworkError(
        err instanceof Error ? err.message : 'Network request failed',
        err
      )
    }

    clearTimeout(timer)

    if (!response.ok) {
      throw await mapResponseToError(response)
    }

    // 204 No Content
    if (response.status === 204) {
      return undefined as unknown as T
    }

    return response.json() as Promise<T>
  }
}
