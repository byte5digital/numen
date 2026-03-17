/**
 * Tests for SWRCache.
 */
import { describe, it, expect, vi, beforeEach } from 'vitest'
import { SWRCache } from '../../src/core/cache.js'

describe('SWRCache', () => {
  let cache: SWRCache

  beforeEach(() => {
    cache = new SWRCache({ maxSize: 3, ttl: 1000 })
  })

  describe('set / get', () => {
    it('stores and retrieves a value', () => {
      cache.set('key1', { hello: 'world' })
      const result = cache.get<{ hello: string }>('key1')
      expect(result).toEqual({ hello: 'world' })
    })

    it('returns null for a missing key', () => {
      expect(cache.get('nonexistent')).toBeNull()
    })

    it('overwrites an existing value', () => {
      cache.set('key1', 'first')
      cache.set('key1', 'second')
      expect(cache.get<string>('key1')).toBe('second')
    })
  })

  describe('LRU eviction', () => {
    it('evicts the oldest entry when maxSize is exceeded', () => {
      cache.set('a', 1)
      cache.set('b', 2)
      cache.set('c', 3)
      // a, b, c in cache (size = maxSize = 3)
      cache.set('d', 4)  // should evict 'a'
      expect(cache.get('a')).toBeNull()
      expect(cache.get('b')).toBe(2)
      expect(cache.get('c')).toBe(3)
      expect(cache.get('d')).toBe(4)
    })

    it('tracks size correctly', () => {
      cache.set('a', 1)
      cache.set('b', 2)
      expect(cache.size).toBe(2)
    })

    it('does not grow beyond maxSize', () => {
      for (let i = 0; i < 10; i++) {
        cache.set(`key${i}`, i)
      }
      expect(cache.size).toBeLessThanOrEqual(3)
    })

    it('promotes accessed key (LRU order update)', () => {
      cache.set('a', 1)
      cache.set('b', 2)
      cache.set('c', 3)
      // Access 'a' to promote it
      cache.get('a')
      // Adding 'd' should evict 'b' (oldest not recently accessed)
      cache.set('d', 4)
      expect(cache.get('a')).toBe(1)  // a was promoted, should survive
    })
  })

  describe('invalidate / clear', () => {
    it('invalidates a single entry', () => {
      cache.set('key1', 'val')
      cache.invalidate('key1')
      expect(cache.get('key1')).toBeNull()
    })

    it('clears all entries', () => {
      cache.set('a', 1)
      cache.set('b', 2)
      cache.clear()
      expect(cache.size).toBe(0)
      expect(cache.get('a')).toBeNull()
    })
  })

  describe('stale-while-revalidate', () => {
    it('returns stale data immediately and triggers background refresh', async () => {
      // Seed with a fresh entry
      cache.set('key', 'old-value')

      // Travel time forward by overriding cache entry timestamp
      const entry = (cache as unknown as { cache: Map<string, { data: unknown; timestamp: number }> }).cache.get('key')
      if (entry) entry.timestamp = Date.now() - 9999_999  // make it very stale

      const revalidate = vi.fn().mockResolvedValue('new-value')

      // Should return stale immediately
      const result = cache.get<string>('key', revalidate, 100)
      expect(result).toBe('old-value')
      expect(revalidate).toHaveBeenCalledTimes(1)

      // After revalidation resolves, the cache should have new value
      await new Promise((resolve) => setTimeout(resolve, 50))
      const updated = cache.get<string>('key')
      expect(updated).toBe('new-value')
    })

    it('does not trigger revalidation for fresh entries', () => {
      cache.set('key', 'value')
      const revalidate = vi.fn().mockResolvedValue('new-value')

      cache.get<string>('key', revalidate, 60_000)  // TTL = 60s, entry is fresh
      expect(revalidate).not.toHaveBeenCalled()
    })

    it('only triggers one revalidation per key (single-flight)', async () => {
      cache.set('key', 'old')
      const entry = (cache as unknown as { cache: Map<string, { data: unknown; timestamp: number }> }).cache.get('key')
      if (entry) entry.timestamp = 0  // stale

      const revalidate = vi.fn().mockResolvedValue('new')

      cache.get('key', revalidate, 100)
      cache.get('key', revalidate, 100)  // second call — should not trigger another revalidation
      expect(revalidate).toHaveBeenCalledTimes(1)

      await new Promise((resolve) => setTimeout(resolve, 50))
    })
  })

  describe('subscribe', () => {
    it('calls listener when a value is set', () => {
      const listener = vi.fn()
      cache.subscribe(listener)
      cache.set('key', 42)
      expect(listener).toHaveBeenCalledWith('key', expect.objectContaining({ data: 42 }))
    })

    it('returns an unsubscribe function', () => {
      const listener = vi.fn()
      const unsubscribe = cache.subscribe(listener)
      unsubscribe()
      cache.set('key', 99)
      expect(listener).not.toHaveBeenCalled()
    })
  })
})
