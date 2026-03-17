# Vue 3 Guide

## Setup

Install the plugin in your Vue app:

```ts
import { createApp } from 'vue'
import { NumenPlugin } from '@numen/sdk/vue'

const app = createApp(App)
app.use(NumenPlugin, {
  baseUrl: 'https://api.numen.ai',
  apiKey: 'your-key',
})
app.mount('#app')
```

## Composables

All composables return reactive refs: `{ data, error, isLoading, refresh }`.

### useContent

```vue
<script setup>
import { useContent } from '@numen/sdk/vue'

const { data, isLoading, error } = useContent('article-id')
</script>

<template>
  <div v-if="isLoading">Loading...</div>
  <div v-else-if="error">Error: {{ error.message }}</div>
  <h1 v-else>{{ data?.title }}</h1>
</template>
```

### useContentList

```vue
<script setup>
import { useContentList } from '@numen/sdk/vue'

const { data, isLoading } = useContentList({ status: 'published' })
</script>

<template>
  <ul v-if="!isLoading">
    <li v-for="item in data?.data" :key="item.id">{{ item.title }}</li>
  </ul>
</template>
```

### useSearch

```vue
<script setup>
import { ref } from 'vue'
import { useSearch } from '@numen/sdk/vue'

const query = ref('machine learning')
const { data, isLoading } = useSearch({ query })
</script>

<template>
  <input v-model="query" placeholder="Search..." />
  <ul v-if="!isLoading">
    <li v-for="hit in data?.hits" :key="hit.id">{{ hit.title }}</li>
  </ul>
</template>
```

### useMedia

```vue
<script setup>
import { useMedia } from '@numen/sdk/vue'

const { data } = useMedia('media-id')
</script>

<template>
  <img v-if="data" :src="data.url" :alt="data.alt" />
</template>
```

### usePage / usePageList

```vue
<script setup>
import { usePageList } from '@numen/sdk/vue'

const { data } = usePageList()
</script>

<template>
  <nav>
    <a v-for="page in data?.data" :key="page.id" :href="page.slug">
      {{ page.title }}
    </a>
  </nav>
</template>
```

### usePipeline

```vue
<script setup>
import { usePipeline } from '@numen/sdk/vue'

const { data } = usePipeline('run-id')
</script>

<template>
  <span>Status: {{ data?.status }}</span>
</template>
```

### useRealtime

```vue
<script setup>
import { useRealtime } from '@numen/sdk/vue'

const { events, connectionState } = useRealtime('content.*')
</script>

<template>
  <p>Connection: {{ connectionState }}</p>
  <ul>
    <li v-for="(event, i) in events" :key="i">
      {{ event.type }}: {{ JSON.stringify(event.data) }}
    </li>
  </ul>
</template>
```

## Reactive Parameters

Vue composables accept both raw values and refs. When a ref changes, the query automatically re-fetches:

```vue
<script setup>
import { ref } from 'vue'
import { useContent } from '@numen/sdk/vue'

const contentId = ref('article-1')
const { data } = useContent(contentId)

// Changing contentId triggers a new fetch
contentId.value = 'article-2'
</script>
```

## TypeScript

All composables are fully typed with generics matching resource types.
