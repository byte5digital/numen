/**
 * Internal hook: generic SWR-style data fetching for Numen resources.
 */

import { useState, useEffect, useCallback, useRef } from 'react'

export interface UseNumenQueryResult<T> {
  data: T | undefined
  error: Error | undefined
  isLoading: boolean
  mutate: (data?: T) => void
  refetch: () => Promise<void>
}

/**
 * Generic query hook. Calls `fetcher` on mount and when `key` changes.
 */
export function useNumenQuery<T>(
  key: string | null,
  fetcher: () => Promise<T>,
  options?: { refreshInterval?: number },
): UseNumenQueryResult<T> {
  const [data, setData] = useState<T | undefined>(undefined)
  const [error, setError] = useState<Error | undefined>(undefined)
  const [isLoading, setIsLoading] = useState(key !== null)
  const mountedRef = useRef(true)
  const intervalRef = useRef<ReturnType<typeof setInterval> | null>(null)

  const fetchData = useCallback(async () => {
    if (key === null) return
    setIsLoading(true)
    setError(undefined)
    try {
      const result = await fetcher()
      if (mountedRef.current) {
        setData(result)
        setIsLoading(false)
      }
    } catch (err) {
      if (mountedRef.current) {
        setError(err instanceof Error ? err : new Error(String(err)))
        setIsLoading(false)
      }
    }
  }, [key, fetcher])

  useEffect(() => {
    mountedRef.current = true
    fetchData()

    if (options?.refreshInterval && options.refreshInterval > 0) {
      intervalRef.current = setInterval(fetchData, options.refreshInterval)
    }

    return () => {
      mountedRef.current = false
      if (intervalRef.current) clearInterval(intervalRef.current)
    }
  }, [fetchData, options?.refreshInterval])

  const mutate = useCallback((newData?: T) => {
    if (newData !== undefined) {
      setData(newData)
    } else {
      fetchData()
    }
  }, [fetchData])

  const refetch = useCallback(async () => {
    await fetchData()
  }, [fetchData])

  return { data, error, isLoading, mutate, refetch }
}
