/**
 * Tests for PollingClient fallback
 */
import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest'
import { PollingClient } from '../../src/realtime/polling.js'

function createMockFetch(events: unknown[] = []) {
  return vi.fn(async () => ({
    ok: true,
    status: 200,
    json: async () => ({ events }),
  })) as unknown as typeof globalThis.fetch
}

function createFailingFetch() {
  return vi.fn(async () => ({
    ok: false,
    status: 500,
    statusText: 'Internal Server Error',
    json: async () => ({}),
  })) as unknown as typeof globalThis.fetch
}

describe('PollingClient', () => {
  beforeEach(() => {
    vi.useFakeTimers()
  })

  afterEach(() => {
    vi.useRealTimers()
  })

  it('creates in disconnected state', () => {
    const client = new PollingClient({
      baseUrl: 'https://api.numen.test',
      fetch: createMockFetch(),
    })
    expect(client.state).toBe('disconnected')
    expect(client.isConnected).toBe(false)
    expect(client.currentChannel).toBeNull()
  })

  it('connects and polls', async () => {
    const mockFetch = createMockFetch([
      { type: 'update', data: { id: '1' }, timestamp: '2026-01-01T00:00:00Z', id: 'evt-1' },
    ])

    const client = new PollingClient({
      baseUrl: 'https://api.numen.test',
      pollInterval: 5000,
      token: 'tok-123',
      fetch: mockFetch,
    })

    const events: unknown[] = []
    client.onEvent((e) => events.push(e))

    client.connect('content.abc')

    // First poll happens immediately
    await vi.advanceTimersByTimeAsync(0)

    expect(mockFetch).toHaveBeenCalledTimes(1)
    const callUrl = (mockFetch as ReturnType<typeof vi.fn>).mock.calls[0][0] as string
    expect(callUrl).toContain('/v1/realtime/content.abc/poll')
    expect(callUrl).not.toContain('last_event_id')

    expect(events).toHaveLength(1)
    expect((events[0] as Record<string, unknown>).type).toBe('update')
    expect((events[0] as Record<string, unknown>).channel).toBe('content.abc')

    expect(client.state).toBe('connected')
    expect(client.isConnected).toBe(true)

    client.disconnect()
  })

  it('polls at configured interval', async () => {
    const mockFetch = createMockFetch()

    const client = new PollingClient({
      baseUrl: 'https://api.numen.test',
      pollInterval: 2000,
      fetch: mockFetch,
    })

    client.connect('pipeline.xyz')
    await vi.advanceTimersByTimeAsync(0) // first poll

    expect(mockFetch).toHaveBeenCalledTimes(1)

    await vi.advanceTimersByTimeAsync(2000) // second poll
    expect(mockFetch).toHaveBeenCalledTimes(2)

    await vi.advanceTimersByTimeAsync(2000) // third poll
    expect(mockFetch).toHaveBeenCalledTimes(3)

    client.disconnect()
  })

  it('includes last_event_id after receiving events', async () => {
    const mockFetch = createMockFetch([
      { type: 'update', data: {}, id: 'evt-42' },
    ])

    const client = new PollingClient({
      baseUrl: 'https://api.numen.test',
      pollInterval: 1000,
      fetch: mockFetch,
    })

    client.connect('content.abc')
    await vi.advanceTimersByTimeAsync(0) // first poll with event

    // Now next poll should include last_event_id
    ;(mockFetch as ReturnType<typeof vi.fn>).mockResolvedValueOnce({
      ok: true,
      status: 200,
      json: async () => ({ events: [] }),
    })

    await vi.advanceTimersByTimeAsync(1000)

    const secondUrl = (mockFetch as ReturnType<typeof vi.fn>).mock.calls[1][0] as string
    expect(secondUrl).toContain('last_event_id=evt-42')

    client.disconnect()
  })

  it('uses auth headers', async () => {
    const mockFetch = createMockFetch()

    const client = new PollingClient({
      baseUrl: 'https://api.numen.test',
      token: 'bearer-tok',
      fetch: mockFetch,
    })

    client.connect('content.abc')
    await vi.advanceTimersByTimeAsync(0)

    const callHeaders = (mockFetch as ReturnType<typeof vi.fn>).mock.calls[0][1].headers
    expect(callHeaders['Authorization']).toBe('Bearer bearer-tok')

    client.disconnect()
  })

  it('uses API key when no token', async () => {
    const mockFetch = createMockFetch()

    const client = new PollingClient({
      baseUrl: 'https://api.numen.test',
      apiKey: 'ak-789',
      fetch: mockFetch,
    })

    client.connect('content.abc')
    await vi.advanceTimersByTimeAsync(0)

    const callHeaders = (mockFetch as ReturnType<typeof vi.fn>).mock.calls[0][1].headers
    expect(callHeaders['X-Api-Key']).toBe('ak-789')

    client.disconnect()
  })

  it('handles poll errors', async () => {
    const mockFetch = createFailingFetch()

    const client = new PollingClient({
      baseUrl: 'https://api.numen.test',
      fetch: mockFetch,
    })

    const errors: Error[] = []
    client.onError((e) => errors.push(e))

    client.connect('content.abc')
    await vi.advanceTimersByTimeAsync(0)

    expect(client.state).toBe('disconnected')
    expect(errors).toHaveLength(1)
    expect(errors[0].message).toContain('500')

    client.disconnect()
  })

  it('disconnects and stops polling', async () => {
    const mockFetch = createMockFetch()

    const client = new PollingClient({
      baseUrl: 'https://api.numen.test',
      pollInterval: 1000,
      fetch: mockFetch,
    })

    client.connect('content.abc')
    await vi.advanceTimersByTimeAsync(0)

    client.disconnect()

    expect(client.state).toBe('disconnected')
    expect(client.currentChannel).toBeNull()

    // No more polls after disconnect
    const countAfterDisconnect = (mockFetch as ReturnType<typeof vi.fn>).mock.calls.length
    await vi.advanceTimersByTimeAsync(5000)
    expect((mockFetch as ReturnType<typeof vi.fn>).mock.calls.length).toBe(countAfterDisconnect)
  })

  it('handles empty event arrays', async () => {
    const mockFetch = createMockFetch([])

    const client = new PollingClient({
      baseUrl: 'https://api.numen.test',
      fetch: mockFetch,
    })

    const events: unknown[] = []
    client.onEvent((e) => events.push(e))

    client.connect('content.abc')
    await vi.advanceTimersByTimeAsync(0)

    expect(events).toHaveLength(0)
    expect(client.isConnected).toBe(true)

    client.disconnect()
  })
})
