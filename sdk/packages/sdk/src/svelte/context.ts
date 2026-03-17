/**
 * Svelte context for NumenClient.
 *
 * Uses a module-level singleton so stores can access the client
 * without requiring Svelte component context (setContext/getContext).
 * This makes stores usable outside components too.
 */

import { NumenClient } from '../core/client.js'

let _client: NumenClient | null = null

/**
 * Set the NumenClient instance for all Svelte stores.
 * Call this once at app initialization.
 *
 * @example
 * ```ts
 * import { setNumenClient } from '@numen/sdk/svelte'
 * import { NumenClient } from '@numen/sdk'
 *
 * const client = new NumenClient({ baseUrl: 'https://api.numen.ai', apiKey: 'sk-...' })
 * setNumenClient(client)
 * ```
 */
export function setNumenClient(client: NumenClient): void {
  _client = client
}

/**
 * Get the NumenClient instance.
 * Throws if `setNumenClient` hasn't been called.
 */
export function getNumenClient(): NumenClient {
  if (!_client) {
    throw new Error('[numen/sdk] getNumenClient: call setNumenClient(client) before using Svelte stores')
  }
  return _client
}

/**
 * Reset client (useful for testing).
 * @internal
 */
export function _resetNumenClient(): void {
  _client = null
}
