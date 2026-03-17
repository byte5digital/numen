/**
 * SWR Cache edge-case tests: TTL, stale-while-revalidate, subscriptions.
 */
import { describe, it, expect, vi, beforeEach } from 'vitest'
import { SWRCache } from '../../src/core/cache.js'

describe('SWRCache — TTL & stale-while-revalidate', () => {
  it('returns stale data when TTL expired but entry exists', () => {
    vi.useFakeTimers()
    const cache = new SWRCache({ ttl: 100 })
    cache.set('key', 'value')
    vi.advanceTimersByTime(200)
    // get without revalidate still returns stale data
    expect(cache.get('key')).toBe('value')
    vi.useRealTimers()
  })

  it('triggers revalidation when TTL expired and revalidate provided', async () => {
    vi.useFakeTimers()
    const cache = new SWRCache({ ttl: 100 })
    cache.set('key', 'old-value')
    vi.advanceTimersByTime(200)

    const revalidate = vi.fn().mockResolvedValue('new-value')
    const staleResult = cache.get('key', revalidate)
    expect(staleResult).toBe('old-value') // stale data returned immediately
    expect(revalidate).toHaveBeenCalledOnce()

    // Let the revalidation complete
    vi.useRealTimers()
    await new Promise(r => setTimeout(r, 10))

    expect(cache.get('key')).toBe('new-value')
  })

  it('does not trigger revalidation when TTL not expired', () => {
    vi.useFakeTimers()
    const cache = new SWRCache({ ttl: 1000 })
    cache.set('key', 'value')
    vi.advanceTimersByTime(500)

    const revalidate = vi.fn().mockResolvedValue('new')
    cache.get('key', revalidate)
    expect(revalidate).not.toHaveBeenCalled()
    vi.useRealTimers()
  })

  it('single-flights concurrent revalidation (only one in-flight per key)', async () => {
    vi.useFakeTimers()
    const cache = new SWRCache({ ttl: 100 })
    cache.set('key', 'old')
    vi.advanceTimersByTime(200)

    let resolveRevalidation!: (v: string) => void
    const revalidate = vi.fn().mockReturnValue(
      new Promise<string>(r => { resolveRevalidation = r })
    )

    cache.get('key', revalidate)
    cache.get('key', revalidate)
    cache.get('key', revalidate)

    expect(revalidate).toHaveBeenCalledTimes(1) // single-flighted

    vi.useRealTimers()
    resolveRevalidation('refreshed')
    await new Promise(r => setTimeout(r, 10))
    expect(cache.get('key')).toBe('refreshed')
  })

  it('uses per-get TTL override', () => {
    vi.useFakeTimers()
    const cache = new SWRCache({ ttl: 10000 })
    cache.set('key', 'value')
    vi.advanceTimersByTime(500)

    const revalidate = vi.fn().mockResolvedValue('new')
    // Use a short TTL override — should trigger revalidation
    cache.get('key', revalidate, 100)
    expect(revalidate).toHaveBeenCalledOnce()
    vi.useRealTimers()
  })

  it('recovers from failed revalidation', async () => {
    vi.useFakeTimers()
    const cache = new SWRCache({ ttl: 100 })
    cache.set('key', 'original')
    vi.advanceTimersByTime(200)

    const revalidate = vi.fn().mockRejectedValue(new Error('network fail'))
    cache.get('key', revalidate)

    vi.useRealTimers()
    await new Promise(r => setTimeout(r, 10))

    // Should still have original data (not crash)
    expect(cache.get('key')).toBe('original')
  })
})

describe('SWRCache — subscriptions', () => {
  it('notifies subscribers on set()', () => {
    const cache = new SWRCache()
    const listener = vi.fn()
    cache.subscribe(listener)
    cache.set('key1', 'val1')
    expect(listener).toHaveBeenCalledOnce()
    expect(listener).toHaveBeenCalledWith('key1', expect.objectContaining({ data: 'val1' }))
  })

  it('notifies subscribers on background revalidation', async () => {
    vi.useFakeTimers()
    const cache = new SWRCache({ ttl: 100 })
    cache.set('key', 'old')
    vi.advanceTimersByTime(200)

    const listener = vi.fn()
    cache.subscribe(listener)
    listener.mockClear() // ignore the set() notification above

    cache.get('key', () => Promise.resolve('refreshed'))

    vi.useRealTimers()
    await new Promise(r => setTimeout(r, 10))

    expect(listener).toHaveBeenCalledWith('key', expect.objectContaining({ data: 'refreshed' }))
  })

  it('unsubscribes correctly', () => {
    const cache = new SWRCache()
    const listener = vi.fn()
    const unsub = cache.subscribe(listener)
    unsub()
    cache.set('key', 'val')
    expect(listener).not.toHaveBeenCalled()
  })
})

describe('SWRCache — edge cases', () => {
  it('handles empty cache gracefully', () => {
    const cache = new SWRCache()
    expect(cache.get('nonexistent')).toBeNull()
    expect(cache.size).toBe(0)
  })

  it('invalidate on nonexistent key is a no-op', () => {
    const cache = new SWRCache()
    expect(() => cache.invalidate('nope')).not.toThrow()
  })

  it('clear on empty cache is a no-op', () => {
    const cache = new SWRCache()
    expect(() => cache.clear()).not.toThrow()
  })

  it('maxSize of 1 means only the latest entry survives', () => {
    const cache = new SWRCache({ maxSize: 1 })
    cache.set('a', 1)
    cache.set('b', 2)
    expect(cache.get('a')).toBeNull()
    expect(cache.get('b')).toBe(2)
    expect(cache.size).toBe(1)
  })

  it('stores various data types', () => {
    const cache = new SWRCache()
    cache.set('string', 'hello')
    cache.set('number', 42)
    cache.set('object', { a: 1 })
    cache.set('array', [1, 2, 3])
    cache.set('null', null)
    expect(cache.get('string')).toBe('hello')
    expect(cache.get('number')).toBe(42)
    expect(cache.get('object')).toEqual({ a: 1 })
    expect(cache.get('array')).toEqual([1, 2, 3])
    expect(cache.get('null')).toBeNull() // ambiguous with "not found" — but this is how the cache works
  })
})
