/**
 * Options for initializing the Numen SDK client.
 */
export interface NumenClientOptions {
  /** Base URL for the Numen API (e.g., https://api.numen.ai) */
  baseUrl: string
  /** API key for authentication */
  apiKey?: string
  /** Bearer token for authentication */
  token?: string
  /** Request timeout in milliseconds (default: 30000) */
  timeout?: number
  /** Cache configuration */
  cache?: CacheOptions
  /** Custom fetch implementation */
  fetch?: typeof globalThis.fetch
  /** Additional default headers */
  headers?: Record<string, string>
}

/**
 * Options for configuring the SDK's built-in caching layer.
 */
export interface CacheOptions {
  /** Enable caching (default: true) */
  enabled?: boolean
  /** Default TTL in seconds (default: 300) */
  ttl?: number
  /** Maximum number of cache entries (default: 100) */
  maxSize?: number
  /** Cache storage strategy */
  storage?: 'memory' | 'localStorage' | 'sessionStorage'
}
