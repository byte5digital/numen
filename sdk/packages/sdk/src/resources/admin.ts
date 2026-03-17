/**
 * Admin resource module.
 * Users, roles, permissions, audit logs, search admin, plugins.
 */

import type { NumenClient, RequestOptions } from '../core/client.js'
import type { PaginatedResponse } from '../types/api.js'

export interface Role {
  id: string
  name: string
  permissions?: string[]
  [key: string]: unknown
}

export interface AuditLog {
  id: string
  action: string
  user_id?: string
  created_at: string
  [key: string]: unknown
}

export interface RoleCreatePayload {
  name: string
  permissions?: string[]
  [key: string]: unknown
}

export interface RoleUpdatePayload {
  name?: string
  permissions?: string[]
  [key: string]: unknown
}

export class AdminResource {
  constructor(private readonly client: NumenClient) {}

  // ── Roles ──

  /** List roles. */
  async roles(): Promise<{ data: Role[] }> {
    return this.client.request<{ data: Role[] }>('GET', '/v1/roles')
  }

  /** Create a role. */
  async createRole(data: RoleCreatePayload): Promise<{ data: Role }> {
    return this.client.request<{ data: Role }>('POST', '/v1/roles', { body: data })
  }

  /** Update a role. */
  async updateRole(id: string, data: RoleUpdatePayload): Promise<{ data: Role }> {
    return this.client.request<{ data: Role }>('PUT', `/v1/roles/${encodeURIComponent(id)}`, { body: data })
  }

  /** Delete a role. */
  async deleteRole(id: string): Promise<void> {
    return this.client.request<void>('DELETE', `/v1/roles/${encodeURIComponent(id)}`)
  }

  // ── Permissions ──

  /** List permissions. */
  async permissions(): Promise<{ data: unknown[] }> {
    return this.client.request<{ data: unknown[] }>('GET', '/v1/permissions')
  }

  // ── User roles ──

  /** Get roles for a user. */
  async userRoles(userId: string): Promise<{ data: Role[] }> {
    return this.client.request<{ data: Role[] }>('GET', `/v1/users/${encodeURIComponent(userId)}/roles`)
  }

  /** Assign a role to a user. */
  async assignRole(userId: string, data: { role: string }): Promise<{ data: unknown }> {
    return this.client.request<{ data: unknown }>(
      'POST',
      `/v1/users/${encodeURIComponent(userId)}/roles`,
      { body: data },
    )
  }

  /** Revoke a role from a user. */
  async revokeRole(userId: string, roleId: string): Promise<void> {
    return this.client.request<void>(
      'DELETE',
      `/v1/users/${encodeURIComponent(userId)}/roles/${encodeURIComponent(roleId)}`,
    )
  }

  /** List users with a specific role. */
  async roleUsers(roleId: string): Promise<{ data: unknown[] }> {
    return this.client.request<{ data: unknown[] }>('GET', `/v1/roles/${encodeURIComponent(roleId)}/users`)
  }

  // ── Audit logs ──

  /** List audit logs. */
  async auditLogs(): Promise<PaginatedResponse<AuditLog>> {
    return this.client.request<PaginatedResponse<AuditLog>>('GET', '/v1/audit-logs')
  }

  // ── Search admin ──

  /** Get search synonyms. */
  async searchSynonyms(): Promise<{ data: unknown[] }> {
    return this.client.request<{ data: unknown[] }>('GET', '/v1/admin/search/synonyms')
  }

  /** Get search health. */
  async searchHealth(): Promise<{ data: unknown }> {
    return this.client.request<{ data: unknown }>('GET', '/v1/admin/search/health')
  }

  /** Trigger search reindex. */
  async searchReindex(): Promise<{ data: unknown }> {
    return this.client.request<{ data: unknown }>('POST', '/v1/admin/search/reindex')
  }

  /** Get search analytics. */
  async searchAnalytics(): Promise<{ data: unknown }> {
    return this.client.request<{ data: unknown }>('GET', '/v1/admin/search/analytics')
  }
}
