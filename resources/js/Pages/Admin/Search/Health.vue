<template>
  <div class="p-6">
    <h1 class="mb-6 text-2xl font-bold text-gray-900">Search Index Health</h1>

    <!-- Status badges -->
    <div class="mb-6 grid grid-cols-3 gap-4">
      <div class="rounded-xl bg-white p-4 shadow-sm">
        <div class="flex items-center gap-2">
          <span :class="['h-3 w-3 rounded-full', health.capabilities?.instant ? 'bg-green-500' : 'bg-red-500']" />
          <span class="text-sm font-medium">Meilisearch (Tier 1)</span>
        </div>
        <p class="mt-1 text-xs text-gray-500">{{ health.capabilities?.instant ? 'Connected' : 'Unavailable' }}</p>
      </div>
      <div class="rounded-xl bg-white p-4 shadow-sm">
        <div class="flex items-center gap-2">
          <span :class="['h-3 w-3 rounded-full', health.capabilities?.semantic ? 'bg-green-500' : 'bg-red-500']" />
          <span class="text-sm font-medium">pgvector (Tier 2)</span>
        </div>
        <p class="mt-1 text-xs text-gray-500">{{ health.capabilities?.semantic ? 'Available' : 'Unavailable' }}</p>
      </div>
      <div class="rounded-xl bg-white p-4 shadow-sm">
        <div class="flex items-center gap-2">
          <span :class="['h-3 w-3 rounded-full', health.capabilities?.ask ? 'bg-green-500' : 'bg-red-500']" />
          <span class="text-sm font-medium">RAG / Ask (Tier 3)</span>
        </div>
        <p class="mt-1 text-xs text-gray-500">{{ health.capabilities?.ask ? 'Available' : 'Unavailable' }}</p>
      </div>
    </div>

    <!-- Stats -->
    <div class="mb-6 rounded-xl bg-white p-4 shadow-sm">
      <h2 class="mb-3 text-sm font-semibold text-gray-700">Index Statistics</h2>
      <dl class="grid grid-cols-2 gap-4 text-sm">
        <div>
          <dt class="text-gray-500">Total Embeddings</dt>
          <dd class="font-bold">{{ health.embeddings_count ?? 0 }}</dd>
        </div>
        <div>
          <dt class="text-gray-500">Embedding Model</dt>
          <dd class="font-bold font-mono text-xs">{{ health.embedding_model ?? '—' }}</dd>
        </div>
        <div>
          <dt class="text-gray-500">Last Re-indexed</dt>
          <dd class="font-bold">{{ health.last_reindex ? formatDate(health.last_reindex) : 'Never' }}</dd>
        </div>
      </dl>
    </div>

    <!-- Re-index -->
    <div class="rounded-xl bg-white p-4 shadow-sm">
      <h2 class="mb-3 text-sm font-semibold text-gray-700">Re-index Content</h2>
      <p class="mb-4 text-xs text-gray-500">
        Re-index all published content. This dispatches jobs to the search queue — it won't block the UI.
      </p>
      <button
        class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50"
        :disabled="reindexing"
        @click="triggerReindex"
      >
        {{ reindexing ? 'Dispatching…' : '⟳ Re-index All Content' }}
      </button>
      <p v-if="reindexResult" class="mt-2 text-xs text-green-700">{{ reindexResult }}</p>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'

const health = ref<Record<string, any>>({})
const reindexing = ref(false)
const reindexResult = ref('')

async function load() {
  try {
    const res = await fetch('/api/v1/admin/search/health')
    const data = await res.json()
    health.value = data.data ?? {}
  } catch (e) {
    console.error(e)
  }
}

async function triggerReindex() {
  reindexing.value = true
  reindexResult.value = ''
  try {
    const res = await fetch('/api/v1/admin/search/reindex', { method: 'POST' })
    const data = await res.json()
    reindexResult.value = data.data?.message ?? 'Re-index dispatched.'
    await load()
  } catch (e) {
    reindexResult.value = 'Failed to trigger re-index.'
  } finally {
    reindexing.value = false
  }
}

function formatDate(iso: string): string {
  try {
    return new Date(iso).toLocaleString()
  } catch {
    return iso
  }
}

onMounted(load)
</script>
