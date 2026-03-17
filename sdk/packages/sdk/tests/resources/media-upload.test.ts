/**
 * Media upload edge cases: multipart form data, metadata.
 */
import { describe, it, expect, vi } from 'vitest'
import { NumenClient } from '../../src/core/client.js'

describe('Media upload', () => {
  it('upload() sends FormData with file', async () => {
    const mockFetch = vi.fn().mockResolvedValue(
      new Response(JSON.stringify({ data: { id: 'm1', filename: 'test.jpg', mime_type: 'image/jpeg', size: 1024, url: '/media/m1' } }), {
        status: 200,
        headers: { 'Content-Type': 'application/json' },
      })
    )
    const client = new NumenClient({ baseUrl: 'https://api.test', fetch: mockFetch })
    const file = new File(['test content'], 'test.jpg', { type: 'image/jpeg' })
    const result = await client.media.upload(file)
    expect(result.data.id).toBe('m1')
    expect(mockFetch).toHaveBeenCalledOnce()
    const url = new URL(mockFetch.mock.calls[0][0])
    expect(url.pathname).toBe('/v1/media')
    expect(mockFetch.mock.calls[0][1].method).toBe('POST')
  })

  it('upload() sends metadata fields', async () => {
    const mockFetch = vi.fn().mockResolvedValue(
      new Response(JSON.stringify({ data: { id: 'm2' } }), {
        status: 200,
        headers: { 'Content-Type': 'application/json' },
      })
    )
    const client = new NumenClient({ baseUrl: 'https://api.test', fetch: mockFetch })
    const file = new File(['data'], 'photo.png', { type: 'image/png' })
    await client.media.upload(file, { title: 'My Photo', alt: 'A nice photo', folder_id: 'folder-1' })
    expect(mockFetch).toHaveBeenCalledOnce()
  })

  it('upload() works with Blob instead of File', async () => {
    const mockFetch = vi.fn().mockResolvedValue(
      new Response(JSON.stringify({ data: { id: 'm3' } }), {
        status: 200,
        headers: { 'Content-Type': 'application/json' },
      })
    )
    const client = new NumenClient({ baseUrl: 'https://api.test', fetch: mockFetch })
    const blob = new Blob(['binary data'], { type: 'application/pdf' })
    const result = await client.media.upload(blob)
    expect(result.data.id).toBe('m3')
  })

  it('upload() without optional metadata', async () => {
    const mockFetch = vi.fn().mockResolvedValue(
      new Response(JSON.stringify({ data: { id: 'm4' } }), {
        status: 200,
        headers: { 'Content-Type': 'application/json' },
      })
    )
    const client = new NumenClient({ baseUrl: 'https://api.test', fetch: mockFetch })
    const file = new File(['x'], 'doc.pdf', { type: 'application/pdf' })
    await client.media.upload(file)
    expect(mockFetch).toHaveBeenCalledOnce()
  })
})
