/**
 * Svelte stores for Numen SDK resources.
 */

import { writable, type Readable } from 'svelte/store'
import { getNumenClient } from './context.js'
import type { ContentItem, ContentListParams } from '../resources/content.js'
import type { Page } from '../resources/pages.js'
import type { SearchParams, SearchResponse } from '../resources/search.js'
import type { MediaAsset } from '../resources/media.js'
import type { PipelineRun } from '../resources/pipeline.js'
import type { PaginatedResponse } from '../types/api.js'

// ─── Shared types ────────────────────────────────────────────

export interface NumenStoreState<T> {
  data: T | undefined
  error: Error | undefined
  isLoading: boolean
}

export interface NumenStore<T> extends Readable<NumenStoreState<T>> {
  refresh: () => Promise<void>
}

// ─── Store factory helper ────────────────────────────────────

function createNumenStore<T>(
  fetcher: () => Promise<T>,
  options?: { refreshInterval?: number; autoFetch?: boolean },
): NumenStore<T> {
  const internal = writable<NumenStoreState<T>>({
    data: undefined,
    error: undefined,
    isLoading: options?.autoFetch !== false,
  })

  let intervalId: ReturnType<typeof setInterval> | null = null

  const fetchData = async () => {
    internal.update((s) => ({ ...s, isLoading: true, error: undefined }))
    try {
      const result = await fetcher()
      internal.set({ data: result, error: undefined, isLoading: false })
    } catch (err) {
      internal.set({
        data: undefined,
        error: err instanceof Error ? err : new Error(String(err)),
        isLoading: false,
      })
    }
  }

  if (options?.autoFetch !== false) {
    fetchData()
  }

  if (options?.refreshInterval && options.refreshInterval > 0) {
    intervalId = setInterval(fetchData, options.refreshInterval)
  }

  let subscriberCount = 0

  const store: NumenStore<T> = {
    subscribe(run, invalidate?) {
      subscriberCount++
      const unsub = internal.subscribe(run, invalidate)
      return () => {
        subscriberCount--
        unsub()
        if (subscriberCount === 0 && intervalId) {
          clearInterval(intervalId)
          intervalId = null
        }
      }
    },
    refresh: fetchData,
  }

  return store
}

// ─── createContentStore ──────────────────────────────────────

export function createContentStore(id: string): NumenStore<ContentItem> {
  const client = getNumenClient()
  return createNumenStore(async () => {
    const res = await client.content.get(id)
    return res.data
  })
}

// ─── createContentListStore ──────────────────────────────────

export function createContentListStore(
  params?: ContentListParams,
): NumenStore<PaginatedResponse<ContentItem>> {
  const client = getNumenClient()
  return createNumenStore(() => client.content.list(params))
}

// ─── createPageStore ─────────────────────────────────────────

export function createPageStore(idOrSlug: string): NumenStore<Page> {
  const client = getNumenClient()
  return createNumenStore(async () => {
    const res = await client.pages.get(idOrSlug)
    return res.data
  })
}

// ─── createSearchStore ───────────────────────────────────────

export interface CreateSearchStoreOptions {
  debounceMs?: number
  type?: string
  page?: number
  per_page?: number
}

export function createSearchStore(
  query: string,
  options?: CreateSearchStoreOptions,
): NumenStore<SearchResponse> & { search: (newQuery: string) => void } {
  const client = getNumenClient()
  let currentQuery = query
  let debounceTimer: ReturnType<typeof setTimeout> | null = null

  const internal = writable<NumenStoreState<SearchResponse>>({
    data: undefined,
    error: undefined,
    isLoading: true,
  })

  const fetchData = async () => {
    if (!currentQuery) {
      internal.set({ data: undefined, error: undefined, isLoading: false })
      return
    }
    internal.update((s) => ({ ...s, isLoading: true, error: undefined }))
    try {
      const searchParams: SearchParams = {
        q: currentQuery,
        type: options?.type,
        page: options?.page,
        per_page: options?.per_page,
      }
      const result = await client.search.search(searchParams)
      internal.set({ data: result, error: undefined, isLoading: false })
    } catch (err) {
      internal.set({
        data: undefined,
        error: err instanceof Error ? err : new Error(String(err)),
        isLoading: false,
      })
    }
  }

  fetchData()

  const search = (newQuery: string) => {
    currentQuery = newQuery
    if (options?.debounceMs && options.debounceMs > 0) {
      if (debounceTimer) clearTimeout(debounceTimer)
      debounceTimer = setTimeout(fetchData, options.debounceMs)
    } else {
      fetchData()
    }
  }

  return {
    subscribe: internal.subscribe,
    refresh: fetchData,
    search,
  }
}

// ─── createMediaStore ────────────────────────────────────────

export function createMediaStore(
  id?: string,
): NumenStore<MediaAsset | PaginatedResponse<MediaAsset>> {
  const client = getNumenClient()
  return createNumenStore(async (): Promise<MediaAsset | PaginatedResponse<MediaAsset>> => {
    if (id) {
      const res = await client.media.get(id)
      return res.data as MediaAsset | PaginatedResponse<MediaAsset>
    }
    return client.media.list() as Promise<MediaAsset | PaginatedResponse<MediaAsset>>
  })
}

// ─── createPipelineRunStore ──────────────────────────────────

export function createPipelineRunStore(
  runId: string,
  options?: { pollInterval?: number },
): NumenStore<PipelineRun> {
  const client = getNumenClient()
  const pollMs = options?.pollInterval ?? 3000

  const internal = writable<NumenStoreState<PipelineRun>>({
    data: undefined,
    error: undefined,
    isLoading: true,
  })

  let intervalId: ReturnType<typeof setInterval> | null = null

  const fetchData = async () => {
    internal.update((s) => ({ ...s, isLoading: true, error: undefined }))
    try {
      const res = await client.pipeline.get(runId)
      const run = res.data
      internal.set({ data: run, error: undefined, isLoading: false })
      if (['completed', 'failed', 'cancelled'].includes(run.status) && intervalId) {
        clearInterval(intervalId)
        intervalId = null
      }
    } catch (err) {
      internal.set({
        data: undefined,
        error: err instanceof Error ? err : new Error(String(err)),
        isLoading: false,
      })
    }
  }

  fetchData()
  intervalId = setInterval(fetchData, pollMs)

  let subscriberCount = 0

  return {
    subscribe(run, invalidate?) {
      subscriberCount++
      const unsub = internal.subscribe(run, invalidate)
      return () => {
        subscriberCount--
        unsub()
        if (subscriberCount === 0 && intervalId) {
          clearInterval(intervalId)
          intervalId = null
        }
      }
    },
    refresh: fetchData,
  }
}

// ─── createRealtimeStore ─────────────────────────────────────

export interface RealtimeEvent {
  type: string
  data: unknown
  timestamp?: string
}

export interface RealtimeStoreState {
  events: RealtimeEvent[]
  isConnected: boolean
  error: Error | undefined
}

export type RealtimeStore = Readable<RealtimeStoreState>

/**
 * Skeleton for real-time updates via SSE/polling.
 * Will be fully implemented in a later chunk.
 */
export function createRealtimeStore(_channel: string): RealtimeStore {
  const { subscribe } = writable<RealtimeStoreState>({
    events: [],
    isConnected: false,
    error: undefined,
  })

  return { subscribe }
}
