/**
 * @numen/sdk — RealtimeManager
 * Manages multiple channel subscriptions with SSE + polling fallback.
 */

import { RealtimeClient } from './client.js'
import { PollingClient } from './polling.js'
import type {
  RealtimeEvent,
  RealtimeEventHandler,
  ConnectionState,
  RealtimeClientOptions,
} from './client.js'

export type SubscriptionCallback = (event: RealtimeEvent) => void

export interface RealtimeManagerOptions {
  /** Base URL of the Numen API */
  baseUrl: string
  /** Bearer token */
  token?: string
  /** API key */
  apiKey?: string
  /** Force polling mode (skip SSE attempt) */
  forcePolling?: boolean
  /** Poll interval for fallback (default: 5000) */
  pollInterval?: number
  /** Custom fetch for polling */
  fetch?: typeof globalThis.fetch
  /** Max SSE reconnect attempts */
  maxReconnectAttempts?: number
}

interface ChannelSubscription {
  client: RealtimeClient | PollingClient
  callbacks: Map<string, SubscriptionCallback>
  cleanups: (() => void)[]
}

let subIdCounter = 0

/**
 * Manages multiple realtime channel subscriptions.
 * Deduplicates connections: one SSE/polling client per channel.
 * Auto-detects SSE support and falls back to polling on failure.
 */
export class RealtimeManager {
  private readonly options: RealtimeManagerOptions
  private readonly channels = new Map<string, ChannelSubscription>()
  private _sseAvailable: boolean | null = null

  constructor(options: RealtimeManagerOptions) {
    this.options = {
      pollInterval: 5_000,
      maxReconnectAttempts: 10,
      ...options,
    }

    if (options.forcePolling) {
      this._sseAvailable = false
    }
  }

  /**
   * Subscribe to a realtime channel.
   * Returns an unsubscribe function.
   */
  subscribe(channel: string, callback: SubscriptionCallback): () => void {
    const subId = `sub_${++subIdCounter}`

    let sub = this.channels.get(channel)

    if (!sub) {
      // Create a new connection for this channel
      const client = this._createClient()
      const cleanups: (() => void)[] = []

      sub = { client, callbacks: new Map(), cleanups }
      this.channels.set(channel, sub)

      // Wire event forwarding
      const removeEvent = client.onEvent((event) => {
        const currentSub = this.channels.get(channel)
        if (currentSub) {
          for (const cb of currentSub.callbacks.values()) {
            try { cb(event) } catch { /* swallow */ }
          }
        }
      })
      cleanups.push(removeEvent)

      // Auto-fallback: if SSE fails and we haven't determined availability yet
      if (this._sseAvailable !== false && client instanceof RealtimeClient) {
        const removeError = client.onError(() => {
          if (this._sseAvailable === null) {
            // SSE failed, switch to polling for this channel
            this._sseAvailable = false
            this._switchToPolling(channel)
          }
        })
        cleanups.push(removeError)
      }

      client.connect(channel)
    }

    sub.callbacks.set(subId, callback)

    // Return unsubscribe function
    return () => {
      const currentSub = this.channels.get(channel)
      if (!currentSub) return

      currentSub.callbacks.delete(subId)

      // If no more subscribers, tear down the connection
      if (currentSub.callbacks.size === 0) {
        currentSub.client.disconnect()
        for (const cleanup of currentSub.cleanups) cleanup()
        this.channels.delete(channel)
      }
    }
  }

  /**
   * Unsubscribe all callbacks from a channel and disconnect.
   */
  unsubscribe(channel: string): void {
    const sub = this.channels.get(channel)
    if (!sub) return

    sub.client.disconnect()
    for (const cleanup of sub.cleanups) cleanup()
    this.channels.delete(channel)
  }

  /**
   * Get the connection state for a channel.
   */
  getChannelState(channel: string): ConnectionState {
    return this.channels.get(channel)?.client.state ?? 'disconnected'
  }

  /**
   * Get all active channel names.
   */
  getActiveChannels(): string[] {
    return Array.from(this.channels.keys())
  }

  /**
   * Disconnect all channels and clean up.
   */
  disconnectAll(): void {
    for (const [channel, sub] of this.channels) {
      sub.client.disconnect()
      for (const cleanup of sub.cleanups) cleanup()
    }
    this.channels.clear()
  }

  /**
   * Update auth token for all active connections.
   * Reconnects all channels with the new token.
   */
  setToken(token: string): void {
    this.options.token = token
    // Reconnect all channels with new auth
    for (const [channel] of this.channels) {
      this._reconnectChannel(channel)
    }
  }

  // ── Internals ──────────────────────────────────────────────

  private _createClient(): RealtimeClient | PollingClient {
    if (this._sseAvailable === false) {
      return new PollingClient({
        baseUrl: this.options.baseUrl,
        token: this.options.token,
        apiKey: this.options.apiKey,
        pollInterval: this.options.pollInterval,
        fetch: this.options.fetch,
      })
    }

    return new RealtimeClient({
      baseUrl: this.options.baseUrl,
      token: this.options.token,
      apiKey: this.options.apiKey,
      maxReconnectAttempts: this.options.maxReconnectAttempts,
    })
  }

  private _switchToPolling(channel: string): void {
    const sub = this.channels.get(channel)
    if (!sub) return

    // Disconnect old SSE client
    sub.client.disconnect()
    for (const cleanup of sub.cleanups) cleanup()
    sub.cleanups.length = 0

    // Create polling replacement
    const pollingClient = new PollingClient({
      baseUrl: this.options.baseUrl,
      token: this.options.token,
      apiKey: this.options.apiKey,
      pollInterval: this.options.pollInterval,
      fetch: this.options.fetch,
    })

    sub.client = pollingClient

    const removeEvent = pollingClient.onEvent((event) => {
      const currentSub = this.channels.get(channel)
      if (currentSub) {
        for (const cb of currentSub.callbacks.values()) {
          try { cb(event) } catch { /* swallow */ }
        }
      }
    })
    sub.cleanups.push(removeEvent)

    pollingClient.connect(channel)
  }

  private _reconnectChannel(channel: string): void {
    const sub = this.channels.get(channel)
    if (!sub) return

    const callbacks = new Map(sub.callbacks)
    this.unsubscribe(channel)

    // Re-subscribe all callbacks
    for (const [, cb] of callbacks) {
      this.subscribe(channel, cb)
    }
  }
}
