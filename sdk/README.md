# @numen/sdk

> Typed Frontend SDK for the Numen AI Content Platform

[![TypeScript](https://img.shields.io/badge/TypeScript-5.4+-blue.svg)](https://www.typescriptlang.org/)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)

A fully typed, tree-shakeable SDK for interacting with the Numen API. Includes first-class bindings for **React**, **Vue 3**, and **Svelte**, plus realtime subscriptions via SSE.

## Installation

```bash
# npm
npm install @numen/sdk

# pnpm
pnpm add @numen/sdk

# yarn
yarn add @numen/sdk
```

## Quick Start

### Core Client (Framework-Agnostic)

```ts
import { NumenClient } from '@numen/sdk'

const client = new NumenClient({
  baseUrl: 'https://api.numen.ai',
  apiKey: 'your-api-key',
})

// Fetch content
const articles = await client.content.list({ status: 'published' })
const article = await client.content.get('article-id')

// Search
const results = await client.search.search({ query: 'machine learning' })
```

### React

```tsx
import { NumenProvider, useContent, useContentList, useSearch } from '@numen/sdk/react'

function App() {
  return (
    <NumenProvider baseUrl="https://api.numen.ai" apiKey="your-key">
      <ArticleList />
    </NumenProvider>
  )
}

function ArticleList() {
  const { data, isLoading } = useContentList({ status: 'published' })
  if (isLoading) return <p>Loading...</p>
  return data?.data.map(article => <div key={article.id}>{article.title}</div>)
}
```

### Vue 3

```vue
<script setup>
import { useContentList } from '@numen/sdk/vue'

const { data, isLoading } = useContentList({ status: 'published' })
</script>

<template>
  <div v-if="isLoading">Loading...</div>
  <div v-for="article in data?.data" :key="article.id">
    {{ article.title }}
  </div>
</template>
```

### Svelte

```svelte
<script>
  import { createContentListStore } from '@numen/sdk/svelte'

  const articles = createContentListStore({ status: 'published' })
</script>

{#if $articles.isLoading}
  <p>Loading...</p>
{:else}
  {#each $articles.data?.data ?? [] as article}
    <div>{article.title}</div>
  {/each}
{/if}
```

### Realtime Subscriptions

```ts
import { RealtimeManager } from '@numen/sdk'

const realtime = new RealtimeManager({
  baseUrl: 'https://api.numen.ai',
  token: 'your-token',
})

realtime.subscribe('content.*', (event) => {
  console.log('Content changed:', event)
})
```

## Features

- **16 Resource Modules** — Content, Pages, Media, Search, Versions, Taxonomies, Briefs, Pipeline, Webhooks, Graph, Chat, Admin, Competitor, Quality, Repurpose, Translations
- **React Hooks** — `useContent`, `useContentList`, `useSearch`, `useMedia`, `usePipeline`, `useRealtime`
- **Vue Composables** — Same API surface, reactive refs
- **Svelte Stores** — Readable store factories for every resource
- **Realtime** — SSE client with auto-reconnect + polling fallback
- **SWR Cache** — Stale-while-revalidate caching built in
- **Tree-Shakeable** — Import only what you use
- **Fully Typed** — Complete TypeScript definitions

## Documentation

- [Getting Started](./docs/getting-started.md)
- [React Guide](./docs/react.md)
- [Vue Guide](./docs/vue.md)
- [Svelte Guide](./docs/svelte.md)
- [Realtime](./docs/realtime.md)
- [API Reference](./docs/api-reference.md)
- [Security Guide](./docs/security.md)

## License

MIT © [byte5digital](https://github.com/byte5digital)
