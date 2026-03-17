/**
 * Resource hooks for Numen React bindings.
 */

import { useCallback, useRef, useEffect, useState } from 'react'
import { useNumenClient } from './context.js'
import { useNumenQuery } from './use-numen-query.js'
import type { UseNumenQueryResult } from './use-numen-query.js'
import type { ContentItem, ContentListParams } from '../resources/content.js'
import type { Page } from '../resources/pages.js'
import type { SearchParams, SearchResponse } from '../resources/search.js'
import type { MediaAsset } from '../resources/media.js'
import type { PipelineRun } from '../resources/pipeline.js'
import type { PaginatedResponse } from '../types/api.js'

// ─── useContent ──────────────────────────────────────────────

export function useContent(id: string | null | undefined): UseNumenQueryResult<ContentItem> {
  const client = useNumenClient()
  const fetcher = useCallback(
    async () => {
      const res = await client.content.get(id!)
      return res.data
    },
    [client, id],
  )
  return useNumenQuery(id ? `content:${id}` : null, fetcher)
}

// ─── useContentList ──────────────────────────────────────────

export function useContentList(
  params?: ContentListParams,
): UseNumenQueryResult<PaginatedResponse<ContentItem>> {
  const client = useNumenClient()
  const key = `content:list:${JSON.stringify(params ?? {})}`
  const fetcher = useCallback(() => client.content.list(params), [client, params])
  return useNumenQuery(key, fetcher)
}

// ─── usePage ─────────────────────────────────────────────────

export function usePage(idOrSlug: string | null | undefined): UseNumenQueryResult<Page> {
  const client = useNumenClient()
  const fetcher = useCallback(
    async () => {
      const res = await client.pages.get(idOrSlug!)
      return res.data
    },
    [client, idOrSlug],
  )
  return useNumenQuery(idOrSlug ? `page:${idOrSlug}` : null, fetcher)
}

// ─── useSearch ───────────────────────────────────────────────

export interface UseSearchOptions {
  debounceMs?: number
  type?: string
  page?: number
  per_page?: number
}

export function useSearch(
  query: string | null | undefined,
  options?: UseSearchOptions,
): UseNumenQueryResult<SearchResponse> {
  const client = useNumenClient()
  const [debouncedQuery, setDebouncedQuery] = useState(query)
  const timerRef = useRef<ReturnType<typeof setTimeout> | null>(null)

  useEffect(() => {
    if (options?.debounceMs && options.debounceMs > 0) {
      if (timerRef.current) clearTimeout(timerRef.current)
      timerRef.current = setTimeout(() => setDebouncedQuery(query), options.debounceMs)
      return () => {
        if (timerRef.current) clearTimeout(timerRef.current)
      }
    } else {
      setDebouncedQuery(query)
    }
  }, [query, options?.debounceMs])

  const searchParams: SearchParams | undefined = debouncedQuery
    ? { q: debouncedQuery, type: options?.type, page: options?.page, per_page: options?.per_page }
    : undefined

  const key = debouncedQuery ? `search:${JSON.stringify(searchParams)}` : null
  const fetcher = useCallback(
    () => client.search.search(searchParams!),
    [client, searchParams],
  )

  return useNumenQuery(key, fetcher)
}

// ─── useMedia ────────────────────────────────────────────────

export function useMedia(id?: string | null): UseNumenQueryResult<MediaAsset | PaginatedResponse<MediaAsset>> {
  const client = useNumenClient()
  const key = id ? `media:${id}` : 'media:list'
  const fetcher = useCallback(
    async () => {
      if (id) {
        const res = await client.media.get(id)
        return res.data as MediaAsset | PaginatedResponse<MediaAsset>
      }
      return client.media.list() as Promise<MediaAsset | PaginatedResponse<MediaAsset>>
    },
    [client, id],
  )
  return useNumenQuery(key, fetcher)
}

// ─── usePipelineRun ──────────────────────────────────────────

export function usePipelineRun(
  runId: string | null | undefined,
  options?: { pollInterval?: number },
): UseNumenQueryResult<PipelineRun> {
  const client = useNumenClient()
  const [autoRefresh, setAutoRefresh] = useState(true)
  const fetcher = useCallback(
    async () => {
      const res = await client.pipeline.get(runId!)
      return res.data
    },
    [client, runId],
  )

  const result = useNumenQuery(
    runId ? `pipeline:${runId}` : null,
    fetcher,
    { refreshInterval: autoRefresh ? (options?.pollInterval ?? 3000) : undefined },
  )

  // Stop polling once pipeline completes or fails
  useEffect(() => {
    if (result.data) {
      const status = result.data.status
      if (['completed', 'failed', 'cancelled'].includes(status)) {
        setAutoRefresh(false)
      }
    }
  }, [result.data])

  return result
}

// ─── useRealtime ─────────────────────────────────────────────

export interface RealtimeEvent {
  type: string
  data: unknown
  timestamp?: string
}

export interface UseRealtimeResult {
  events: RealtimeEvent[]
  isConnected: boolean
  error: Error | undefined
}

/**
 * Skeleton for real-time updates via SSE/polling.
 * Will be fully implemented in chunk 8.
 */
export function useRealtime(_channel: string): UseRealtimeResult {
  const [events] = useState<RealtimeEvent[]>([])
  const [isConnected] = useState(false)
  const [error] = useState<Error | undefined>(undefined)

  // Skeleton — real implementation in chunk 8
  return { events, isConnected, error }
}
