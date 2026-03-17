/**
 * @numen/sdk — Realtime module
 * SSE realtime + polling fallback for Numen channels.
 */

export { RealtimeClient } from './client.js'
export type {
  RealtimeEvent,
  RealtimeEventHandler,
  ConnectionState,
  ConnectionStateHandler,
  ErrorHandler,
  RealtimeClientOptions,
} from './client.js'

export { PollingClient } from './polling.js'
export type { PollingClientOptions } from './polling.js'

export { RealtimeManager } from './manager.js'
export type { RealtimeManagerOptions, SubscriptionCallback } from './manager.js'
