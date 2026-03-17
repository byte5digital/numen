/**
 * @numen/sdk — Auth middleware
 * Wraps fetch to attach Bearer tokens and handle single-flight token refresh.
 */

export interface AuthMiddlewareOptions {
  /** Returns the current token (or null/undefined if not set) */
  getToken: () => string | null | undefined
  /** Called when the server returns 401; should return a new token or throw */
  onTokenExpired?: () => Promise<string>
}

type FetchFn = typeof globalThis.fetch

/**
 * Creates a fetch wrapper that:
 * 1. Attaches `Authorization: Bearer <token>` to every request.
 * 2. On 401, calls `onTokenExpired` once (single-flight mutex), updates the
 *    token, and retries the original request exactly one time.
 */
export function createAuthMiddleware(options: AuthMiddlewareOptions): (inner: FetchFn) => FetchFn {
  const { getToken, onTokenExpired } = options

  return (inner: FetchFn): FetchFn => {
    // Single-flight mutex: only one concurrent refresh at a time
    let refreshPromise: Promise<string> | null = null

    const fetchWithAuth: FetchFn = async (input, init) => {
      const token = getToken()

      const makeHeaders = (t: string | null | undefined): HeadersInit => {
        const existing = new Headers(init?.headers)
        if (t) {
          existing.set('Authorization', `Bearer ${t}`)
        }
        return existing
      }

      // Initial request
      const response = await inner(input, { ...init, headers: makeHeaders(token) })

      // If not 401 or no refresh handler, return as-is
      if (response.status !== 401 || !onTokenExpired) {
        return response
      }

      // Single-flight: if a refresh is already in-flight, wait for it
      if (!refreshPromise) {
        refreshPromise = onTokenExpired().finally(() => {
          refreshPromise = null
        })
      }

      let newToken: string
      try {
        newToken = await refreshPromise
      } catch {
        // Refresh failed — return original 401 response
        return response
      }

      // Retry with the new token (one time only)
      return inner(input, { ...init, headers: makeHeaders(newToken) })
    }

    return fetchWithAuth
  }
}
