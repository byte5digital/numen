/**
 * @numen/sdk - Typed Frontend SDK for Numen AI
 *
 * @example
 * ```ts
 * import { NumenClient } from '@numen/sdk'
 *
 * const client = new NumenClient({
 *   baseUrl: 'https://api.numen.ai',
 *   apiKey: 'your-api-key',
 * })
 * ```
 */

import { NumenClient } from './core/client.js'
import type { NumenClientOptions } from './types/sdk.js'

// Types
export type { NumenClientOptions, CacheOptions } from './types/sdk.js'
export type { ApiResponse, PaginatedResponse, ApiError } from './types/api.js'

// Core client
export { NumenClient } from './core/client.js'
export type { RequestOptions } from './core/client.js'

// Auth
export { createAuthMiddleware } from './core/auth.js'
export type { AuthMiddlewareOptions } from './core/auth.js'

// Errors
export {
  NumenError,
  NumenRateLimitError,
  NumenValidationError,
  NumenAuthError,
  NumenNotFoundError,
  NumenNetworkError,
  mapResponseToError,
} from './core/errors.js'

// Cache
export { SWRCache } from './core/cache.js'
export type { CacheEntry, CacheListener } from './core/cache.js'

// Resources
export {
  ContentResource,
  PagesResource,
  MediaResource,
  SearchResource,
  VersionsResource,
  TaxonomiesResource,
} from './resources/index.js'

export type {
  ContentItem,
  ContentListParams,
  ContentCreatePayload,
  ContentUpdatePayload,
  Page,
  PageListParams,
  PageCreatePayload,
  PageUpdatePayload,
  PageReorderPayload,
  MediaAsset,
  MediaListParams,
  MediaUpdatePayload,
  SearchParams,
  SearchResult,
  SearchResponse,
  SuggestResponse,
  AskPayload,
  AskResponse,
  ContentVersion,
  VersionListParams,
  VersionDiff,
  Taxonomy,
  TaxonomyTerm,
  TaxonomyCreatePayload,
  TaxonomyUpdatePayload,
  TermCreatePayload,
  TermUpdatePayload,
} from './resources/index.js'

/**
 * SDK version
 */
export const SDK_VERSION = '0.1.0'

/**
 * Creates a Numen API client instance.
 * Returns a NumenClient augmented with legacy properties for backward compatibility.
 */
export function createNumenClient(options: NumenClientOptions) {
  const client = new NumenClient(options)
  // Backward-compat shape from chunk 1
  return Object.assign(client, {
    _options: options,
    _version: SDK_VERSION,
  })
}
