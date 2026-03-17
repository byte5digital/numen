import { describe, it, expect, vi } from 'vitest'
import { createElement } from 'react'
import { renderHook, waitFor, act } from '@testing-library/react'
import { NumenProvider, useNumenClient } from '../../src/react/context.js'
import {
  useContent,
  useContentList,
  usePage,
  useSearch,
  useMedia,
  usePipelineRun,
  useRealtime,
} from '../../src/react/hooks.js'
import { NumenClient } from '../../src/core/client.js'

function createMockClient() {
  const client = new NumenClient({ baseUrl: 'https://api.test' })
  client.content = { get: vi.fn(), list: vi.fn(), create: vi.fn(), update: vi.fn(), delete: vi.fn() } as any
  client.pages = { get: vi.fn(), list: vi.fn(), create: vi.fn(), update: vi.fn(), delete: vi.fn(), reorder: vi.fn() } as any
  client.search = { search: vi.fn(), suggest: vi.fn(), ask: vi.fn() } as any
  client.media = { get: vi.fn(), list: vi.fn(), update: vi.fn(), delete: vi.fn() } as any
  client.pipeline = { get: vi.fn(), list: vi.fn(), start: vi.fn(), cancel: vi.fn(), retryStep: vi.fn() } as any
  ;(client as any).realtime = { subscribe: vi.fn(() => vi.fn()), unsubscribe: vi.fn(), disconnectAll: vi.fn(), getChannelState: vi.fn(() => 'disconnected'), getActiveChannels: vi.fn(() => []), setToken: vi.fn() }
  return client
}

function wrapper(client: NumenClient) {
  return ({ children }: { children: React.ReactNode }) =>
    createElement(NumenProvider, { client }, children)
}

describe('NumenProvider + useNumenClient', () => {
  it('provides the client to children', () => {
    const client = createMockClient()
    const { result } = renderHook(() => useNumenClient(), { wrapper: wrapper(client) })
    expect(result.current).toBe(client)
  })

  it('throws when used outside provider', () => {
    expect(() => { renderHook(() => useNumenClient()) }).toThrow('[numen/sdk] useNumenClient must be used within a <NumenProvider>')
  })

  it('accepts apiKey + baseUrl props', () => {
    const w = ({ children }: { children: React.ReactNode }) =>
      createElement(NumenProvider, { apiKey: 'sk-test', baseUrl: 'https://api.test' }, children)
    const { result } = renderHook(() => useNumenClient(), { wrapper: w })
    expect(result.current).toBeInstanceOf(NumenClient)
  })
})

describe('useContent', () => {
  it('fetches content by id', async () => {
    const client = createMockClient()
    const mockItem = { id: 'c1', title: 'Hello', slug: 'hello', type: 'article', status: 'published', created_at: '', updated_at: '' }
    ;(client.content.get as any).mockResolvedValue({ data: mockItem })
    const { result } = renderHook(() => useContent('c1'), { wrapper: wrapper(client) })
    expect(result.current.isLoading).toBe(true)
    await waitFor(() => expect(result.current.isLoading).toBe(false))
    expect(result.current.data).toEqual(mockItem)
    expect(result.current.error).toBeUndefined()
    expect(client.content.get).toHaveBeenCalledWith('c1')
  })

  it('does not fetch when id is null', () => {
    const client = createMockClient()
    const { result } = renderHook(() => useContent(null), { wrapper: wrapper(client) })
    expect(result.current.isLoading).toBe(false)
    expect(result.current.data).toBeUndefined()
    expect(client.content.get).not.toHaveBeenCalled()
  })

  it('handles errors', async () => {
    const client = createMockClient()
    ;(client.content.get as any).mockRejectedValue(new Error('Not found'))
    const { result } = renderHook(() => useContent('bad'), { wrapper: wrapper(client) })
    await waitFor(() => expect(result.current.isLoading).toBe(false))
    expect(result.current.error?.message).toBe('Not found')
  })
})

describe('useContentList', () => {
  it('fetches content list', async () => {
    const client = createMockClient()
    const mockResponse = { data: [{ id: 'c1', title: 'A' }], meta: { total: 1, page: 1, perPage: 10, lastPage: 1 } }
    ;(client.content.list as any).mockResolvedValue(mockResponse)
    const { result } = renderHook(() => useContentList({ page: 1 }), { wrapper: wrapper(client) })
    await waitFor(() => expect(result.current.isLoading).toBe(false))
    expect(result.current.data).toEqual(mockResponse)
  })
})

describe('usePage', () => {
  it('fetches page by slug', async () => {
    const client = createMockClient()
    const mockPage = { id: 'p1', title: 'About', slug: 'about', status: 'published', created_at: '', updated_at: '' }
    ;(client.pages.get as any).mockResolvedValue({ data: mockPage })
    const { result } = renderHook(() => usePage('about'), { wrapper: wrapper(client) })
    await waitFor(() => expect(result.current.isLoading).toBe(false))
    expect(result.current.data).toEqual(mockPage)
  })

  it('skips fetch when null', () => {
    const client = createMockClient()
    const { result } = renderHook(() => usePage(null), { wrapper: wrapper(client) })
    expect(result.current.isLoading).toBe(false)
    expect(client.pages.get).not.toHaveBeenCalled()
  })
})

describe('useSearch', () => {
  it('searches with query', async () => {
    const client = createMockClient()
    const mockResults = { data: [{ id: 's1', title: 'Result', slug: 'r', type: 'article' }], meta: { total: 1, page: 1, perPage: 10, lastPage: 1 } }
    ;(client.search.search as any).mockResolvedValue(mockResults)
    const { result } = renderHook(() => useSearch('hello'), { wrapper: wrapper(client) })
    await waitFor(() => expect(result.current.isLoading).toBe(false))
    expect(result.current.data).toEqual(mockResults)
  })

  it('does not search when query is null', () => {
    const client = createMockClient()
    const { result } = renderHook(() => useSearch(null), { wrapper: wrapper(client) })
    expect(result.current.isLoading).toBe(false)
    expect(client.search.search).not.toHaveBeenCalled()
  })
})

describe('useMedia', () => {
  it('fetches single media by id', async () => {
    const client = createMockClient()
    const mockMedia = { id: 'm1', filename: 'img.png', url: 'https://cdn/img.png' }
    ;(client.media.get as any).mockResolvedValue({ data: mockMedia })
    const { result } = renderHook(() => useMedia('m1'), { wrapper: wrapper(client) })
    await waitFor(() => expect(result.current.isLoading).toBe(false))
    expect(result.current.data).toEqual(mockMedia)
  })

  it('fetches media list when no id', async () => {
    const client = createMockClient()
    const mockList = { data: [], meta: { total: 0, page: 1, perPage: 10, lastPage: 1 } }
    ;(client.media.list as any).mockResolvedValue(mockList)
    const { result } = renderHook(() => useMedia(), { wrapper: wrapper(client) })
    await waitFor(() => expect(result.current.isLoading).toBe(false))
    expect(result.current.data).toEqual(mockList)
  })
})

describe('usePipelineRun', () => {
  it('fetches pipeline run', async () => {
    const client = createMockClient()
    const mockRun = { id: 'run1', status: 'running', created_at: '', updated_at: '' }
    ;(client.pipeline.get as any).mockResolvedValue({ data: mockRun })
    const { result } = renderHook(() => usePipelineRun('run1'), { wrapper: wrapper(client) })
    await waitFor(() => expect(result.current.isLoading).toBe(false))
    expect(result.current.data).toEqual(mockRun)
  })

  it('skips when runId is null', () => {
    const client = createMockClient()
    const { result } = renderHook(() => usePipelineRun(null), { wrapper: wrapper(client) })
    expect(result.current.isLoading).toBe(false)
    expect(client.pipeline.get).not.toHaveBeenCalled()
  })
})

describe('useRealtime', () => {
  it('subscribes to realtime channel', () => {
    const client = createMockClient()
    const { result } = renderHook(() => useRealtime('content-updates'), { wrapper: wrapper(client) })
    expect(result.current.events).toEqual([])
    expect(result.current.isConnected).toBe(true)
    expect(result.current.error).toBeUndefined()
    expect((client as any).realtime.subscribe).toHaveBeenCalledWith('content-updates', expect.any(Function))
  })
})

describe('mutate and refetch', () => {
  it('mutate with data updates locally', async () => {
    const client = createMockClient()
    const mockItem = { id: 'c1', title: 'Original', slug: 'orig', type: 'article', status: 'published', created_at: '', updated_at: '' }
    ;(client.content.get as any).mockResolvedValue({ data: mockItem })
    const { result } = renderHook(() => useContent('c1'), { wrapper: wrapper(client) })
    await waitFor(() => expect(result.current.data).toEqual(mockItem))
    act(() => { result.current.mutate({ ...mockItem, title: 'Updated' } as any) })
    expect(result.current.data?.title).toBe('Updated')
  })

  it('refetch re-fetches from server', async () => {
    const client = createMockClient()
    const v1 = { id: 'c1', title: 'V1', slug: 'v1', type: 'article', status: 'published', created_at: '', updated_at: '' }
    const v2 = { id: 'c1', title: 'V2', slug: 'v2', type: 'article', status: 'published', created_at: '', updated_at: '' }
    ;(client.content.get as any).mockResolvedValueOnce({ data: v1 }).mockResolvedValueOnce({ data: v2 })
    const { result } = renderHook(() => useContent('c1'), { wrapper: wrapper(client) })
    await waitFor(() => expect(result.current.data?.title).toBe('V1'))
    await act(async () => { await result.current.refetch() })
    await waitFor(() => expect(result.current.data?.title).toBe('V2'))
  })
})
