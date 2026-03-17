# React Guide

## Setup

Wrap your app with `NumenProvider`:

```tsx
import { NumenProvider } from '@numen/sdk/react'

function App() {
  return (
    <NumenProvider baseUrl="https://api.numen.ai" apiKey="your-key">
      <MyApp />
    </NumenProvider>
  )
}
```

## Hooks

All hooks return `{ data, error, isLoading, mutate }`.

### useContent

Fetch a single content item by ID.

```tsx
import { useContent } from '@numen/sdk/react'

function Article({ id }: { id: string }) {
  const { data, isLoading, error } = useContent(id)

  if (isLoading) return <p>Loading...</p>
  if (error) return <p>Error: {error.message}</p>
  return <h1>{data?.title}</h1>
}
```

### useContentList

Fetch a paginated list of content.

```tsx
import { useContentList } from '@numen/sdk/react'

function ArticleList() {
  const { data, isLoading } = useContentList({ status: 'published', page: 1 })

  if (isLoading) return <p>Loading...</p>
  return (
    <ul>
      {data?.data.map(item => (
        <li key={item.id}>{item.title}</li>
      ))}
    </ul>
  )
}
```

### useSearch

```tsx
import { useSearch } from '@numen/sdk/react'

function SearchResults({ query }: { query: string }) {
  const { data, isLoading } = useSearch({ query })

  if (isLoading) return <p>Searching...</p>
  return (
    <ul>
      {data?.hits.map(hit => (
        <li key={hit.id}>{hit.title}</li>
      ))}
    </ul>
  )
}
```

### useMedia

```tsx
import { useMedia } from '@numen/sdk/react'

function MediaViewer({ id }: { id: string }) {
  const { data } = useMedia(id)
  return data ? <img src={data.url} alt={data.alt} /> : null
}
```

### usePage / usePageList

```tsx
import { usePage, usePageList } from '@numen/sdk/react'

function PageNav() {
  const { data } = usePageList()
  return (
    <nav>
      {data?.data.map(page => <a key={page.id} href={page.slug}>{page.title}</a>)}
    </nav>
  )
}
```

### usePipeline

```tsx
import { usePipeline } from '@numen/sdk/react'

function PipelineStatus({ runId }: { runId: string }) {
  const { data } = usePipeline(runId)
  return <span>Status: {data?.status}</span>
}
```

### useRealtime

Subscribe to realtime events from within a component.

```tsx
import { useRealtime } from '@numen/sdk/react'

function LiveUpdates() {
  const { events, connectionState } = useRealtime('content.*')

  return (
    <div>
      <p>Connection: {connectionState}</p>
      <ul>
        {events.map((e, i) => (
          <li key={i}>{e.type}: {JSON.stringify(e.data)}</li>
        ))}
      </ul>
    </div>
  )
}
```

## Mutation Pattern

Hooks expose a `mutate` function for optimistic updates:

```tsx
const { data, mutate } = useContent('article-id')

async function handleUpdate(title: string) {
  // Optimistically update local state
  mutate({ ...data!, title }, false)
  // Persist to server
  await client.content.update('article-id', { title })
  // Revalidate
  mutate()
}
```

## TypeScript

All hooks are fully typed. Return types match the corresponding resource:

```ts
const { data } = useContent('id')    // data: ContentItem | undefined
const { data } = useSearch({ query }) // data: SearchResponse | undefined
const { data } = useMedia('id')      // data: MediaAsset | undefined
```
