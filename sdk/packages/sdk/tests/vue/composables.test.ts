import { describe, it, expect, vi, beforeEach } from 'vitest'
import { defineComponent, h, ref, nextTick } from 'vue'
import { mount, flushPromises } from '@vue/test-utils'
import { NumenPlugin, useNumenClient, NumenClientKey } from '../../src/vue/plugin.js'
import {
  useContent,
  useContentList,
  usePage,
  useSearch,
  useMedia,
  usePipelineRun,
  useRealtime,
} from '../../src/vue/composables.js'
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

function mountComposable<T>(composable: () => T, client: NumenClient): { result: T } {
  let result!: T
  const Comp = defineComponent({
    setup() {
      result = composable()
      return () => h('div')
    },
  })
  mount(Comp, {
    global: {
      plugins: [[NumenPlugin, { client }]],
    },
  })
  return { result }
}

// ─── NumenPlugin + useNumenClient ────────────────────────────

describe('NumenPlugin + useNumenClient', () => {
  it('provides the client to components', () => {
    const client = createMockClient()
    const { result } = mountComposable(() => useNumenClient(), client)
    expect(result).toBe(client)
  })

  it('throws when used outside plugin', () => {
    const Comp = defineComponent({
      setup() {
        useNumenClient()
        return () => h('div')
      },
    })
    expect(() => mount(Comp)).toThrow('[numen/sdk] useNumenClient must be used in a component where NumenPlugin is installed')
  })

  it('accepts apiKey + baseUrl options', () => {
    let result: any
    const Comp = defineComponent({
      setup() {
        result = useNumenClient()
        return () => h('div')
      },
    })
    mount(Comp, {
      global: {
        plugins: [[NumenPlugin, { apiKey: 'sk-test', baseUrl: 'https://api.test' }]],
      },
    })
    expect(result).toBeInstanceOf(NumenClient)
  })
})

// ─── useContent ──────────────────────────────────────────────

describe('useContent', () => {
  it('fetches content by id', async () => {
    const client = createMockClient()
    const mockItem = { id: 'c1', title: 'Hello', slug: 'hello', type: 'article', status: 'published', created_at: '', updated_at: '' }
    ;(client.content.get as any).mockResolvedValue({ data: mockItem })

    const { result } = mountComposable(() => useContent('c1'), client)

    expect(result.isLoading.value).toBe(true)
    await flushPromises()
    expect(result.isLoading.value).toBe(false)
    expect(result.data.value).toEqual(mockItem)
    expect(result.error.value).toBeNull()
    expect(client.content.get).toHaveBeenCalledWith('c1')
  })

  it('does not fetch when id is null', async () => {
    const client = createMockClient()
    const { result } = mountComposable(() => useContent(null), client)

    await flushPromises()
    expect(result.isLoading.value).toBe(false)
    expect(result.data.value).toBeUndefined()
    expect(client.content.get).not.toHaveBeenCalled()
  })

  it('handles errors', async () => {
    const client = createMockClient()
    ;(client.content.get as any).mockRejectedValue(new Error('Not found'))

    const { result } = mountComposable(() => useContent('bad'), client)
    await flushPromises()
    expect(result.isLoading.value).toBe(false)
    expect(result.error.value?.message).toBe('Not found')
  })

  it('refetches when reactive id changes', async () => {
    const client = createMockClient()
    const item1 = { id: 'c1', title: 'First' }
    const item2 = { id: 'c2', title: 'Second' }
    ;(client.content.get as any)
      .mockResolvedValueOnce({ data: item1 })
      .mockResolvedValueOnce({ data: item2 })

    const id = ref<string | null>('c1')
    const { result } = mountComposable(() => useContent(id), client)
    await flushPromises()
    expect(result.data.value).toEqual(item1)

    id.value = 'c2'
    await nextTick()
    await flushPromises()
    expect(result.data.value).toEqual(item2)
    expect(client.content.get).toHaveBeenCalledTimes(2)
  })
})

// ─── useContentList ──────────────────────────────────────────

describe('useContentList', () => {
  it('fetches content list', async () => {
    const client = createMockClient()
    const mockList = { data: [{ id: 'c1' }], meta: { total: 1, page: 1, perPage: 15, lastPage: 1 } }
    ;(client.content.list as any).mockResolvedValue(mockList)

    const { result } = mountComposable(() => useContentList(), client)
    await flushPromises()
    expect(result.data.value).toEqual(mockList)
  })

  it('refetches when reactive params change', async () => {
    const client = createMockClient()
    const list1 = { data: [{ id: 'c1' }], meta: { total: 1, page: 1, perPage: 15, lastPage: 1 } }
    const list2 = { data: [{ id: 'c2' }], meta: { total: 1, page: 2, perPage: 15, lastPage: 2 } }
    ;(client.content.list as any)
      .mockResolvedValueOnce(list1)
      .mockResolvedValueOnce(list2)

    const params = ref({ page: 1 })
    const { result } = mountComposable(() => useContentList(params as any), client)
    await flushPromises()
    expect(result.data.value).toEqual(list1)

    params.value = { page: 2 }
    await nextTick()
    await flushPromises()
    expect(result.data.value).toEqual(list2)
  })
})

// ─── usePage ─────────────────────────────────────────────────

describe('usePage', () => {
  it('fetches page by slug', async () => {
    const client = createMockClient()
    const mockPage = { id: 'p1', title: 'About', slug: 'about' }
    ;(client.pages.get as any).mockResolvedValue({ data: mockPage })

    const { result } = mountComposable(() => usePage('about'), client)
    await flushPromises()
    expect(result.data.value).toEqual(mockPage)
    expect(client.pages.get).toHaveBeenCalledWith('about')
  })

  it('does not fetch when slug is null', async () => {
    const client = createMockClient()
    const { result } = mountComposable(() => usePage(null), client)
    await flushPromises()
    expect(result.data.value).toBeUndefined()
    expect(client.pages.get).not.toHaveBeenCalled()
  })
})

// ─── useSearch ───────────────────────────────────────────────

describe('useSearch', () => {
  it('searches with a query', async () => {
    const client = createMockClient()
    const mockResults = { data: [{ id: 'r1' }], meta: { total: 1, page: 1, perPage: 15, lastPage: 1 } }
    ;(client.search.search as any).mockResolvedValue(mockResults)

    const { result } = mountComposable(() => useSearch('hello'), client)
    await flushPromises()
    expect(result.data.value).toEqual(mockResults)
    expect(client.search.search).toHaveBeenCalledWith(
      expect.objectContaining({ q: 'hello' }),
    )
  })

  it('does not search when query is null', async () => {
    const client = createMockClient()
    const { result } = mountComposable(() => useSearch(null), client)
    await flushPromises()
    expect(result.data.value).toBeUndefined()
    expect(client.search.search).not.toHaveBeenCalled()
  })
})

// ─── useMedia ────────────────────────────────────────────────

describe('useMedia', () => {
  it('fetches single media by id', async () => {
    const client = createMockClient()
    const mockAsset = { id: 'm1', filename: 'photo.jpg', mime_type: 'image/jpeg', url: 'https://cdn/photo.jpg', size: 1000, created_at: '', updated_at: '' }
    ;(client.media.get as any).mockResolvedValue({ data: mockAsset })

    const { result } = mountComposable(() => useMedia('m1'), client)
    await flushPromises()
    expect(result.data.value).toEqual(mockAsset)
    expect(client.media.get).toHaveBeenCalledWith('m1')
  })

  it('fetches media list when no id', async () => {
    const client = createMockClient()
    const mockList = { data: [{ id: 'm1' }], meta: { total: 1, page: 1, perPage: 15, lastPage: 1 } }
    ;(client.media.list as any).mockResolvedValue(mockList)

    const { result } = mountComposable(() => useMedia(), client)
    await flushPromises()
    expect(result.data.value).toEqual(mockList)
    expect(client.media.list).toHaveBeenCalled()
  })
})

// ─── usePipelineRun ──────────────────────────────────────────

describe('usePipelineRun', () => {
  it('fetches pipeline run', async () => {
    const client = createMockClient()
    const mockRun = { id: 'run1', status: 'running', steps: [], created_at: '', updated_at: '' }
    ;(client.pipeline.get as any).mockResolvedValue({ data: mockRun })

    const { result } = mountComposable(() => usePipelineRun('run1'), client)
    await flushPromises()
    expect(result.data.value).toEqual(mockRun)
    expect(client.pipeline.get).toHaveBeenCalledWith('run1')
  })

  it('does not fetch when runId is null', async () => {
    const client = createMockClient()
    const { result } = mountComposable(() => usePipelineRun(null), client)
    await flushPromises()
    expect(result.data.value).toBeUndefined()
    expect(client.pipeline.get).not.toHaveBeenCalled()
  })
})

// ─── useRealtime ─────────────────────────────────────────────

describe('useRealtime', () => {
  it('returns skeleton state', () => {
    const client = createMockClient()
    const { result } = mountComposable(() => useRealtime('test-channel'), client)
    expect(result.events.value).toEqual([])
    expect(result.isConnected.value).toBe(false)
    expect(result.error.value).toBeNull()
  })
})
