/**
 * @numen/sdk/vue — Vue 3 bindings for Numen SDK
 */

// Plugin & context
export { NumenPlugin, useNumenClient, NumenClientKey } from './plugin.js'
export type { NumenPluginOptions } from './plugin.js'

// Internal query composable
export { useNumenQuery } from './use-numen-query.js'
export type { UseNumenQueryResult } from './use-numen-query.js'

// Resource composables
export {
  useContent,
  useContentList,
  usePage,
  useSearch,
  useMedia,
  usePipelineRun,
  useRealtime,
} from './composables.js'
export type { UseSearchOptions, RealtimeEvent, UseRealtimeResult } from './composables.js'
