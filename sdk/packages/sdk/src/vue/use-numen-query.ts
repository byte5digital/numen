/**
 * Internal composable: generic SWR-style data fetching for Numen resources (Vue 3).
 */

import { ref, watch, onUnmounted, type Ref, isRef, unref } from 'vue'

export interface UseNumenQueryResult<T> {
  data: Ref<T | undefined>
  error: Ref<Error | null>
  isLoading: Ref<boolean>
  refetch: () => Promise<void>
}

/**
 * Generic query composable. Calls `fetcher` on mount and when `key` changes.
 */
export function useNumenQuery<T>(
  key: Ref<string | null> | (() => string | null),
  fetcher: () => Promise<T>,
  options?: { refreshInterval?: number },
): UseNumenQueryResult<T> {
  const data = ref<T | undefined>(undefined) as Ref<T | undefined>
  const error = ref<Error | null>(null)
  const isLoading = ref(false)
  let intervalId: ReturnType<typeof setInterval> | null = null
  let mounted = true

  const fetchData = async () => {
    const currentKey = typeof key === 'function' ? key() : unref(key)
    if (currentKey === null) {
      isLoading.value = false
      return
    }
    isLoading.value = true
    error.value = null
    try {
      const result = await fetcher()
      if (mounted) {
        data.value = result
        isLoading.value = false
      }
    } catch (err) {
      if (mounted) {
        error.value = err instanceof Error ? err : new Error(String(err))
        isLoading.value = false
      }
    }
  }

  const setupInterval = () => {
    clearExistingInterval()
    if (options?.refreshInterval && options.refreshInterval > 0) {
      intervalId = setInterval(fetchData, options.refreshInterval)
    }
  }

  const clearExistingInterval = () => {
    if (intervalId) {
      clearInterval(intervalId)
      intervalId = null
    }
  }

  // Watch key changes and refetch
  if (isRef(key)) {
    watch(key, () => {
      fetchData()
      setupInterval()
    }, { immediate: true })
  } else {
    // key is a getter function — use it as watch source
    watch(key, () => {
      fetchData()
      setupInterval()
    }, { immediate: true })
  }

  onUnmounted(() => {
    mounted = false
    clearExistingInterval()
  })

  const refetch = async () => {
    await fetchData()
  }

  return { data, error, isLoading, refetch }
}
