/**
 * Error class tests: mapResponseToError, error hierarchy, properties.
 */
import { describe, it, expect } from 'vitest'
import {
  NumenError,
  NumenAuthError,
  NumenNotFoundError,
  NumenValidationError,
  NumenRateLimitError,
  NumenNetworkError,
  mapResponseToError,
} from '../../src/core/errors.js'

describe('Error classes', () => {
  it('NumenError has correct properties', () => {
    const err = new NumenError('test', 500, 'API_ERROR', { detail: 'x' })
    expect(err.message).toBe('test')
    expect(err.status).toBe(500)
    expect(err.code).toBe('API_ERROR')
    expect(err.body).toEqual({ detail: 'x' })
    expect(err.name).toBe('NumenError')
    expect(err).toBeInstanceOf(Error)
    expect(err).toBeInstanceOf(NumenError)
  })

  it('NumenAuthError extends NumenError', () => {
    const err = new NumenAuthError('Forbidden', 403, null)
    expect(err).toBeInstanceOf(NumenError)
    expect(err).toBeInstanceOf(NumenAuthError)
    expect(err.status).toBe(403)
    expect(err.code).toBe('AUTH_ERROR')
    expect(err.name).toBe('NumenAuthError')
  })

  it('NumenNotFoundError has status 404', () => {
    const err = new NumenNotFoundError('not found', null)
    expect(err.status).toBe(404)
    expect(err.code).toBe('NOT_FOUND')
    expect(err.name).toBe('NumenNotFoundError')
  })

  it('NumenValidationError includes fields', () => {
    const fields = { title: ['required'], slug: ['too_long'] }
    const err = new NumenValidationError('Validation failed', null, fields)
    expect(err.status).toBe(422)
    expect(err.fields).toEqual(fields)
    expect(err.name).toBe('NumenValidationError')
  })

  it('NumenRateLimitError includes retryAfter', () => {
    const err = new NumenRateLimitError('Too many requests', null, 45)
    expect(err.status).toBe(429)
    expect(err.retryAfter).toBe(45)
    expect(err.name).toBe('NumenRateLimitError')
  })

  it('NumenNetworkError has status 0 and cause', () => {
    const cause = new TypeError('fetch failed')
    const err = new NumenNetworkError('Network error', cause)
    expect(err.status).toBe(0)
    expect(err.code).toBe('NETWORK_ERROR')
    expect(err.cause).toBe(cause)
    expect(err.name).toBe('NumenNetworkError')
  })
})

describe('mapResponseToError', () => {
  function makeResponse(status: number, body: unknown, headers: Record<string, string> = {}) {
    return new Response(JSON.stringify(body), {
      status,
      headers: { 'Content-Type': 'application/json', ...headers },
    })
  }

  it('maps 401 to NumenAuthError', async () => {
    const err = await mapResponseToError(makeResponse(401, { message: 'Unauthorized' }))
    expect(err).toBeInstanceOf(NumenAuthError)
    expect(err.message).toBe('Unauthorized')
  })

  it('maps 403 to NumenAuthError', async () => {
    const err = await mapResponseToError(makeResponse(403, { message: 'Forbidden' }))
    expect(err).toBeInstanceOf(NumenAuthError)
  })

  it('maps 404 to NumenNotFoundError', async () => {
    const err = await mapResponseToError(makeResponse(404, { message: 'Not found' }))
    expect(err).toBeInstanceOf(NumenNotFoundError)
  })

  it('maps 422 with errors to NumenValidationError', async () => {
    const body = { message: 'Validation', errors: { title: ['required'] } }
    const err = await mapResponseToError(makeResponse(422, body))
    expect(err).toBeInstanceOf(NumenValidationError)
    expect((err as NumenValidationError).fields).toEqual({ title: ['required'] })
  })

  it('maps 422 without errors object to NumenValidationError with empty fields', async () => {
    const err = await mapResponseToError(makeResponse(422, { message: 'Bad input' }))
    expect(err).toBeInstanceOf(NumenValidationError)
    expect((err as NumenValidationError).fields).toEqual({})
  })

  it('maps 429 with Retry-After header', async () => {
    const err = await mapResponseToError(
      makeResponse(429, { message: 'Rate limited' }, { 'Retry-After': '120' })
    )
    expect(err).toBeInstanceOf(NumenRateLimitError)
    expect((err as NumenRateLimitError).retryAfter).toBe(120)
  })

  it('maps 429 without Retry-After to default 60', async () => {
    const err = await mapResponseToError(makeResponse(429, { message: 'Rate limited' }))
    expect(err).toBeInstanceOf(NumenRateLimitError)
    expect((err as NumenRateLimitError).retryAfter).toBe(60)
  })

  it('maps unknown status to generic NumenError', async () => {
    const err = await mapResponseToError(makeResponse(503, { message: 'Service down' }))
    expect(err).toBeInstanceOf(NumenError)
    expect(err).not.toBeInstanceOf(NumenAuthError)
    expect(err.status).toBe(503)
  })

  it('handles non-JSON response body gracefully', async () => {
    const res = new Response('not json', { status: 500, headers: { 'Content-Type': 'text/plain' } })
    const err = await mapResponseToError(res)
    expect(err).toBeInstanceOf(NumenError)
    expect(err.message).toBe('HTTP 500')
  })

  it('uses fallback message when body has no message field', async () => {
    const err = await mapResponseToError(makeResponse(400, { error: 'something' }))
    expect(err.message).toBe('HTTP 400')
  })
})
