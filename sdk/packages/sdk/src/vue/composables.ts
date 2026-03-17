/**
 * Resource composables for Numen Vue 3 bindings.
 */

import { ref, watch, computed, onUnmounted, type Ref, toValue, isRef } from 'vue'
import { useNumenClient } from './plugin.js'
import { useNumenQuery } from './use-numen-query.js'
import type { UseNumenQueryResult } from './use-numen-query.js'
import type { ContentItem, ContentListParams } from '../resources/content.js'
import type { Page } from '../resources/pages.js'
import type { SearchParams, SearchResponse } from '../resources/search.js'
import type { MediaAsset } from '../resources/media.js'
import type { PipelineRun } from '../resources/pipeline.js'
import type { PaginatedResponse } from '../types/api.js'
import type { RealtimeEvent } from '../realtime/client.js'

// ─── useContent ──────────────────────────────────────────────

export function useContent(id: Ref<string | null | undefined> | string | null | undefined): UseNumenQueryResult<ContentItem> {
  const client = useNumenClient()
  const key = computed(() => {
    const val = toValue(id)
    return val ? `content:${val}` : null
  })
  const fetcher = async () => {
    const val = toValue(id)!
    const res = await client.content.get(val)
    return res.data
  }
  return useNumenQuery(key, fetcher)
}

// ─── useContentList ──────────────────────────────────────────

export function useContentList(
  params?: Ref<ContentListParams | undefined> | ContentListParams,
): UseNumenQueryResult<PaginatedResponse<ContentItem>> {
  const client = useNumenClient()
  const key = computed(() => `content:list:${JSON.stringify(toValue(params) ?? {})}`)
  const fetcher = async () => {
    const p = toValue(params)
    return client.content.list(p)
  }
  return useNumenQuery(key, fetcher)
}

// ─── usePage ─────────────────────────────────────────────────

export function usePage(idOrSlug: Ref<string | null | undefined> | string | null | undefined): UseNumenQueryResult<Page> {
  const client = useNumenClient()
  const key = computed(() => {
    const val = toValue(idOrSlug)
    return val ? `page:${val}` : null
  })
  const fetcher = async () => {
    const val = toValue(idOrSlug)!
    const res = await client.pages.get(val)
    return res.data
  }
  return useNumenQuery(key, fetcher)
}

// ─── useSearch ───────────────────────────────────────────────

export interface UseSearchOptions {
  debounceMs?: number
  type?: string
  page?: number
  per_page?: number
}

export function useSearch(
  query: Ref<string | null | undefined> | string | null | undefined,
  options?: UseSearchOptions,
): UseNumenQueryResult<SearchResponse> {
  const client = useNumenClient()
  const debouncedQuery = ref(toValue(query))
  let timerId: ReturnType<typeof setTimeout> | null = null

  // Watch for query changes with optional debounce
  if (isRef(query)) {
    watch(query, (newQuery) => {
      if (options?.debounceMs && options.debounceMs > 0) {
        if (timerId) clearTimeout(timerId)
        timerId = setTimeout(() => {
          debouncedQuery.value = newQuery
        }, options.debounceMs)
      } else {
        debouncedQuery.value = newQuery
      }
    })
  }

  onUnmounted(() => {
    if (timerId) clearTimeout(timerId)
  })

  const key = computed(() => {
    const q = debouncedQuery.value
    return q ? `search:${JSON.stringify({ q, type: options?.type, page: options?.page, per_page: options?.per_page })}` : null
  })

  const fetcher = async () => {
    const q = debouncedQuery.value!
    const searchParams: SearchParams = {
      q,
      type: options?.type,
      page: options?.page,
      per_page: options?.per_page,
    }
    return client.search.search(searchParams)
  }

  return useNumenQuery(key, fetcher)
}

// ─── useMedia ────────────────────────────────────────────────

export function useMedia(id?: Ref<string | null | undefined> | string | null): UseNumenQueryResult<MediaAsset | PaginatedResponse<MediaAsset>> {
  const client = useNumenClient()
  const key = computed(() => {
    const val = id ? toValue(id) : undefined
    return val ? `media:${val}` : 'media:list'
  })
  const fetcher = async (): Promise<MediaAsset | PaginatedResponse<MediaAsset>> => {
    const val = id ? toValue(id) : undefined
    if (val) {
      const res = await client.media.get(val)
      return res.data as MediaAsset | PaginatedResponse<MediaAsset>
    }
    return client.media.list() as Promise<MediaAsset | PaginatedResponse<MediaAsset>>
  }
  return useNumenQuery(key, fetcher)
}

// ─── usePipelineRun ──────────────────────────────────────────

export function usePipelineRun(
  runId: Ref<string | null | undefined> | string | null | undefined,
  options?: { pollInterval?: number },
): UseNumenQueryResult<PipelineRun> {
  const client = useNumenClient()
  const autoRefresh = ref(true)
  const key = computed(() => {
    const val = toValue(runId)
    return val ? `pipeline:${val}` : null
  })
  const fetcher = async () => {
    const val = toValue(runId)!
    const res = await client.pipeline.get(val)
    return res.data
  }

  const refreshInterval = computed(() =>
    autoRefresh.value ? (options?.pollInterval ?? 3000) : undefined
  )

  const result = useNumenQuery(key, fetcher, {
    refreshInterval: refreshInterval.value,
  })

  // Stop polling once pipeline completes or fails
  watch(result.data, (data) => {
    if (data) {
      const status = data.status
      if (['completed', 'failed', 'cancelled'].includes(status)) {
        autoRefresh.value = false
      }
    }
  })

  return result
}

// ─── useRealtime ─────────────────────────────────────────────

export type { RealtimeEvent } from '../realtime/client.js'

export interface UseRealtimeResult {
  events: Ref<RealtimeEvent[]>
  isConnected: Ref<boolean>
  error: Ref<Error | null>
}

/**
 * Subscribe to a realtime channel via SSE with polling fallback.
 *
 * @param channel - Channel name (e.g., 'content.abc123', 'pipeline.xyz')
 */
export function useRealtime(channel: Ref<string | null | undefined> | string | null | undefined): UseRealtimeResult {
  const client = useNumenClient()
  const events = ref<RealtimeEvent[]>([])
  const isConnected = ref(false)
  const error = ref<Error | null>(null)

  let unsub: (() => void) | null = null

  const setupSubscription = (ch: string | null | undefined) => {
    // Clean up previous subscription
    if (unsub) {
      unsub()
      unsub = null
    }

    if (!ch) {
      isConnected.value = false
      events.value = []
      error.value = null
      return
    }

    unsub = client.realtime.subscribe(ch, (event) => {
      events.value = [...events.value, event]
    })
    isConnected.value = true
    error.value = null
  }

  // Watch for reactive channel changes
  if (isRef(channel)) {
    watch(channel, (newChannel) => {
      setupSubscription(newChannel)
    }, { immediate: true })
  } else {
    setupSubscription(channel)
  }

  onUnmounted(() => {
    if (unsub) {
      unsub()
      unsub = null
    }
    isConnected.value = false
  })

  return { events, isConnected, error }
}
