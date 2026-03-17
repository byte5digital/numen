/**
 * NumenProvider — React context for NumenClient.
 */

import { createContext, createElement, useContext } from 'react'
import type { ReactNode } from 'react'
import { NumenClient } from '../core/client.js'
import type { NumenClientOptions } from '../types/sdk.js'

const NumenContext = createContext<NumenClient | null>(null)

export interface NumenProviderProps {
  /** Pre-built client instance */
  client?: NumenClient
  /** Shorthand: API key (creates client internally) */
  apiKey?: string
  /** Shorthand: base URL (creates client internally) */
  baseUrl?: string
  /** Additional client options when using apiKey/baseUrl shorthand */
  options?: Omit<NumenClientOptions, 'baseUrl' | 'apiKey'>
  children: ReactNode
}

/**
 * Provides a NumenClient instance to the React tree.
 *
 * @example
 * ```tsx
 * <NumenProvider client={client}>{children}</NumenProvider>
 * // or
 * <NumenProvider apiKey="sk-..." baseUrl="https://api.numen.ai">{children}</NumenProvider>
 * ```
 */
export function NumenProvider({ client, apiKey, baseUrl, options, children }: NumenProviderProps) {
  const resolvedClient =
    client ??
    new NumenClient({
      baseUrl: baseUrl ?? '',
      apiKey,
      ...options,
    })

  return createElement(NumenContext.Provider, { value: resolvedClient }, children)
}

/**
 * Access the NumenClient from context.
 * Must be used within a `<NumenProvider>`.
 */
export function useNumenClient(): NumenClient {
  const client = useContext(NumenContext)
  if (!client) {
    throw new Error('[numen/sdk] useNumenClient must be used within a <NumenProvider>')
  }
  return client
}
