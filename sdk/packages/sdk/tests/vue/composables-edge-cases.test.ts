import { describe, it, expect, vi } from "vitest"
import { defineComponent, h, ref, nextTick } from "vue"
import { mount, flushPromises } from "@vue/test-utils"
import { NumenPlugin, useNumenClient } from "../../src/vue/plugin.js"
import { useContent, useContentList, usePage, useSearch, useMedia, usePipelineRun, useRealtime } from "../../src/vue/composables.js"
import { NumenClient } from "../../src/core/client.js"

function mc(): NumenClient {
  const c = new NumenClient({ baseUrl: "https://api.test" }) as any
  c.content = { get: vi.fn(), list: vi.fn() }
  c.pages = { get: vi.fn(), list: vi.fn() }
  c.search = { search: vi.fn(), suggest: vi.fn(), ask: vi.fn() }
  c.media = { get: vi.fn(), list: vi.fn() }
  c.pipeline = { get: vi.fn(), list: vi.fn() }
  c.realtime = { subscribe: vi.fn(() => vi.fn()), unsubscribe: vi.fn(), disconnectAll: vi.fn(), getChannelState: vi.fn(() => "disconnected"), getActiveChannels: vi.fn(() => []), setToken: vi.fn() }
  return c
}

function mountC<T>(composable: () => T, client: NumenClient) {
  let result!: T
  const Comp = defineComponent({ setup() { result = composable(); return () => h("div") } })
  const wrapper = mount(Comp, { global: { plugins: [[NumenPlugin, { client }]] } })
  return { result, wrapper }
}

describe('Composable reactivity: param changes trigger refetch', () => {
  it('useContent refetches when ref id changes', async () => {
    const client = mc()
    ;(client.content.get as any).mockResolvedValue({ data: { id: 'c1', title: 'First' } })
    const idRef = ref<string | null>('c1')
    const { result } = mountC(() => useContent(idRef), client)
    await flushPromises()
    expect(client.content.get).toHaveBeenCalledWith('c1')
    ;(client.content.get as any).mockResolvedValue({ data: { id: 'c2', title: 'Second' } })
    idRef.value = 'c2'
    await flushPromises()
    expect(client.content.get).toHaveBeenCalledWith('c2')
  })

  it('usePage refetches on slug change', async () => {
    const client = mc()
    ;(client.pages.get as any).mockResolvedValue({ data: { id: 'p1', slug: 'home' } })
    const slug = ref<string | null>('home')
    mountC(() => usePage(slug), client)
    await flushPromises()
    expect(client.pages.get).toHaveBeenCalledWith('home')
    ;(client.pages.get as any).mockResolvedValue({ data: { id: 'p2', slug: 'about' } })
    slug.value = 'about'
    await flushPromises()
    expect(client.pages.get).toHaveBeenCalledWith('about')
  })

  it('useContent skips fetch when id becomes null', async () => {
    const client = mc()
    ;(client.content.get as any).mockResolvedValue({ data: { id: 'c1' } })
    const idRef = ref<string | null>('c1')
    mountC(() => useContent(idRef), client)
    await flushPromises()
    const callsBefore = (client.content.get as any).mock.calls.length
    idRef.value = null
    await flushPromises()
    expect((client.content.get as any).mock.calls.length).toBe(callsBefore)
  })
})

describe('Plugin installation verification', () => {
  it('NumenPlugin installs and provides client', () => {
    const client = mc()
    const { result } = mountC(() => useNumenClient(), client)
    expect(result).toBe(client)
  })

  it('throws when plugin not installed', () => {
    const Comp = defineComponent({ setup() { useNumenClient(); return () => h('div') } })
    expect(() => mount(Comp)).toThrow('[numen/sdk]')
  })

  it('accepts apiKey + baseUrl config', () => {
    let result: any
    const Comp = defineComponent({ setup() { result = useNumenClient(); return () => h('div') } })
    mount(Comp, { global: { plugins: [[NumenPlugin, { apiKey: 'sk-test', baseUrl: 'https://api.test' }]] } })
    expect(result).toBeInstanceOf(NumenClient)
  })
})

describe('Component unmount cleanup', () => {
  it('useRealtime unsubscribes on unmount', async () => {
    const client = mc()
    const unsub = vi.fn()
    ;(client.realtime.subscribe as any).mockReturnValue(unsub)
    const { wrapper } = mountC(() => useRealtime('ch1'), client)
    expect(client.realtime.subscribe).toHaveBeenCalled()
    wrapper.unmount()
    expect(unsub).toHaveBeenCalled()
  })

  it('useRealtime resets state when channel ref becomes null', async () => {
    const client = mc()
    ;(client.realtime.subscribe as any).mockReturnValue(vi.fn())
    const ch = ref<string | null>('ch1')
    const { result } = mountC(() => useRealtime(ch), client)
    expect(result.isConnected.value).toBe(true)
    ch.value = null
    await nextTick()
    expect(result.isConnected.value).toBe(false)
  })
})

describe('Loading state transitions (Vue)', () => {
  it('useContent data transitions', async () => {
    const client = mc()
    ;(client.content.get as any).mockResolvedValue({ data: { id: 'c1', title: 'Hello' } })
    const { result } = mountC(() => useContent('c1'), client)
    expect(result.isLoading.value).toBe(true)
    await flushPromises()
    expect(result.isLoading.value).toBe(false)
    expect(result.data.value).toEqual({ id: 'c1', title: 'Hello' })
  })

  it('useContent error state', async () => {
    const client = mc()
    ;(client.content.get as any).mockRejectedValue(new Error('Oops'))
    const { result } = mountC(() => useContent('bad'), client)
    await flushPromises()
    expect(result.error.value?.message).toBe('Oops')
  })

  it('useContentList loading transition', async () => {
    const client = mc()
    const d = { data: [{ id: '1' }], meta: { total: 1, page: 1, perPage: 10, lastPage: 1 } }
    ;(client.content.list as any).mockResolvedValue(d)
    const { result } = mountC(() => useContentList(), client)
    await flushPromises()
    expect(result.data.value).toEqual(d)
  })

  it('null id means no fetch', async () => {
    const client = mc()
    mountC(() => useContent(null), client)
    await flushPromises()
    expect(client.content.get).not.toHaveBeenCalled()
  })
})

describe('useSearch Vue edge cases', () => {
  it('no fetch when null query', async () => {
    const client = mc()
    mountC(() => useSearch(null), client)
    await flushPromises()
    expect(client.search.search).not.toHaveBeenCalled()
  })

  it('fetches with string query', async () => {
    const client = mc()
    const d = { data: [], meta: { total: 0, page: 1, perPage: 10, query: 'test' } }
    ;(client.search.search as any).mockResolvedValue(d)
    const { result } = mountC(() => useSearch('test'), client)
    await flushPromises()
    expect(result.data.value).toEqual(d)
  })
})

describe('useMedia Vue edge cases', () => {
  it('fetches by id', async () => {
    const client = mc()
    ;(client.media.get as any).mockResolvedValue({ data: { id: 'm1' } })
    const { result } = mountC(() => useMedia('m1'), client)
    await flushPromises()
    expect(result.data.value).toEqual({ id: 'm1' })
  })

  it('fetches list when no id', async () => {
    const client = mc()
    const d = { data: [], meta: { total: 0, page: 1, perPage: 10, lastPage: 1 } }
    ;(client.media.list as any).mockResolvedValue(d)
    const { result } = mountC(() => useMedia(), client)
    await flushPromises()
    expect(result.data.value).toEqual(d)
  })
})

describe('useRealtime Vue accumulates events', () => {
  it('collects events', async () => {
    const client = mc()
    let cb: any = null
    ;(client.realtime.subscribe as any).mockImplementation((_: string, fn: any) => { cb = fn; return vi.fn() })
    const { result } = mountC(() => useRealtime('ch1'), client)
    cb({ type: 'a', data: {}, timestamp: 1 })
    await nextTick()
    expect(result.events.value).toHaveLength(1)
    cb({ type: 'b', data: {}, timestamp: 2 })
    await nextTick()
    expect(result.events.value).toHaveLength(2)
  })
})
