/**
 * @numen/sdk - Typed Frontend SDK for Numen AI
 *
 * @example
 * ```ts
 * import { createNumenClient } from '@numen/sdk'
 *
 * const client = createNumenClient({
 *   baseUrl: 'https://api.numen.ai',
 *   apiKey: 'your-api-key',
 * })
 * ```
 */

export type { NumenClientOptions, CacheOptions } from './types/sdk.js'
export type { ApiResponse, PaginatedResponse, ApiError } from './types/api.js'

/**
 * SDK version
 */
export const SDK_VERSION = '0.1.0'

/**
 * Creates a Numen API client instance.
 * Full implementation coming in subsequent chunks.
 */
export function createNumenClient(options: import('./types/sdk.js').NumenClientOptions) {
  if (!options.baseUrl) {
    throw new Error('[numen/sdk] baseUrl is required')
  }

  // Placeholder — full client implementation in chunk 2
  return {
    _options: options,
    _version: SDK_VERSION,
  } as const
}
