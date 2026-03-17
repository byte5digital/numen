/**
 * Resource error responses: 404, 422 validation, 403 forbidden, 429 rate limit.
 */
import { describe, it, expect, vi } from 'vitest'
import { NumenClient } from '../../src/core/client.js'
import {
  NumenNotFoundError,
  NumenValidationError,
  NumenAuthError,
  NumenRateLimitError,
  NumenError,
} from '../../src/core/errors.js'

function mockFetchError(status: number, body: unknown, headers: Record<string, string> = {}) {
  return vi.fn().mockResolvedValue(
    new Response(JSON.stringify(body), {
      status,
      headers: { 'Content-Type': 'application/json', ...headers },
    })
  )
}

describe('Resource error responses', () => {
  describe('404 Not Found', () => {
    it('content.get() throws NumenNotFoundError', async () => {
      const mockFetch = mockFetchError(404, { message: 'Content not found' })
      const client = new NumenClient({ baseUrl: 'https://api.test', fetch: mockFetch })
      await expect(client.content.get('nonexistent')).rejects.toThrow(NumenNotFoundError)
    })

    it('pages.get() throws NumenNotFoundError', async () => {
      const mockFetch = mockFetchError(404, { message: 'Page not found' })
      const client = new NumenClient({ baseUrl: 'https://api.test', fetch: mockFetch })
      await expect(client.pages.get('missing-page')).rejects.toThrow(NumenNotFoundError)
    })

    it('media.get() throws NumenNotFoundError', async () => {
      const mockFetch = mockFetchError(404, { message: 'Media not found' })
      const client = new NumenClient({ baseUrl: 'https://api.test', fetch: mockFetch })
      await expect(client.media.get('bad-id')).rejects.toThrow(NumenNotFoundError)
    })
  })

  describe('422 Validation Error', () => {
    it('content.create() throws NumenValidationError with fields', async () => {
      const body = { message: 'Validation failed', errors: { title: ['The title field is required.'], type: ['Invalid type.'] } }
      const mockFetch = mockFetchError(422, body)
      const client = new NumenClient({ baseUrl: 'https://api.test', fetch: mockFetch })
      try {
        await client.content.create({ title: '', type: '' })
        expect.fail('Should throw')
      } catch (err) {
        expect(err).toBeInstanceOf(NumenValidationError)
        const ve = err as NumenValidationError
        expect(ve.fields.title).toContain('The title field is required.')
        expect(ve.fields.type).toContain('Invalid type.')
      }
    })

    it('media.update() throws NumenValidationError', async () => {
      const mockFetch = mockFetchError(422, { message: 'Bad input', errors: { alt: ['Too long'] } })
      const client = new NumenClient({ baseUrl: 'https://api.test', fetch: mockFetch })
      try {
        await client.media.update('m1', { alt: 'x'.repeat(1000) })
        expect.fail('Should throw')
      } catch (err) {
        expect(err).toBeInstanceOf(NumenValidationError)
        expect((err as NumenValidationError).fields.alt).toBeDefined()
      }
    })
  })

  describe('403 Forbidden', () => {
    it('admin resource throws NumenAuthError on 403', async () => {
      const mockFetch = mockFetchError(403, { message: 'Forbidden: insufficient permissions' })
      const client = new NumenClient({ baseUrl: 'https://api.test', fetch: mockFetch })
      await expect(client.admin.roles()).rejects.toThrow(NumenAuthError)
    })

    it('content.delete() throws NumenAuthError on 403', async () => {
      const mockFetch = mockFetchError(403, { message: 'Cannot delete published content' })
      const client = new NumenClient({ baseUrl: 'https://api.test', fetch: mockFetch })
      await expect(client.content.delete('c1')).rejects.toThrow(NumenAuthError)
    })
  })

  describe('429 Rate Limit', () => {
    it('throws NumenRateLimitError with retryAfter', async () => {
      const mockFetch = vi.fn().mockResolvedValue(
        new Response(JSON.stringify({ message: 'Too many requests' }), {
          status: 429,
          headers: { 'Content-Type': 'application/json', 'Retry-After': '60' },
        })
      )
      const client = new NumenClient({ baseUrl: 'https://api.test', fetch: mockFetch })
      try {
        await client.content.list()
        expect.fail('Should throw')
      } catch (err) {
        expect(err).toBeInstanceOf(NumenRateLimitError)
        expect((err as NumenRateLimitError).retryAfter).toBe(60)
      }
    })

    it('search throws NumenRateLimitError', async () => {
      const mockFetch = vi.fn().mockResolvedValue(
        new Response(JSON.stringify({ message: 'Slow down' }), {
          status: 429,
          headers: { 'Content-Type': 'application/json', 'Retry-After': '30' },
        })
      )
      const client = new NumenClient({ baseUrl: 'https://api.test', fetch: mockFetch })
      await expect(client.search.search({ q: 'test' })).rejects.toThrow(NumenRateLimitError)
    })
  })

  describe('5xx Server Errors', () => {
    it('500 throws generic NumenError', async () => {
      const mockFetch = mockFetchError(500, { message: 'Internal Server Error' })
      const client = new NumenClient({ baseUrl: 'https://api.test', fetch: mockFetch })
      try {
        await client.content.list()
        expect.fail('Should throw')
      } catch (err) {
        expect(err).toBeInstanceOf(NumenError)
        expect((err as NumenError).status).toBe(500)
      }
    })

    it('502 throws NumenError with correct status', async () => {
      const mockFetch = mockFetchError(502, { message: 'Bad Gateway' })
      const client = new NumenClient({ baseUrl: 'https://api.test', fetch: mockFetch })
      try {
        await client.content.list()
        expect.fail('Should throw')
      } catch (err) {
        expect((err as NumenError).status).toBe(502)
      }
    })
  })
})
