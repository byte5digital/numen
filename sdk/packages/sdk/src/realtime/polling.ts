/**
 * @numen/sdk — PollingFallback
 * Polling-based fallback when SSE is unavailable.
 * Exposes the same API surface as RealtimeClient.
 */

import type {
  RealtimeEvent,
  RealtimeEventHandler,
  ConnectionState,
  ConnectionStateHandler,
  ErrorHandler,
} from './client.js'

export interface PollingClientOptions {
  /** Base URL of the Numen API */
  baseUrl: string
  /** Bearer token for auth */
  token?: string
  /** API key for auth */
  apiKey?: string
  /** Poll interval in ms (default: 5000) */
  pollInterval?: number
  /** Custom fetch implementation */
  fetch?: typeof globalThis.fetch
}

/**
 * Polling-based realtime client.
 * Same API surface as RealtimeClient so they can be swapped transparently.
 */
export class PollingClient {
  private readonly options: PollingClientOptions
  private pollTimer: ReturnType<typeof setInterval> | null = null
  private channel: string | null = null
  private _state: ConnectionState = 'disconnected'
  private lastEventId: string | undefined
  private fetchFn: typeof globalThis.fetch

  private readonly eventHandlers = new Set<RealtimeEventHandler>()
  private readonly stateHandlers = new Set<ConnectionStateHandler>()
  private readonly errorHandlers = new Set<ErrorHandler>()

  constructor(options: PollingClientOptions) {
    this.options = {
      pollInterval: 5_000,
      ...options,
    }
    this.fetchFn = options.fetch ?? globalThis.fetch
  }

  get state(): ConnectionState {
    return this._state
  }

  get isConnected(): boolean {
    return this._state === 'connected'
  }

  get currentChannel(): string | null {
    return this.channel
  }

  connect(channel: string): void {
    if (this.channel === channel && this._state === 'connected') return

    this.disconnect()
    this.channel = channel
    this._setState('connecting')

    // Immediately do first poll, then set interval
    this._poll().then(() => {
      if (this.channel === channel) {
        this._setState('connected')
        this.pollTimer = setInterval(() => this._poll(), this.options.pollInterval!)
      }
    }).catch((err) => {
      this._emitError(err instanceof Error ? err : new Error(String(err)))
      this._setState('disconnected')
    })
  }

  disconnect(): void {
    if (this.pollTimer) {
      clearInterval(this.pollTimer)
      this.pollTimer = null
    }
    this.channel = null
    this.lastEventId = undefined
    this._setState('disconnected')
  }

  onEvent(handler: RealtimeEventHandler): () => void {
    this.eventHandlers.add(handler)
    return () => { this.eventHandlers.delete(handler) }
  }

  onStateChange(handler: ConnectionStateHandler): () => void {
    this.stateHandlers.add(handler)
    return () => { this.stateHandlers.delete(handler) }
  }

  onError(handler: ErrorHandler): () => void {
    this.errorHandlers.add(handler)
    return () => { this.errorHandlers.delete(handler) }
  }

  // ── Internals ──────────────────────────────────────────────

  private async _poll(): Promise<void> {
    if (!this.channel) return

    const base = this.options.baseUrl.replace(/\/$/, '')
    const url = new URL(`${base}/v1/realtime/${this.channel}/poll`)

    if (this.lastEventId) {
      url.searchParams.set('last_event_id', this.lastEventId)
    }

    const headers: Record<string, string> = {
      Accept: 'application/json',
    }

    if (this.options.token) {
      headers['Authorization'] = `Bearer ${this.options.token}`
    } else if (this.options.apiKey) {
      headers['X-Api-Key'] = this.options.apiKey
    }

    const res = await this.fetchFn(url.toString(), { headers })

    if (!res.ok) {
      throw new Error(`Poll request failed: ${res.status} ${res.statusText}`)
    }

    const body = await res.json() as { events?: RealtimeEvent[] }
    const events: RealtimeEvent[] = body.events ?? []

    for (const event of events) {
      if (event.id) {
        this.lastEventId = event.id
      }

      const normalized: RealtimeEvent = {
        type: event.type ?? 'message',
        channel: this.channel!,
        data: event.data,
        timestamp: event.timestamp ?? new Date().toISOString(),
        id: event.id,
      }

      for (const handler of this.eventHandlers) {
        try { handler(normalized) } catch { /* swallow */ }
      }
    }
  }

  private _setState(state: ConnectionState): void {
    if (this._state === state) return
    this._state = state
    for (const handler of this.stateHandlers) {
      try { handler(state) } catch { /* swallow */ }
    }
  }

  private _emitError(error: Error): void {
    for (const handler of this.errorHandlers) {
      try { handler(error) } catch { /* swallow */ }
    }
  }
}
