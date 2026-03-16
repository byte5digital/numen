/**
 * Placeholder re-exports for generated API types.
 * These will be populated by @numen/sdk-codegen after running the codegen pipeline.
 *
 * Usage: pnpm codegen
 */

// Re-export generated types once codegen has been run
// export type { paths, components, operations } from '../../generated/api'

/**
 * Generic API response wrapper
 */
export interface ApiResponse<T = unknown> {
  data: T
  status: number
  ok: boolean
}

/**
 * Generic paginated response
 */
export interface PaginatedResponse<T = unknown> {
  data: T[]
  meta: {
    total: number
    page: number
    perPage: number
    lastPage: number
  }
}

/**
 * API error response
 */
export interface ApiError {
  message: string
  code?: string
  errors?: Record<string, string[]>
}
