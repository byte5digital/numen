/**
 * @numen/sdk — Error classes
 * All errors thrown by the SDK extend NumenError.
 */

/**
 * Base error class for all Numen SDK errors.
 */
export class NumenError extends Error {
  readonly status: number
  readonly code: string
  readonly body: unknown

  constructor(message: string, status: number, code: string, body: unknown) {
    super(message)
    this.name = 'NumenError'
    this.status = status
    this.code = code
    this.body = body
    // Maintains proper prototype chain in compiled TypeScript
    Object.setPrototypeOf(this, new.target.prototype)
  }
}

/**
 * Thrown when the API returns 429 Too Many Requests.
 */
export class NumenRateLimitError extends NumenError {
  readonly retryAfter: number

  constructor(message: string, body: unknown, retryAfter: number) {
    super(message, 429, 'RATE_LIMITED', body)
    this.name = 'NumenRateLimitError'
    this.retryAfter = retryAfter
    Object.setPrototypeOf(this, new.target.prototype)
  }
}

/**
 * Thrown when the API returns 422 Unprocessable Entity (validation failure).
 */
export class NumenValidationError extends NumenError {
  readonly fields: Record<string, string[]>

  constructor(message: string, body: unknown, fields: Record<string, string[]>) {
    super(message, 422, 'VALIDATION_ERROR', body)
    this.name = 'NumenValidationError'
    this.fields = fields
    Object.setPrototypeOf(this, new.target.prototype)
  }
}

/**
 * Thrown when the API returns 401 Unauthorized or 403 Forbidden.
 */
export class NumenAuthError extends NumenError {
  constructor(message: string, status: number, body: unknown) {
    super(message, status, 'AUTH_ERROR', body)
    this.name = 'NumenAuthError'
    Object.setPrototypeOf(this, new.target.prototype)
  }
}

/**
 * Thrown when the API returns 404 Not Found.
 */
export class NumenNotFoundError extends NumenError {
  constructor(message: string, body: unknown) {
    super(message, 404, 'NOT_FOUND', body)
    this.name = 'NumenNotFoundError'
    Object.setPrototypeOf(this, new.target.prototype)
  }
}

/**
 * Thrown when a network-level failure occurs (e.g., DNS failure, timeout).
 */
export class NumenNetworkError extends NumenError {
  readonly cause: unknown

  constructor(message: string, cause?: unknown) {
    super(message, 0, 'NETWORK_ERROR', null)
    this.name = 'NumenNetworkError'
    this.cause = cause
    Object.setPrototypeOf(this, new.target.prototype)
  }
}

/**
 * Maps an HTTP Response to the appropriate NumenError subclass.
 * Call after confirming !response.ok.
 */
export async function mapResponseToError(response: Response): Promise<NumenError> {
  let body: unknown = null
  try {
    body = await response.json()
  } catch {
    // non-JSON body — keep null
  }

  const message =
    (body && typeof body === 'object' && 'message' in body && typeof (body as Record<string, unknown>).message === 'string')
      ? (body as Record<string, unknown>).message as string
      : `HTTP ${response.status}`

  switch (response.status) {
    case 401:
    case 403:
      return new NumenAuthError(message, response.status, body)

    case 404:
      return new NumenNotFoundError(message, body)

    case 422: {
      const fields: Record<string, string[]> =
        body &&
        typeof body === 'object' &&
        'errors' in body &&
        typeof (body as Record<string, unknown>).errors === 'object' &&
        (body as Record<string, unknown>).errors !== null
          ? ((body as Record<string, unknown>).errors as Record<string, string[]>)
          : {}
      return new NumenValidationError(message, body, fields)
    }

    case 429: {
      const retryAfter = Number(response.headers.get('Retry-After') ?? '60')
      return new NumenRateLimitError(message, body, isNaN(retryAfter) ? 60 : retryAfter)
    }

    default:
      return new NumenError(message, response.status, 'API_ERROR', body)
  }
}
