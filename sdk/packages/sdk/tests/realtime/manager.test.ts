/**
 * Tests for RealtimeManager
 */
import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest'
import { RealtimeManager } from '../../src/realtime/manager.js'
import type { RealtimeEvent } from '../../src/realtime/client.js'

// We need EventSource mock for SSE mode
class MockEventSource {
  static instances: MockEventSource[] = []
  url: string
  onopen: (() => void) | null = null
  onmessage: ((ev: MessageEvent) => void) | null = null
  onerror: (() => void) | null = null
  readyState = 0
  private listeners = new Map<string, ((ev: Event) => void)[]>()

  static readonly CONNECTING = 0
  static readonly OPEN = 1
  static readonly CLOSED = 2

  constructor(url: string) {
    this.url = url
    MockEventSource.instances.push(this)
    setTimeout(() => {
      this.readyState = MockEventSource.OPEN
      this.onopen?.()
    }, 0)
  }

  addEventListener(type: string, handler: (ev: Event) => void) {
    const handlers = this.listeners.get(type) ?? []
    handlers.push(handler)
    this.listeners.set(type, handlers)
  }

  removeEventListener() {}

  close() {
    this.readyState = MockEventSource.CLOSED
  }

  _simulateMessage(data: string) {
    const ev = { data, type: 'message', lastEventId: '' } as unknown as MessageEvent
    this.onmessage?.(ev)
  }

  static reset() {
    MockEventSource.instances = []
  }
}

const origEventSource = globalThis.EventSource

beforeEach(() => {
  MockEventSource.reset()
  ;(globalThis as unknown as Record<string, unknown>).EventSource = MockEventSource as unknown as typeof EventSource
})

afterEach(() => {
  ;(globalThis as unknown as Record<string, unknown>).EventSource = origEventSource
})

describe('RealtimeManager', () => {
  const baseOpts = { baseUrl: 'https://api.numen.test' }

  it('creates manager with no active channels', () => {
    const manager = new RealtimeManager(baseOpts)
    expect(manager.getActiveChannels()).toEqual([])
  })

  it('subscribes to a channel and receives events', async () => {
    const manager = new RealtimeManager(baseOpts)
    const events: RealtimeEvent[] = []

    manager.subscribe('content.abc', (e) => events.push(e))

    await new Promise(r => setTimeout(r, 10))

    expect(manager.getActiveChannels()).toEqual(['content.abc'])

    // Send an event via mock
    MockEventSource.instances[0]._simulateMessage(
      JSON.stringify({ type: 'update', data: { id: '1' } })
    )

    expect(events).toHaveLength(1)
    expect(events[0].channel).toBe('content.abc')

    manager.disconnectAll()
  })

  it('deduplicates connections for same channel', async () => {
    const manager = new RealtimeManager(baseOpts)

    const events1: RealtimeEvent[] = []
    const events2: RealtimeEvent[] = []

    manager.subscribe('content.abc', (e) => events1.push(e))
    manager.subscribe('content.abc', (e) => events2.push(e))

    await new Promise(r => setTimeout(r, 10))

    // Should only create ONE EventSource
    expect(MockEventSource.instances).toHaveLength(1)

    MockEventSource.instances[0]._simulateMessage(
      JSON.stringify({ type: 'update', data: {} })
    )

    // Both callbacks get the event
    expect(events1).toHaveLength(1)
    expect(events2).toHaveLength(1)

    manager.disconnectAll()
  })

  it('creates separate connections for different channels', async () => {
    const manager = new RealtimeManager(baseOpts)

    manager.subscribe('content.abc', () => {})
    manager.subscribe('pipeline.xyz', () => {})

    await new Promise(r => setTimeout(r, 10))

    expect(MockEventSource.instances).toHaveLength(2)
    expect(manager.getActiveChannels()).toEqual(['content.abc', 'pipeline.xyz'])

    manager.disconnectAll()
  })

  it('unsubscribes single callback without closing channel', async () => {
    const manager = new RealtimeManager(baseOpts)

    const events1: RealtimeEvent[] = []
    const events2: RealtimeEvent[] = []

    const unsub1 = manager.subscribe('content.abc', (e) => events1.push(e))
    manager.subscribe('content.abc', (e) => events2.push(e))

    await new Promise(r => setTimeout(r, 10))

    unsub1()

    MockEventSource.instances[0]._simulateMessage(
      JSON.stringify({ type: 'update', data: {} })
    )

    // Only second callback should receive
    expect(events1).toHaveLength(0)
    expect(events2).toHaveLength(1)

    // Channel still active
    expect(manager.getActiveChannels()).toEqual(['content.abc'])

    manager.disconnectAll()
  })

  it('closes connection when last subscriber unsubscribes', async () => {
    const manager = new RealtimeManager(baseOpts)

    const unsub = manager.subscribe('content.abc', () => {})
    await new Promise(r => setTimeout(r, 10))

    expect(manager.getActiveChannels()).toEqual(['content.abc'])

    unsub()

    expect(manager.getActiveChannels()).toEqual([])
    expect(MockEventSource.instances[0].readyState).toBe(MockEventSource.CLOSED)
  })

  it('unsubscribe() removes channel entirely', async () => {
    const manager = new RealtimeManager(baseOpts)

    manager.subscribe('content.abc', () => {})
    manager.subscribe('content.abc', () => {})
    await new Promise(r => setTimeout(r, 10))

    manager.unsubscribe('content.abc')

    expect(manager.getActiveChannels()).toEqual([])
  })

  it('disconnectAll() cleans everything', async () => {
    const manager = new RealtimeManager(baseOpts)

    manager.subscribe('content.abc', () => {})
    manager.subscribe('pipeline.xyz', () => {})
    await new Promise(r => setTimeout(r, 10))

    manager.disconnectAll()

    expect(manager.getActiveChannels()).toEqual([])
  })

  it('forcePolling option skips SSE', async () => {
    const mockFetch = vi.fn(async () => ({
      ok: true,
      status: 200,
      json: async () => ({ events: [] }),
    })) as unknown as typeof globalThis.fetch

    const manager = new RealtimeManager({
      ...baseOpts,
      forcePolling: true,
      fetch: mockFetch,
    })

    manager.subscribe('content.abc', () => {})

    // Should NOT create EventSource
    expect(MockEventSource.instances).toHaveLength(0)

    // Should have called fetch (polling)
    await new Promise(r => setTimeout(r, 10))
    expect(mockFetch).toHaveBeenCalled()

    manager.disconnectAll()
  })

  it('getChannelState returns correct state', async () => {
    const manager = new RealtimeManager(baseOpts)

    expect(manager.getChannelState('content.abc')).toBe('disconnected')

    manager.subscribe('content.abc', () => {})
    await new Promise(r => setTimeout(r, 10))

    expect(manager.getChannelState('content.abc')).toBe('connected')

    manager.disconnectAll()
  })
})
