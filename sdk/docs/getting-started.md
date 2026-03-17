# Getting Started with @numen/sdk

## Installation

```bash
npm install @numen/sdk
# or
pnpm add @numen/sdk
# or
yarn add @numen/sdk
```

## Creating a Client

```ts
import { NumenClient } from '@numen/sdk'

const client = new NumenClient({
  baseUrl: 'https://api.numen.ai',
  apiKey: 'your-api-key',
})
```

### Configuration Options

| Option | Type | Required | Description |
|--------|------|----------|-------------|
| `baseUrl` | `string` | ✅ | Numen API base URL |
| `apiKey` | `string` | — | API key authentication |
| `token` | `string` | — | Bearer token authentication |
| `cache` | `CacheOptions` | — | SWR cache configuration |
| `timeout` | `number` | — | Request timeout in ms |

### Cache Options

```ts
const client = new NumenClient({
  baseUrl: 'https://api.numen.ai',
  apiKey: 'key',
  cache: {
    ttl: 60_000,        // Cache TTL in ms (default: 60s)
    maxEntries: 100,     // Max cached entries
  },
})
```

## Basic Usage

### Content

```ts
// List content
const articles = await client.content.list({ status: 'published', page: 1, perPage: 20 })

// Get single item
const article = await client.content.get('content-id')

// Create
const newArticle = await client.content.create({
  title: 'My Article',
  body: 'Content here...',
  status: 'draft',
})

// Update
await client.content.update('content-id', { title: 'Updated Title' })

// Delete
await client.content.delete('content-id')
```

### Pages

```ts
const pages = await client.pages.list()
const page = await client.pages.get('page-id')
await client.pages.reorder({ pages: [{ id: 'a', position: 0 }, { id: 'b', position: 1 }] })
```

### Search

```ts
const results = await client.search.search({ query: 'machine learning', page: 1 })
const suggestions = await client.search.suggest('mach')
const answer = await client.search.ask({ question: 'What is our content strategy?' })
```

### Media

```ts
const assets = await client.media.list()
const asset = await client.media.get('media-id')
const uploaded = await client.media.upload(file, { alt: 'Description' })
```

## Error Handling

```ts
import { NumenError, NumenNotFoundError, NumenRateLimitError } from '@numen/sdk'

try {
  const article = await client.content.get('missing-id')
} catch (error) {
  if (error instanceof NumenNotFoundError) {
    console.log('Article not found')
  } else if (error instanceof NumenRateLimitError) {
    console.log(`Rate limited. Retry after ${error.retryAfter}s`)
  } else if (error instanceof NumenError) {
    console.log(`API error: ${error.message} (${error.status})`)
  }
}
```

## Framework Bindings

The SDK provides first-class bindings for React, Vue 3, and Svelte:

- [React Guide](./react.md) — hooks + context provider
- [Vue Guide](./vue.md) — composables + plugin
- [Svelte Guide](./svelte.md) — stores + context

## Realtime

Subscribe to live updates via SSE:

- [Realtime Guide](./realtime.md)

## Next Steps

- [API Reference](./api-reference.md) — full resource documentation
