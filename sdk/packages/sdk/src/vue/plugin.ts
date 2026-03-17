/**
 * NumenPlugin — Vue 3 plugin for NumenClient.
 */

import { inject, type App, type InjectionKey } from 'vue'
import { NumenClient } from '../core/client.js'
import type { NumenClientOptions } from '../types/sdk.js'

export const NumenClientKey: InjectionKey<NumenClient> = Symbol('NumenClient')

export interface NumenPluginOptions {
  /** Pre-built client instance */
  client?: NumenClient
  /** Shorthand: API key (creates client internally) */
  apiKey?: string
  /** Shorthand: base URL (creates client internally) */
  baseUrl?: string
  /** Additional client options when using apiKey/baseUrl shorthand */
  options?: Omit<NumenClientOptions, 'baseUrl' | 'apiKey'>
}

/**
 * Vue 3 plugin that provides NumenClient to the app.
 *
 * @example
 * ```ts
 * app.use(NumenPlugin, { client })
 * // or
 * app.use(NumenPlugin, { apiKey: 'sk-...', baseUrl: 'https://api.numen.ai' })
 * ```
 */
export const NumenPlugin = {
  install(app: App, pluginOptions: NumenPluginOptions) {
    const client =
      pluginOptions.client ??
      new NumenClient({
        baseUrl: pluginOptions.baseUrl ?? '',
        apiKey: pluginOptions.apiKey,
        ...pluginOptions.options,
      })

    app.provide(NumenClientKey, client)
  },
}

/**
 * Access the NumenClient from the Vue inject context.
 * Must be used within a component tree where NumenPlugin is installed.
 */
export function useNumenClient(): NumenClient {
  const client = inject(NumenClientKey)
  if (!client) {
    throw new Error('[numen/sdk] useNumenClient must be used in a component where NumenPlugin is installed')
  }
  return client
}
