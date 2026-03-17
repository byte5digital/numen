/**
 * @numen/sdk — SWR Cache
 * Zero runtime dependencies. LRU eviction + stale-while-revalidate semantics.
 */

export interface CacheEntry<T> {
  data: T
  timestamp: number
  /** In-flight revalidation promise (set during background refresh) */
  promise?: Promise<T>
}

export type CacheListener<T> = (key: string, entry: CacheEntry<T>) => void

// Internal entry that stores everything as unknown
interface InternalEntry {
  data: unknown
  timestamp: number
  promise?: Promise<unknown>
}

interface CacheOptions {
  /** Maximum number of entries before LRU eviction (default: 100) */
  maxSize?: number
  /** Default TTL in milliseconds (default: 300_000 = 5 min) */
  ttl?: number
}

/**
 * Minimal EventEmitter for cache subscriptions.
 */
class CacheEventEmitter {
  private listeners: Map<string, Set<(key: string, entry: InternalEntry) => void>> = new Map()

  on(event: string, listener: (key: string, entry: InternalEntry) => void): () => void {
    if (!this.listeners.has(event)) {
      this.listeners.set(event, new Set())
    }
    this.listeners.get(event)!.add(listener)
    return () => this.off(event, listener)
  }

  off(event: string, listener: (key: string, entry: InternalEntry) => void): void {
    this.listeners.get(event)?.delete(listener)
  }

  emit(event: string, key: string, entry: InternalEntry): void {
    this.listeners.get(event)?.forEach((fn) => fn(key, entry))
  }
}

/**
 * SWR Cache with LRU eviction.
 *
 * - `get()` returns stale data immediately and fires a background revalidation.
 * - `set()` stores data and moves the key to the front of the LRU list.
 * - `invalidate()` removes a single entry.
 * - `clear()` removes all entries.
 * - Subscribe to `update` events via `subscribe()`.
 */
export class SWRCache {
  private cache: Map<string, InternalEntry> = new Map()
  private readonly maxSize: number
  private readonly defaultTtl: number
  private emitter = new CacheEventEmitter()

  constructor(options: CacheOptions = {}) {
    this.maxSize = options.maxSize ?? 100
    this.defaultTtl = options.ttl ?? 300_000
  }

  /**
   * Retrieve a cache entry.
   * Returns `null` if no entry exists.
   * If the entry is stale and a `revalidate` function is provided,
   * triggers a background refresh and returns the stale data.
   */
  get<T>(key: string, revalidate?: () => Promise<T>, ttl?: number): T | null {
    const entry = this.cache.get(key)
    if (!entry) return null

    const effectiveTtl = ttl ?? this.defaultTtl
    const isStale = Date.now() - entry.timestamp > effectiveTtl

    // Promote to front (LRU)
    this.cache.delete(key)
    this.cache.set(key, entry)

    if (isStale && revalidate && !entry.promise) {
      // Fire background revalidation — single-flight per key
      entry.promise = revalidate()
        .then((data: T) => {
          const updated: InternalEntry = { data, timestamp: Date.now() }
          this.cache.delete(key)
          this.cache.set(key, updated)
          this.emitter.emit('update', key, updated)
          return data as unknown
        })
        .catch(() => entry.data)
        .finally(() => {
          const current = this.cache.get(key)
          if (current) current.promise = undefined
        })
    }

    return entry.data as T
  }

  /**
   * Store a value in the cache, evicting the LRU entry if maxSize is exceeded.
   */
  set<T>(key: string, data: T): CacheEntry<T> {
    if (this.cache.has(key)) {
      this.cache.delete(key)
    }

    const entry: InternalEntry = { data, timestamp: Date.now() }
    this.cache.set(key, entry)

    // Evict oldest entry if over maxSize
    if (this.cache.size > this.maxSize) {
      const oldest = this.cache.keys().next().value
      if (oldest !== undefined) {
        this.cache.delete(oldest)
      }
    }

    this.emitter.emit('update', key, entry)
    return entry as CacheEntry<T>
  }

  /**
   * Invalidate (remove) a single cache entry.
   */
  invalidate(key: string): void {
    this.cache.delete(key)
  }

  /**
   * Clear all cache entries.
   */
  clear(): void {
    this.cache.clear()
  }

  /**
   * Current number of entries.
   */
  get size(): number {
    return this.cache.size
  }

  /**
   * Subscribe to cache update events.
   * Returns an unsubscribe function.
   */
  subscribe<T>(listener: CacheListener<T>): () => void {
    const wrapper = (key: string, entry: InternalEntry) => {
      listener(key, entry as CacheEntry<T>)
    }
    return this.emitter.on('update', wrapper)
  }
}
