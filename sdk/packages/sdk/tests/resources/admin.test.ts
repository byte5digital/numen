import { describe, it, expect, vi } from 'vitest'
import { NumenClient } from '../../src/core/client.js'

function createMockClient(responseData: unknown = {}, status = 200) {
  const mockFetch = vi.fn().mockResolvedValue(
    new Response(JSON.stringify(responseData), {
      status,
      headers: { 'Content-Type': 'application/json' },
    }),
  )
  return {
    client: new NumenClient({ baseUrl: 'https://api.test', fetch: mockFetch }),
    mockFetch,
  }
}

describe('AdminResource', () => {
  it('roles() calls GET /v1/roles', async () => {
    const { client, mockFetch } = createMockClient({ data: [] })
    await client.admin.roles()
    const url = new URL(mockFetch.mock.calls[0][0])
    expect(url.pathname).toBe('/v1/roles')
  })

  it('createRole() calls POST /v1/roles', async () => {
    const { client, mockFetch } = createMockClient({ data: { id: 'r1' } })
    await client.admin.createRole({ name: 'editor' })
    expect(mockFetch.mock.calls[0][1].method).toBe('POST')
  })

  it('updateRole() calls PUT /v1/roles/:id', async () => {
    const { client, mockFetch } = createMockClient({ data: { id: 'r1' } })
    await client.admin.updateRole('r1', { name: 'admin' })
    expect(mockFetch.mock.calls[0][1].method).toBe('PUT')
    const url = new URL(mockFetch.mock.calls[0][0])
    expect(url.pathname).toBe('/v1/roles/r1')
  })

  it('deleteRole() calls DELETE /v1/roles/:id', async () => {
    const mockFetch = vi.fn().mockResolvedValue(new Response(null, { status: 204 }))
    const client = new NumenClient({ baseUrl: 'https://api.test', fetch: mockFetch })
    await client.admin.deleteRole('r1')
    expect(mockFetch.mock.calls[0][1].method).toBe('DELETE')
  })

  it('permissions() calls GET /v1/permissions', async () => {
    const { client, mockFetch } = createMockClient({ data: [] })
    await client.admin.permissions()
    const url = new URL(mockFetch.mock.calls[0][0])
    expect(url.pathname).toBe('/v1/permissions')
  })

  it('userRoles() calls GET /v1/users/:id/roles', async () => {
    const { client, mockFetch } = createMockClient({ data: [] })
    await client.admin.userRoles('u1')
    const url = new URL(mockFetch.mock.calls[0][0])
    expect(url.pathname).toBe('/v1/users/u1/roles')
  })

  it('assignRole() calls POST /v1/users/:id/roles', async () => {
    const { client, mockFetch } = createMockClient({ data: {} })
    await client.admin.assignRole('u1', { role: 'editor' })
    expect(mockFetch.mock.calls[0][1].method).toBe('POST')
  })

  it('revokeRole() calls DELETE /v1/users/:id/roles/:roleId', async () => {
    const mockFetch = vi.fn().mockResolvedValue(new Response(null, { status: 204 }))
    const client = new NumenClient({ baseUrl: 'https://api.test', fetch: mockFetch })
    await client.admin.revokeRole('u1', 'r1')
    expect(mockFetch.mock.calls[0][1].method).toBe('DELETE')
    const url = new URL(mockFetch.mock.calls[0][0])
    expect(url.pathname).toBe('/v1/users/u1/roles/r1')
  })

  it('auditLogs() calls GET /v1/audit-logs', async () => {
    const data = { data: [], meta: { total: 0, page: 1, perPage: 10, lastPage: 1 } }
    const { client, mockFetch } = createMockClient(data)
    await client.admin.auditLogs()
    const url = new URL(mockFetch.mock.calls[0][0])
    expect(url.pathname).toBe('/v1/audit-logs')
  })

  it('searchHealth() calls GET /v1/admin/search/health', async () => {
    const { client, mockFetch } = createMockClient({ data: {} })
    await client.admin.searchHealth()
    const url = new URL(mockFetch.mock.calls[0][0])
    expect(url.pathname).toBe('/v1/admin/search/health')
  })

  it('searchReindex() calls POST /v1/admin/search/reindex', async () => {
    const { client, mockFetch } = createMockClient({ data: {} })
    await client.admin.searchReindex()
    expect(mockFetch.mock.calls[0][1].method).toBe('POST')
  })
})
