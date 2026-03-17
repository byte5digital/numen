import { describe, it, expect, vi, beforeEach } from 'vitest'
import { get, type Readable } from 'svelte/store'
import {
  setNumenClient,
  getNumenClient,
  createContentStore,
  createContentListStore,
  createPageStore,
  createSearchStore,
  createMediaStore,
  createPipelineRunStore,
  createRealtimeStore,
} from '../../src/svelte/index.js'
import { _resetNumenClient } from '../../src/svelte/context.js'
import { NumenClient } from '../../src/core/client.js'

function createMockClient(): NumenClient {
  const client = new NumenClient({ baseUrl: 'https://api.test' })
  const c = client as any
  c.content = { get: vi.fn(), list: vi.fn(), create: vi.fn(), update: vi.fn(), delete: vi.fn() }
  c.pages = { get: vi.fn(), list: vi.fn(), create: vi.fn(), update: vi.fn(), delete: vi.fn(), reorder: vi.fn() }
  c.search = { search: vi.fn(), suggest: vi.fn(), ask: vi.fn() }
  c.media = { get: vi.fn(), list: vi.fn(), update: vi.fn(), delete: vi.fn() }
  c.pipeline = { get: vi.fn(), list: vi.fn(), start: vi.fn(), cancel: vi.fn(), retryStep: vi.fn() }
  return client
}

/**
 * Subscribe and wait until state.isLoading becomes false.
 */
function settled<T>(store: Readable<{ isLoading: boolean } & T>): Promise<{ isLoading: boolean } & T> {
  return new Promise((resolve) => {
    let resolved = false
    const unsub = store.subscribe((val) => {
      if (!val.isLoading && !resolved) {
        resolved = true
        // defer unsubscribe to avoid cleanup issues
        queueMicrotask(() => { unsub() })
        resolve(val)
      }
    })
  })
}

// ─── Context ─────────────────────────────────────────────────

describe('Svelte context', () => {
  beforeEach(() => _resetNumenClient())

  it('round-trips', () => {
    const c = createMockClient()
    setNumenClient(c)
    expect(getNumenClient()).toBe(c)
  })

  it('throws if not set', () => {
    expect(() => getNumenClient()).toThrow('[numen/sdk]')
  })
})

// ─── createContentStore ──────────────────────────────────────

describe('createContentStore', () => {
  let client: NumenClient
  beforeEach(() => {
    _resetNumenClient()
    client = createMockClient()
    setNumenClient(client)
  })

  it('fetches and updates', async () => {
    const item = { id: 'c1', title: 'Hello' }
    ;(client.content.get as any).mockResolvedValue({ data: item })
    const store = createContentStore('c1')
    const state = await settled(store)
    expect(state.isLoading).toBe(false)
    expect(state.data).toEqual(item)
    expect(state.error).toBeUndefined()
  })

  it('handles errors', async () => {
    ;(client.content.get as any).mockRejectedValue(new Error('fail'))
    const store = createContentStore('bad')
    const state = await settled(store)
    expect(state.error?.message).toBe('fail')
    expect(state.data).toBeUndefined()
  })

  it('refresh re-fetches', async () => {
    ;(client.content.get as any)
      .mockResolvedValueOnce({ data: { id: 'c1', v: 1 } })
      .mockResolvedValueOnce({ data: { id: 'c1', v: 2 } })
    const store = createContentStore('c1')
    await settled(store)
    expect(get(store).data).toEqual({ id: 'c1', v: 1 })
    const p = store.refresh()
    const state2 = await settled(store)
    await p
    expect(state2.data).toEqual({ id: 'c1', v: 2 })
  })
})

// ─── createContentListStore ──────────────────────────────────

describe('createContentListStore', () => {
  let client: NumenClient
  beforeEach(() => {
    _resetNumenClient()
    client = createMockClient()
    setNumenClient(client)
  })

  it('fetches list', async () => {
    const list = { data: [{ id: 'c1' }], meta: {} }
    ;(client.content.list as any).mockResolvedValue(list)
    const store = createContentListStore()
    const state = await settled(store)
    expect(state.data).toEqual(list)
  })

  it('passes params', async () => {
    ;(client.content.list as any).mockResolvedValue({ data: [] })
    const store = createContentListStore({ page: 2 })
    await settled(store)
    expect(client.content.list).toHaveBeenCalledWith({ page: 2 })
  })
})

// ─── createPageStore ─────────────────────────────────────────

describe('createPageStore', () => {
  let client: NumenClient
  beforeEach(() => {
    _resetNumenClient()
    client = createMockClient()
    setNumenClient(client)
  })

  it('fetches page', async () => {
    ;(client.pages.get as any).mockResolvedValue({ data: { id: 'p1' } })
    const store = createPageStore('about')
    const state = await settled(store)
    expect(state.data).toEqual({ id: 'p1' })
    expect(client.pages.get).toHaveBeenCalledWith('about')
  })
})

// ─── createSearchStore ───────────────────────────────────────

describe('createSearchStore', () => {
  let client: NumenClient
  beforeEach(() => {
    _resetNumenClient()
    client = createMockClient()
    setNumenClient(client)
  })

  it('initial search', async () => {
    const res = { data: [{ id: 'r1' }] }
    ;(client.search.search as any).mockResolvedValue(res)
    const store = createSearchStore('hello')
    const state = await settled(store)
    expect(state.data).toEqual(res)
    expect(client.search.search).toHaveBeenCalledWith(
      expect.objectContaining({ q: 'hello' }),
    )
  })

  it('search() updates query', async () => {
    ;(client.search.search as any)
      .mockResolvedValueOnce({ data: [{ id: 'r1' }] })
      .mockResolvedValueOnce({ data: [{ id: 'r2' }] })
    const store = createSearchStore('first')
    await settled(store)
    store.search('second')
    const state = await settled(store)
    expect(state.data).toEqual({ data: [{ id: 'r2' }] })
  })

  it('debounces', async () => {
    vi.useFakeTimers()
    ;(client.search.search as any).mockResolvedValue({ data: [] })
    const store = createSearchStore('x', { debounceMs: 200 })
    await vi.advanceTimersByTimeAsync(50)
    const calls = (client.search.search as any).mock.calls.length
    store.search('a')
    store.search('ab')
    store.search('abc')
    expect((client.search.search as any).mock.calls.length).toBe(calls)
    await vi.advanceTimersByTimeAsync(250)
    expect((client.search.search as any).mock.calls.length).toBe(calls + 1)
    expect(client.search.search).toHaveBeenLastCalledWith(
      expect.objectContaining({ q: 'abc' }),
    )
    vi.useRealTimers()
  })
})

// ─── createMediaStore ────────────────────────────────────────

describe('createMediaStore', () => {
  let client: NumenClient
  beforeEach(() => {
    _resetNumenClient()
    client = createMockClient()
    setNumenClient(client)
  })

  it('single by id', async () => {
    ;(client.media.get as any).mockResolvedValue({ data: { id: 'm1' } })
    const store = createMediaStore('m1')
    const state = await settled(store)
    expect(state.data).toEqual({ id: 'm1' })
  })

  it('list when no id', async () => {
    ;(client.media.list as any).mockResolvedValue({ data: [{ id: 'm1' }] })
    const store = createMediaStore()
    const state = await settled(store)
    expect(state.data).toEqual({ data: [{ id: 'm1' }] })
  })
})

// ─── createPipelineRunStore ──────────────────────────────────

describe('createPipelineRunStore', () => {
  let client: NumenClient
  beforeEach(() => {
    _resetNumenClient()
    client = createMockClient()
    setNumenClient(client)
  })

  it('fetches run', async () => {
    const run = { id: 'r1', status: 'running', steps: [] }
    ;(client.pipeline.get as any).mockResolvedValue({ data: run })
    const store = createPipelineRunStore('r1')
    const state = await settled(store)
    expect(state.data).toEqual(run)
  })

  it('stops polling on completion', async () => {
    let callCount = 0
    ;(client.pipeline.get as any).mockImplementation(() => {
      callCount++
      if (callCount === 1) {
        return Promise.resolve({ data: { id: 'r1', status: 'running' } })
      }
      return Promise.resolve({ data: { id: 'r1', status: 'completed' } })
    })

    const store = createPipelineRunStore('r1', { pollInterval: 50 })

    // Keep a persistent subscriber so polling stays alive
    const values: any[] = []
    const unsub = store.subscribe((v) => values.push(v))

    // Wait for initial fetch + poll
    await new Promise((r) => setTimeout(r, 200))

    // Should have transitioned to completed
    const lastVal = values[values.length - 1]
    expect(lastVal.data?.status).toBe('completed')

    // Verify polling stopped
    const currentCalls = callCount
    await new Promise((r) => setTimeout(r, 200))
    expect(callCount).toBe(currentCalls)

    unsub()
  })
})

// ─── createRealtimeStore ─────────────────────────────────────

describe('createRealtimeStore', () => {
  beforeEach(() => {
    _resetNumenClient()
    setNumenClient(createMockClient())
  })

  it('skeleton state', () => {
    const store = createRealtimeStore('ch')
    const s = get(store)
    expect(s.events).toEqual([])
    expect(s.isConnected).toBe(false)
    expect(s.error).toBeUndefined()
  })
})
