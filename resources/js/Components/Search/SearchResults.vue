<template>
  <div class="numen-search-results">
    <!-- Loading state -->
    <div v-if="loading" class="flex items-center justify-center py-8">
      <svg class="h-6 w-6 animate-spin text-indigo-600" fill="none" viewBox="0 0 24 24">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
      </svg>
      <span class="ml-2 text-sm text-gray-500">Searching…</span>
    </div>

    <!-- Empty state -->
    <div v-else-if="results.length === 0 && query" class="py-8 text-center">
      <p class="text-sm text-gray-500">No results found for <strong>{{ query }}</strong></p>
      <p class="mt-1 text-xs text-gray-400">Try different keywords or browse all content.</p>
    </div>

    <!-- Results -->
    <template v-else>
      <div class="mb-3 flex items-center justify-between text-xs text-gray-400">
        <span>{{ meta.total }} results ({{ meta.tier_used }}, {{ meta.response_time_ms }}ms)</span>
      </div>

      <ul class="divide-y divide-gray-100">
        <li
          v-for="result in results"
          :key="result.id"
          class="py-4"
        >
          <a
            :href="result.url"
            class="group block"
            @click="emit('click', result, results.indexOf(result) + 1)"
          >
            <div class="flex items-start justify-between gap-2">
              <div>
                <div v-if="result.metadata?.promoted" class="mb-1">
                  <span class="rounded bg-amber-100 px-1.5 py-0.5 text-xs font-medium text-amber-800">Featured</span>
                </div>
                <h3
                  class="text-sm font-medium text-gray-900 group-hover:text-indigo-600"
                  v-html="highlight(result.title)"
                />
                <p class="mt-1 text-xs text-gray-500 line-clamp-2" v-html="highlight(result.excerpt)" />
              </div>
              <span class="shrink-0 rounded bg-gray-100 px-1.5 py-0.5 text-xs text-gray-500">
                {{ result.content_type }}
              </span>
            </div>
            <p class="mt-1 text-xs text-gray-400">{{ formatDate(result.published_at) }}</p>
          </a>
        </li>
      </ul>

      <!-- Pagination -->
      <div v-if="meta.total > meta.per_page" class="mt-4 flex justify-center gap-2">
        <button
          v-if="meta.page > 1"
          class="rounded border px-3 py-1.5 text-xs hover:bg-gray-50"
          @click="emit('page', meta.page - 1)"
        >Previous</button>
        <button
          v-if="meta.page * meta.per_page < meta.total"
          class="rounded border px-3 py-1.5 text-xs hover:bg-gray-50"
          @click="emit('page', meta.page + 1)"
        >Next</button>
      </div>
    </template>
  </div>
</template>

<script setup lang="ts">
interface SearchResult {
  id: string
  title: string
  excerpt: string
  url: string
  content_type: string
  score: number
  published_at: string
  highlights: Record<string, string>
  metadata?: Record<string, unknown>
}

interface SearchMeta {
  total: number
  page: number
  per_page: number
  tier_used: string
  response_time_ms: number
}

const props = withDefaults(defineProps<{
  results: SearchResult[]
  meta: SearchMeta
  loading: boolean
  query: string
}>(), {
  results: () => [],
  loading: false,
  query: '',
})

const emit = defineEmits<{
  click: [result: SearchResult, position: number]
  page: [page: number]
}>()

function highlight(text: string): string {
  if (!props.query || !text) return text
  const escaped = props.query.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')
  return text.replace(new RegExp(`(${escaped})`, 'gi'), '<mark class="bg-yellow-100 rounded">$1</mark>')
}

function formatDate(iso: string): string {
  if (!iso) return ''
  try {
    return new Date(iso).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' })
  } catch {
    return iso
  }
}
</script>
