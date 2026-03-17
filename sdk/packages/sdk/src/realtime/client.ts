/**
 * @numen/sdk — RealtimeClient
 * SSE (Server-Sent Events) connection to Numen's realtime endpoint.
 */

export interface RealtimeEvent {
  type: string
  channel: string
  data: unknown
  timestamp: string
  id?: string
}

export type RealtimeEventHandler = (event: RealtimeEvent) => void
export type ConnectionState = 'disconnected' | 'connecting' | 'connected' | 'reconnecting'
export type ConnectionStateHandler = (state: ConnectionState) => void
export type ErrorHandler = (error: Error) => void

export interface RealtimeClientOptions {
  /** Base URL of the Numen API */
  baseUrl: string
  /** Bearer token or API key for auth */
  token?: string
  apiKey?: string
  /** Max reconnect attempts (default: 10) */
  maxReconnectAttempts?: number
  /** Initial reconnect delay in ms (default: 1000) */
  reconnectDelay?: number
  /** Max reconnect delay in ms (default: 30000) */
  maxReconnectDelay?: number
}

/**
 * SSE-based realtime client for Numen channels.
 *
 * Channels follow the pattern: `content.{id}`, `pipeline.{id}`, `space.{id}`
 */
export class RealtimeClient {
  private readonly options: RealtimeClientOptions
  private eventSource: EventSource | null = null
  private channel: string | null = null
  private reconnectAttempts = 0
  private reconnectTimer: ReturnType<typeof setTimeout> | null = null
  private _state: ConnectionState = 'disconnected'
  private lastEventId: string | undefined

  private readonly eventHandlers = new Set<RealtimeEventHandler>()
  private readonly stateHandlers = new Set<ConnectionStateHandler>()
  private readonly errorHandlers = new Set<ErrorHandler>()

  constructor(options: RealtimeClientOptions) {
    this.options = {
      maxReconnectAttempts: 10,
      reconnectDelay: 1_000,
      maxReconnectDelay: 30_000,
      ...options,
    }
  }

  /** Current connection state */
  get state(): ConnectionState {
    return this._state
  }

  /** Whether the client is currently connected */
  get isConnected(): boolean {
    return this._state === 'connected'
  }

  /** Currently connected channel (or null) */
  get currentChannel(): string | null {
    return this.channel
  }

  /**
   * Connect to a realtime channel via SSE.
   */
  connect(channel: string): void {
    // If already connected to the same channel, no-op
    if (this.channel === channel && this._state === 'connected') return

    // Disconnect any existing connection
    this.disconnect()

    this.channel = channel
    this.reconnectAttempts = 0
    this._openConnection()
  }

  /**
   * Disconnect from the current channel.
   */
  disconnect(): void {
    if (this.reconnectTimer) {
      clearTimeout(this.reconnectTimer)
      this.reconnectTimer = null
    }

    if (this.eventSource) {
      this.eventSource.close()
      this.eventSource = null
    }

    this.channel = null
    this.lastEventId = undefined
    this.reconnectAttempts = 0
    this._setState('disconnected')
  }

  /** Register an event handler */
  onEvent(handler: RealtimeEventHandler): () => void {
    this.eventHandlers.add(handler)
    return () => { this.eventHandlers.delete(handler) }
  }

  /** Register a connection state change handler */
  onStateChange(handler: ConnectionStateHandler): () => void {
    this.stateHandlers.add(handler)
    return () => { this.stateHandlers.delete(handler) }
  }

  /** Register an error handler */
  onError(handler: ErrorHandler): () => void {
    this.errorHandlers.add(handler)
    return () => { this.errorHandlers.delete(handler) }
  }

  // ── Internals ──────────────────────────────────────────────

  private _buildUrl(channel: string): string {
    const base = this.options.baseUrl.replace(/\/$/, '')
    const url = new URL(`${base}/v1/realtime/${channel}`)

    if (this.options.token) {
      url.searchParams.set('token', this.options.token)
    } else if (this.options.apiKey) {
      url.searchParams.set('api_key', this.options.apiKey)
    }

    if (this.lastEventId) {
      url.searchParams.set('last_event_id', this.lastEventId)
    }

    return url.toString()
  }

  private _openConnection(): void {
    if (!this.channel) return

    this._setState(this.reconnectAttempts === 0 ? 'connecting' : 'reconnecting')

    const url = this._buildUrl(this.channel)

    try {
      this.eventSource = new EventSource(url)
    } catch (err) {
      this._emitError(new Error(`Failed to create EventSource: ${err}`))
      this._scheduleReconnect()
      return
    }

    this.eventSource.onopen = () => {
      this.reconnectAttempts = 0
      this._setState('connected')
    }

    this.eventSource.onmessage = (ev: MessageEvent) => {
      this._handleMessage(ev)
    }

    // Listen for typed events too
    this.eventSource.addEventListener('update', (ev) => {
      this._handleMessage(ev as MessageEvent)
    })

    this.eventSource.addEventListener('delete', (ev) => {
      this._handleMessage(ev as MessageEvent)
    })

    this.eventSource.addEventListener('status', (ev) => {
      this._handleMessage(ev as MessageEvent)
    })

    this.eventSource.onerror = () => {
      if (this.eventSource?.readyState === 2 /* EventSource.CLOSED */) {
        this.eventSource = null
        this._emitError(new Error('SSE connection closed'))
        this._scheduleReconnect()
      }
    }
  }

  private _handleMessage(ev: MessageEvent): void {
    if (ev.lastEventId) {
      this.lastEventId = ev.lastEventId
    }

    let parsed: RealtimeEvent
    try {
      const raw = JSON.parse(ev.data)
      parsed = {
        type: (ev as MessageEvent & { type?: string }).type === 'message'
          ? (raw.type ?? 'message')
          : ((ev as MessageEvent & { type?: string }).type ?? raw.type ?? 'message'),
        channel: this.channel!,
        data: raw.data ?? raw,
        timestamp: raw.timestamp ?? new Date().toISOString(),
        id: ev.lastEventId || raw.id,
      }
    } catch {
      // Non-JSON payload
      parsed = {
        type: 'message',
        channel: this.channel!,
        data: ev.data,
        timestamp: new Date().toISOString(),
        id: ev.lastEventId || undefined,
      }
    }

    for (const handler of this.eventHandlers) {
      try {
        handler(parsed)
      } catch {
        // Swallow handler errors
      }
    }
  }

  private _scheduleReconnect(): void {
    if (!this.channel) return

    const max = this.options.maxReconnectAttempts!
    if (this.reconnectAttempts >= max) {
      this._emitError(new Error(`Max reconnect attempts (${max}) reached`))
      this._setState('disconnected')
      return
    }

    const delay = Math.min(
      this.options.reconnectDelay! * Math.pow(2, this.reconnectAttempts),
      this.options.maxReconnectDelay!,
    )

    this.reconnectAttempts++
    this._setState('reconnecting')
    this.reconnectTimer = setTimeout(() => {
      this.reconnectTimer = null
      this._openConnection()
    }, delay)
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
