// @ts-nocheck
import { describe, it, expect, vi } from "vitest"
import { createElement } from "react"
import { renderHook, waitFor, act } from "@testing-library/react"
import { NumenProvider } from "../../src/react/context.js"
import { useContent, useContentList, usePage, useSearch, useMedia, usePipelineRun, useRealtime } from "../../src/react/hooks.js"
import { NumenClient } from "../../src/core/client.js"

function mc() {
  const c = new NumenClient({ baseUrl: "https://api.test" }) as any
  c.content = { get: vi.fn(), list: vi.fn() }
  c.pages = { get: vi.fn(), list: vi.fn() }
  c.search = { search: vi.fn(), suggest: vi.fn(), ask: vi.fn() }
  c.media = { get: vi.fn(), list: vi.fn() }
  c.pipeline = { get: vi.fn(), list: vi.fn() }
  c.realtime = { subscribe: vi.fn(() => vi.fn()), unsubscribe: vi.fn(), disconnectAll: vi.fn(), getChannelState: vi.fn(() => "disconnected"), getActiveChannels: vi.fn(() => []), setToken: vi.fn() }
  return c as NumenClient
}
function w(c: NumenClient) { return ({ children }: { children: React.ReactNode }) => createElement(NumenProvider, { client: c }, children) }

describe('Hook cleanup on unmount', () => {
  it('useContent does not update state after unmount', async () => {
    const client = mc()
    let res!: (v: unknown) => void
    ;(client.content.get as any).mockReturnValue(new Promise(r => { res = r }))
    const { result, unmount } = renderHook(() => useContent('c1'), { wrapper: w(client) })
    expect(result.current.isLoading).toBe(true)
    unmount()
    res({ data: { id: 'c1', title: 'Test' } })
  })

  it('usePipelineRun cleans up interval on unmount', async () => {
    const client = mc()
    ;(client.pipeline.get as any).mockResolvedValue({ data: { id: 'r1', status: 'running' } })
    const { unmount } = renderHook(() => usePipelineRun('r1', { pollInterval: 100 }), { wrapper: w(client) })
    await waitFor(() => expect(client.pipeline.get).toHaveBeenCalled())
    unmount()
    const cc = (client.pipeline.get as any).mock.calls.length
    await new Promise(r => setTimeout(r, 250))
    expect((client.pipeline.get as any).mock.calls.length).toBe(cc)
  })

  it('useRealtime unsubscribes on unmount', () => {
    const client = mc()
    const unsub = vi.fn()
    ;(client.realtime.subscribe as any).mockReturnValue(unsub)
    const { unmount } = renderHook(() => useRealtime('ch1'), { wrapper: w(client) })
    unmount()
    expect(unsub).toHaveBeenCalled()
  })

  it('useRealtime cleans up when channel becomes null', () => {
    const client = mc()
    ;(client.realtime.subscribe as any).mockReturnValue(vi.fn())
    const { result, rerender } = renderHook(
      ({ ch }) => useRealtime(ch),
      { wrapper: w(client), initialProps: { ch: 'ch1' as string | null } }
    )
    expect(result.current.isConnected).toBe(true)
    rerender({ ch: null })
    expect(result.current.isConnected).toBe(false)
  })
})

describe('Loading state transitions', () => {
  it('useContent loading to loaded', async () => {
    const client = mc()
    ;(client.content.get as any).mockResolvedValue({ data: { id: 'c1', title: 'Hello' } })
    const { result } = renderHook(() => useContent('c1'), { wrapper: w(client) })
    expect(result.current.isLoading).toBe(true)
    await waitFor(() => expect(result.current.isLoading).toBe(false))
    expect(result.current.data).toEqual({ id: 'c1', title: 'Hello' })
  })

  it('useContent loading to error', async () => {
    const client = mc()
    ;(client.content.get as any).mockRejectedValue(new Error('Network fail'))
    const { result } = renderHook(() => useContent('bad'), { wrapper: w(client) })
    await waitFor(() => expect(result.current.isLoading).toBe(false))
    expect(result.current.error?.message).toBe('Network fail')
  })

  it('useContentList loading transition', async () => {
    const client = mc()
    const data = { data: [{ id: '1' }], meta: { total: 1, page: 1, perPage: 10, lastPage: 1 } }
    ;(client.content.list as any).mockResolvedValue(data)
    const { result } = renderHook(() => useContentList(), { wrapper: w(client) })
    await waitFor(() => expect(result.current.isLoading).toBe(false))
    expect(result.current.data).toEqual(data)
  })

  it('usePage loading to loaded', async () => {
    const client = mc()
    ;(client.pages.get as any).mockResolvedValue({ data: { id: 'p1', slug: 'home' } })
    const { result } = renderHook(() => usePage('home'), { wrapper: w(client) })
    await waitFor(() => expect(result.current.isLoading).toBe(false))
    expect(result.current.data?.slug).toBe('home')
  })

  it('null id means no loading', () => {
    const client = mc()
    const { result } = renderHook(() => usePage(null), { wrapper: w(client) })
    expect(result.current.isLoading).toBe(false)
    expect(client.pages.get).not.toHaveBeenCalled()
  })
})

describe('mutate and refetch', () => {
  it('mutate updates data optimistically', async () => {
    const client = mc()
    ;(client.content.get as any).mockResolvedValue({ data: { id: 'c1', title: 'Orig' } })
    const { result } = renderHook(() => useContent('c1'), { wrapper: w(client) })
    await waitFor(() => expect(result.current.data).toBeDefined())
    act(() => { result.current.mutate({ id: 'c1', title: 'New' } as any) })
    expect(result.current.data?.title).toBe('New')
  })

  it('refetch re-fetches', async () => {
    const client = mc()
    let n = 0
    ;(client.content.get as any).mockImplementation(() => { n++; return Promise.resolve({ data: { id: 'c1', title: 'V' + n } }) })
    const { result } = renderHook(() => useContent('c1'), { wrapper: w(client) })
    await waitFor(() => expect(result.current.data).toBeDefined())
    await act(async () => { await result.current.refetch() })
    expect(result.current.data?.title).toBe('V2')
  })
})

describe('useSearch edge cases', () => {
  it('no fetch when query is null', () => {
    const client = mc()
    const { result } = renderHook(() => useSearch(null), { wrapper: w(client) })
    expect(result.current.isLoading).toBe(false)
    expect(client.search.search).not.toHaveBeenCalled()
  })

  it('fetches when query provided', async () => {
    const client = mc()
    const d = { data: [], meta: { total: 0, page: 1, perPage: 10, query: 'test' } }
    ;(client.search.search as any).mockResolvedValue(d)
    const { result } = renderHook(() => useSearch('test'), { wrapper: w(client) })
    await waitFor(() => expect(result.current.isLoading).toBe(false))
    expect(result.current.data).toEqual(d)
  })
})

describe('useMedia edge cases', () => {
  it('fetches single asset by id', async () => {
    const client = mc()
    ;(client.media.get as any).mockResolvedValue({ data: { id: 'm1', filename: 'test.jpg' } })
    const { result } = renderHook(() => useMedia('m1'), { wrapper: w(client) })
    await waitFor(() => expect(result.current.isLoading).toBe(false))
    expect(result.current.data).toEqual({ id: 'm1', filename: 'test.jpg' })
  })

  it('fetches list when no id', async () => {
    const client = mc()
    const d = { data: [], meta: { total: 0, page: 1, perPage: 10, lastPage: 1 } }
    ;(client.media.list as any).mockResolvedValue(d)
    const { result } = renderHook(() => useMedia(), { wrapper: w(client) })
    await waitFor(() => expect(result.current.isLoading).toBe(false))
    expect(result.current.data).toEqual(d)
  })
})

describe('usePipelineRun auto-stop', () => {
  it('stops polling when completed', async () => {
    const client = mc()
    let n = 0
    ;(client.pipeline.get as any).mockImplementation(() => {
      n++
      return Promise.resolve({ data: { id: 'r1', status: n >= 2 ? 'completed' : 'running' } })
    })
    const { result } = renderHook(() => usePipelineRun('r1', { pollInterval: 100 }), { wrapper: w(client) })
    await waitFor(() => expect(result.current.data?.status).toBe('completed'), { timeout: 2000 })
    const fc = (client.pipeline.get as any).mock.calls.length
    await new Promise(r => setTimeout(r, 300))
    expect((client.pipeline.get as any).mock.calls.length).toBeLessThanOrEqual(fc + 1)
  })
})

describe('useRealtime events', () => {
  it('accumulates events from subscription', () => {
    const client = mc()
    let cb: ((e: any) => void) | null = null
    ;(client.realtime.subscribe as any).mockImplementation((_: string, fn: any) => { cb = fn; return vi.fn() })
    const { result } = renderHook(() => useRealtime('ch1'), { wrapper: w(client) })
    act(() => { cb!({ type: 'updated', data: {}, timestamp: Date.now() }) })
    expect(result.current.events).toHaveLength(1)
    act(() => { cb!({ type: 'published', data: {}, timestamp: Date.now() }) })
    expect(result.current.events).toHaveLength(2)
  })
})
