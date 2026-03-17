# Svelte Guide

## Setup

Set the client in your root layout or entry component:

```svelte
<script>
  import { NumenClient } from '@numen/sdk'
  import { setNumenClient } from '@numen/sdk/svelte'

  const client = new NumenClient({
    baseUrl: 'https://api.numen.ai',
    apiKey: 'your-key',
  })

  setNumenClient(client)
</script>

<slot />
```

## Store Factories

Each factory returns a Svelte readable store with `{ data, error, isLoading }`.

### createContentStore

```svelte
<script>
  import { createContentStore } from '@numen/sdk/svelte'

  const article = createContentStore('article-id')
</script>

{#if $article.isLoading}
  <p>Loading...</p>
{:else if $article.error}
  <p>Error: {$article.error.message}</p>
{:else}
  <h1>{$article.data?.title}</h1>
{/if}
```

### createContentListStore

```svelte
<script>
  import { createContentListStore } from '@numen/sdk/svelte'

  const articles = createContentListStore({ status: 'published' })
</script>

{#if $articles.isLoading}
  <p>Loading...</p>
{:else}
  {#each $articles.data?.data ?? [] as item}
    <div>{item.title}</div>
  {/each}
{/if}
```

### createSearchStore

```svelte
<script>
  import { createSearchStore } from '@numen/sdk/svelte'

  const results = createSearchStore({ query: 'machine learning' })
</script>

{#each $results.data?.hits ?? [] as hit}
  <div>{hit.title}</div>
{/each}
```

### createMediaStore

```svelte
<script>
  import { createMediaStore } from '@numen/sdk/svelte'

  const asset = createMediaStore('media-id')
</script>

{#if $asset.data}
  <img src={$asset.data.url} alt={$asset.data.alt} />
{/if}
```

### createPageStore / createPageListStore

```svelte
<script>
  import { createPageListStore } from '@numen/sdk/svelte'

  const pages = createPageListStore()
</script>

<nav>
  {#each $pages.data?.data ?? [] as page}
    <a href={page.slug}>{page.title}</a>
  {/each}
</nav>
```

### createPipelineStore

```svelte
<script>
  import { createPipelineStore } from '@numen/sdk/svelte'

  const run = createPipelineStore('run-id')
</script>

<span>Status: {$run.data?.status}</span>
```

### createRealtimeStore

```svelte
<script>
  import { createRealtimeStore } from '@numen/sdk/svelte'

  const live = createRealtimeStore('content.*')
</script>

<p>Connection: {$live.connectionState}</p>
{#each $live.events as event}
  <div>{event.type}: {JSON.stringify(event.data)}</div>
{/each}
```

## TypeScript

All store factories are generic-typed. The store value matches the corresponding resource type.
