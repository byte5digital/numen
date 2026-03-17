/**
 * Translations resource module.
 * Placeholder — no dedicated translation routes found in the Numen API yet.
 * Provides a stub that can be expanded when endpoints become available.
 */

import type { NumenClient, RequestOptions } from '../core/client.js'

export interface Translation {
  id: string
  content_id: string
  locale: string
  status: string
  [key: string]: unknown
}

export class TranslationsResource {
  constructor(private readonly client: NumenClient) {}

  // Placeholder: No dedicated /v1/translations routes in current API.
  // Will be expanded once translation endpoints are added to the backend.
}
