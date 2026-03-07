<template>
  <div :class="['numen-search-widget', `theme-${theme}`]" :style="cssVars">
    <!-- Mode toggle -->
    <div class="flex gap-1 mb-3 bg-gray-100 rounded-lg p-1">
      <button
        :class="['flex-1 py-1.5 rounded-md text-xs font-medium transition-all', mode === 'search' ? 'bg-white shadow text-gray-900' : 'text-gray-500 hover:text-gray-700']"
        @click="mode = 'search'"
      >Search</button>
      <button
        v-if="enableAsk"
        :class="['flex-1 py-1.5 rounded-md text-xs font-medium transition-all', mode === 'ask' ? 'bg-white shadow text-gray-900' : 'text-gray-500 hover:text-gray-700']"
        @click="mode = 'ask'"
      >Ask AI</button>
    </div>

    <!-- Search mode -->
    <template v-if="mode === 'search'">
      <div class="relative mb-3">
        <input
          v-model="query"
          type="search"
          :placeholder="`Search ${space}…`"
          class="w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 pl-9 text-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
          @input="debouncedSearch"
          @keydown.enter="doSearch"
        />
        <svg class="pointer-events-none absolute left-2.5 top-3 h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
        </svg>
      </div>

      <div v-if="loading" class="text-center py-4 text-sm text-gray-500">Searching…</div>

      <ul v-else-if="results.length > 0" class="space-y-2">
        <li v-for="(result, i) in results" :key="result.id">
          <a
            :href="result.url"
            class="block rounded-lg p-3 hover:bg-gray-50 transition-colors"
            @click="recordClick(result, i + 1)"
          >
            <div class="flex items-start gap-2">
              <div class="flex-1 min-w-0">
                <p class="text-sm font-medium text-gray-900 truncate">{{ result.title }}</p>
                <p class="text-xs text-gray-500 mt-0.5 line-clamp-2">{{ result.excerpt }}</p>
              </div>
              <span class="shrink-0 rounded bg-indigo-100 px-1.5 py-0.5 text-xs text-indigo-700">{{ result.content_type }}</span>
            </div>
          </a>
        </li>
      </ul>

      <p v-else-if="hasSearched && !loading" class="py-4 text-center text-sm text-gray-400">
        No results for "{{ query }}"
      </p>
    </template>

    <!-- Ask mode -->
    <template v-else>
      <div v-if="answer" class="mb-3 rounded-lg bg-white p-3 text-sm text-gray-700 border shadow-sm">
        <p v-html="formatAnswer(answer)" />
        <div v-if="answerSources.length > 0" class="mt-2 pt-2 border-t">
          <p class="text-xs text-gray-400 mb-1">Sources:</p>
          <div class="space-y-0.5">
            <a v-for="s in answerSources" :key="s.url" :href="s.url" class="block text-xs text-indigo-600 hover:underline">{{ s.title }}</a>
          </div>
        </div>
      </div>

      <form @submit.prevent="doAsk" class="flex gap-2">
        <input
          v-model="question"
          type="text"
          placeholder="Ask anything about the content…"
          class="flex-1 rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none"
          :disabled="askLoading"
          maxlength="500"
        />
        <button
          type="submit"
          class="rounded-lg bg-indigo-600 px-3 py-2 text-white text-sm disabled:opacity-50 hover:bg-indigo-700"
          :disabled="askLoading || !question.trim()"
        >
          {{ askLoading ? '…' : 'Ask' }}
        </button>
      </form>
    </template>
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'

const props = withDefaults(defineProps<{
  apiUrl?: string
  apiKey?: string
  space?: string
  locale?: string
  theme?: string
  enableAsk?: boolean | string
}>(), {
  apiUrl: '/api/v1',
  space: 'default',
  theme: 'light',
  enableAsk: true,
})

const mode = ref<'search' | 'ask'>('search')
const query = ref('')
const question = ref('')
const results = ref<any[]>([])
const answer = ref('')
const answerSources = ref<any[]>([])
const loading = ref(false)
const askLoading = ref(false)
const hasSearched = ref(false)
const conversationId = ref<string | null>(null)

const enableAsk = computed(() => props.enableAsk === true || props.enableAsk === 'true')

const cssVars = computed(() => ({
  '--numen-search-accent': theme.value.accent,
}))

const theme = computed(() => ({
  accent: '#6366f1',
}))

let debounceTimer: ReturnType<typeof setTimeout> | null = null

function debouncedSearch() {
  if (debounceTimer) clearTimeout(debounceTimer)
  debounceTimer = setTimeout(doSearch, 300)
}

async function doSearch() {
  if (!query.value.trim()) {
    results.value = []
    return
  }

  loading.value = true
  hasSearched.value = true

  try {
    const headers: Record<string, string> = { 'Accept': 'application/json' }
    if (props.apiKey) headers['X-API-Key'] = props.apiKey

    const url = `${props.apiUrl}/search?q=${encodeURIComponent(query.value)}&space_id=${props.space}${props.locale ? `&locale=${props.locale}` : ''}`
    const res = await fetch(url, { headers })
    const data = await res.json()
    results.value = data.data ?? []
  } catch {
    results.value = []
  } finally {
    loading.value = false
  }
}

async function doAsk() {
  if (!question.value.trim() || askLoading.value) return

  askLoading.value = true
  try {
    const res = await fetch(`${props.apiUrl}/search/ask`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
      body: JSON.stringify({ question: question.value, space_id: props.space, conversation_id: conversationId.value }),
    })
    const data = await res.json()
    answer.value = data.answer ?? ''
    answerSources.value = data.sources ?? []
    conversationId.value = data.conversation_id ?? null
    question.value = ''
  } catch {
    answer.value = 'Sorry, failed to get an answer.'
  } finally {
    askLoading.value = false
  }
}

function recordClick(result: any, position: number) {
  fetch(`${props.apiUrl}/search/click`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ query: query.value, content_id: result.id, position, space_id: props.space }),
  }).catch(() => {})
}

function formatAnswer(text: string): string {
  return text
    .replace(/\[(\d+)\]/g, '<sup class="font-bold text-indigo-600">[$1]</sup>')
    .replace(/\n/g, '<br>')
}
</script>

<style>
.numen-search-widget {
  font-family: system-ui, -apple-system, sans-serif;
  max-width: 480px;
  padding: 16px;
  background: #f9fafb;
  border-radius: 12px;
}
</style>
