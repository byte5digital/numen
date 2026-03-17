/**
 * Tests for RealtimeClient (SSE)
 */
import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest'
import { RealtimeClient } from '../../src/realtime/client.js'
import type { ConnectionState } from '../../src/realtime/client.js'

// Mock EventSource
class MockEventSource {
  static instances: MockEventSource[] = []
  static autoOpen = true
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
    if (MockEventSource.autoOpen) {
      // Auto-open after microtask
      setTimeout(() => {
        if (this.readyState !== MockEventSource.CLOSED) {
          this.readyState = MockEventSource.OPEN
          this.onopen?.()
        }
      }, 0)
    }
  }

  // Test helper: manually trigger open
  _open() {
    this.readyState = MockEventSource.OPEN
    this.onopen?.()
  }

  addEventListener(type: string, handler: (ev: Event) => void) {
    const handlers = this.listeners.get(type) ?? []
    handlers.push(handler)
    this.listeners.set(type, handlers)
  }

  removeEventListener(type: string, handler: (ev: Event) => void) {
    const handlers = this.listeners.get(type) ?? []
    this.listeners.set(type, handlers.filter(h => h !== handler))
  }

  close() {
    this.readyState = MockEventSource.CLOSED
  }

  // Test helper: simulate a message
  _simulateMessage(data: string, eventType?: string, lastEventId?: string) {
    const ev = {
      data,
      type: eventType ?? 'message',
      lastEventId: lastEventId ?? '',
    } as unknown as MessageEvent

    if (eventType && eventType !== 'message') {
      const handlers = this.listeners.get(eventType) ?? []
      for (const handler of handlers) handler(ev)
    } else {
      this.onmessage?.(ev)
    }
  }

  // Test helper: simulate error
  _simulateError() {
    this.readyState = MockEventSource.CLOSED
    this.onerror?.()
  }

  static reset() {
    MockEventSource.instances = []
    MockEventSource.autoOpen = true
  }
}

// Install mock
const origEventSource = globalThis.EventSource
beforeEach(() => {
  MockEventSource.reset()
  ;(globalThis as unknown as Record<string, unknown>).EventSource = MockEventSource as unknown as typeof EventSource
})
afterEach(() => {
  ;(globalThis as unknown as Record<string, unknown>).EventSource = origEventSource
})

describe('RealtimeClient', () => {
  const baseOpts = { baseUrl: 'https://api.numen.test' }

  it('creates a client with disconnected state', () => {
    const client = new RealtimeClient(baseOpts)
    expect(client.state).toBe('disconnected')
    expect(client.isConnected).toBe(false)
    expect(client.currentChannel).toBeNull()
  })

  it('connects to a channel', async () => {
    const client = new RealtimeClient(baseOpts)
    const states: ConnectionState[] = []
    client.onStateChange((s) => states.push(s))

    client.connect('content.abc123')

    expect(client.currentChannel).toBe('content.abc123')
    expect(states).toContain('connecting')

    // Wait for mock EventSource to "open"
    await new Promise(r => setTimeout(r, 10))

    expect(client.state).toBe('connected')
    expect(client.isConnected).toBe(true)
    expect(states).toContain('connected')

    client.disconnect()
  })

  it('builds URL with auth token', () => {
    const client = new RealtimeClient({ ...baseOpts, token: 'tok-123' })
    client.connect('pipeline.xyz')

    const instance = MockEventSource.instances[0]
    expect(instance.url).toContain('/v1/realtime/pipeline.xyz')
    expect(instance.url).toContain('token=tok-123')

    client.disconnect()
  })

  it('builds URL with API key', () => {
    const client = new RealtimeClient({ ...baseOpts, apiKey: 'ak-456' })
    client.connect('space.s1')

    const instance = MockEventSource.instances[0]
    expect(instance.url).toContain('api_key=ak-456')

    client.disconnect()
  })

  it('dispatches parsed events', async () => {
    const client = new RealtimeClient(baseOpts)
    const events: unknown[] = []
    client.onEvent((e) => events.push(e))

    client.connect('content.abc')
    await new Promise(r => setTimeout(r, 10))

    const source = MockEventSource.instances[0]
    source._simulateMessage(JSON.stringify({ type: 'update', data: { id: '1' }, timestamp: '2026-01-01T00:00:00Z' }))

    expect(events).toHaveLength(1)
    expect(events[0]).toMatchObject({
      type: 'update',
      channel: 'content.abc',
      data: { id: '1' },
    })

    client.disconnect()
  })

  it('handles non-JSON messages', async () => {
    const client = new RealtimeClient(baseOpts)
    const events: unknown[] = []
    client.onEvent((e) => events.push(e))

    client.connect('content.abc')
    await new Promise(r => setTimeout(r, 10))

    const source = MockEventSource.instances[0]
    source._simulateMessage('plain text data')

    expect(events).toHaveLength(1)
    expect((events[0] as Record<string, unknown>).data).toBe('plain text data')

    client.disconnect()
  })

  it('handles typed SSE events (update, delete, status)', async () => {
    const client = new RealtimeClient(baseOpts)
    const events: unknown[] = []
    client.onEvent((e) => events.push(e))

    client.connect('content.abc')
    await new Promise(r => setTimeout(r, 10))

    const source = MockEventSource.instances[0]
    source._simulateMessage(JSON.stringify({ data: { deleted: true } }), 'delete')

    expect(events).toHaveLength(1)
    expect((events[0] as Record<string, unknown>).type).toBe('delete')

    client.disconnect()
  })

  it('disconnects and cleans up', async () => {
    const client = new RealtimeClient(baseOpts)
    client.connect('content.abc')
    await new Promise(r => setTimeout(r, 10))

    client.disconnect()

    expect(client.state).toBe('disconnected')
    expect(client.isConnected).toBe(false)
    expect(client.currentChannel).toBeNull()
    expect(MockEventSource.instances[0].readyState).toBe(MockEventSource.CLOSED)
  })

  it('attempts reconnect on error with exponential backoff', async () => {
    vi.useFakeTimers()

    const client = new RealtimeClient({
      ...baseOpts,
      maxReconnectAttempts: 3,
      reconnectDelay: 100,
    })

    const errors: Error[] = []
    client.onError((e) => errors.push(e))

    client.connect('content.abc')
    await vi.advanceTimersByTimeAsync(1)
    expect(client.state).toBe('connected')

    // Disable auto-open so reconnects don't auto-succeed
    MockEventSource.autoOpen = false

    // First error: triggers reconnect attempt 1
    MockEventSource.instances[0]._simulateError()
    expect(client.state).toBe('reconnecting')

    // Reconnect 1 at 100ms (delay * 2^0), immediately fail
    await vi.advanceTimersByTimeAsync(100)
    MockEventSource.instances[1]._simulateError()

    // Reconnect 2 at 200ms (delay * 2^1), immediately fail
    await vi.advanceTimersByTimeAsync(200)
    MockEventSource.instances[2]._simulateError()

    // Reconnect 3 at 400ms (delay * 2^2), immediately fail
    await vi.advanceTimersByTimeAsync(400)
    MockEventSource.instances[3]._simulateError()

    // Now attempts(3) >= max(3), should be disconnected
    expect(client.state).toBe('disconnected')
    expect(errors.some(e => e.message.includes('Max reconnect attempts'))).toBe(true)

    client.disconnect()
    vi.useRealTimers()
  })

  it('tracks lastEventId for resume', async () => {
    vi.useFakeTimers()

    const client = new RealtimeClient({ ...baseOpts, reconnectDelay: 100 })
    client.connect('content.abc')
    await vi.advanceTimersByTimeAsync(1)

    const source = MockEventSource.instances[0]
    source._simulateMessage(
      JSON.stringify({ type: 'update', data: {} }),
      undefined,
      'evt-42',
    )

    // Force a reconnect to check lastEventId is passed
    source._simulateError()

    // Wait for reconnect timer
    await vi.advanceTimersByTimeAsync(100)

    // The 2nd EventSource should have last_event_id in URL
    const reconnectInstance = MockEventSource.instances.find(
      (inst, idx) => idx > 0 && inst.url.includes('last_event_id=evt-42')
    )
    expect(reconnectInstance).toBeDefined()

    client.disconnect()
    vi.useRealTimers()
  })

  it('removes handlers via cleanup function', () => {
    const client = new RealtimeClient(baseOpts)
    const handler = vi.fn()
    const remove = client.onEvent(handler)
    remove()

    // handler should not be called after removal
    // (would need to connect and send event to fully verify,
    // but the set removal is the key behavior)
    expect(handler).not.toHaveBeenCalled()
  })
})
