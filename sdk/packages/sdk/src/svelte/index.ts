/**
 * Svelte bindings for Numen SDK.
 *
 * @example
 * ```ts
 * import { setNumenClient, createContentStore } from '@numen/sdk/svelte'
 * import { NumenClient } from '@numen/sdk'
 *
 * const client = new NumenClient({ baseUrl: '...', apiKey: '...' })
 * setNumenClient(client)
 *
 * const content = createContentStore('article-id')
 * // In Svelte: $content.data, $content.isLoading, $content.error
 * ```
 */

export { setNumenClient, getNumenClient } from './context.js'
export {
  createContentStore,
  createContentListStore,
  createPageStore,
  createSearchStore,
  createMediaStore,
  createPipelineRunStore,
  createRealtimeStore,
} from './stores.js'
export type {
  NumenStore,
  NumenStoreState,
  CreateSearchStoreOptions,
  RealtimeEvent,
  RealtimeStoreState,
  RealtimeStore,
} from './stores.js'
