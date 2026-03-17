import { describe, it, expect } from 'vitest'
import { NumenClient } from '../../src/core/client.js'
import { TranslationsResource } from '../../src/resources/translations.js'

describe('TranslationsResource', () => {
  it('is wired into NumenClient', () => {
    const mockFetch = () => Promise.resolve(new Response('{}'))
    const client = new NumenClient({ baseUrl: 'https://api.test', fetch: mockFetch as typeof fetch })
    expect(client.translations).toBeInstanceOf(TranslationsResource)
  })
})
