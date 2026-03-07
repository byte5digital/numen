<template>
  <div class="numen-search-bar relative" ref="container">
    <div class="relative">
      <input
        ref="inputRef"
        v-model="query"
        type="search"
        :placeholder="placeholder"
        class="w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 pl-10 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
        autocomplete="off"
        @input="onInput"
        @keydown="onKeydown"
        @focus="showSuggestions = suggestions.length > 0"
        @blur="hideSuggestions"
      />
      <div class="pointer-events-none absolute inset-y-0 left-3 flex items-center">
        <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
        </svg>
      </div>
      <button
        v-if="query"
        class="absolute inset-y-0 right-3 flex items-center text-gray-400 hover:text-gray-600"
        @click="clear"
      >
        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
        </svg>
      </button>
    </div>

    <!-- Autocomplete dropdown -->
    <div
      v-if="showSuggestions && suggestions.length > 0"
      class="absolute z-50 mt-1 w-full rounded-lg border border-gray-200 bg-white py-1 shadow-lg"
    >
      <button
        v-for="(suggestion, i) in suggestions"
        :key="i"
        class="flex w-full items-center px-4 py-2 text-left text-sm hover:bg-gray-50"
        :class="{ 'bg-indigo-50': i === highlightedIndex }"
        @mousedown.prevent="selectSuggestion(suggestion)"
      >
        <svg class="mr-2 h-3 w-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
        </svg>
        {{ suggestion }}
      </button>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onUnmounted } from 'vue'

const props = withDefaults(defineProps<{
  placeholder?: string
  apiUrl?: string
  spaceId?: string
  debounceMs?: number
}>(), {
  placeholder: 'Search…',
  apiUrl: '/api/v1',
  spaceId: 'default',
  debounceMs: 150,
})

const emit = defineEmits<{
  search: [query: string]
  suggest: [suggestions: string[]]
}>()

const query = ref('')
const suggestions = ref<string[]>([])
const showSuggestions = ref(false)
const highlightedIndex = ref(-1)
const inputRef = ref<HTMLInputElement>()
const container = ref<HTMLDivElement>()

let debounceTimer: ReturnType<typeof setTimeout> | null = null

function onInput() {
  highlightedIndex.value = -1

  if (debounceTimer) clearTimeout(debounceTimer)

  if (query.value.length < 2) {
    suggestions.value = []
    showSuggestions.value = false
    return
  }

  debounceTimer = setTimeout(() => {
    fetchSuggestions(query.value)
  }, props.debounceMs)
}

async function fetchSuggestions(q: string) {
  try {
    const url = `${props.apiUrl}/search/suggest?q=${encodeURIComponent(q)}&space_id=${props.spaceId}&limit=5`
    const res = await fetch(url)
    const data = await res.json()
    suggestions.value = data.suggestions ?? []
    showSuggestions.value = suggestions.value.length > 0
    emit('suggest', suggestions.value)
  } catch {
    suggestions.value = []
  }
}

function onKeydown(e: KeyboardEvent) {
  if (e.key === 'ArrowDown') {
    e.preventDefault()
    highlightedIndex.value = Math.min(highlightedIndex.value + 1, suggestions.value.length - 1)
  } else if (e.key === 'ArrowUp') {
    e.preventDefault()
    highlightedIndex.value = Math.max(highlightedIndex.value - 1, -1)
  } else if (e.key === 'Enter') {
    if (highlightedIndex.value >= 0 && suggestions.value[highlightedIndex.value]) {
      selectSuggestion(suggestions.value[highlightedIndex.value])
    } else {
      submitSearch()
    }
  } else if (e.key === 'Escape') {
    hideSuggestions()
  }
}

function selectSuggestion(suggestion: string) {
  query.value = suggestion
  hideSuggestions()
  submitSearch()
}

function submitSearch() {
  if (query.value.trim()) {
    emit('search', query.value.trim())
  }
}

function clear() {
  query.value = ''
  suggestions.value = []
  showSuggestions.value = false
  inputRef.value?.focus()
}

function hideSuggestions() {
  setTimeout(() => {
    showSuggestions.value = false
  }, 150)
}

onUnmounted(() => {
  if (debounceTimer) clearTimeout(debounceTimer)
})
</script>
