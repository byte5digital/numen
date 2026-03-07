<template>
  <div class="numen-ask-widget rounded-xl border border-indigo-100 bg-indigo-50 p-4">
    <div class="mb-3 flex items-center gap-2">
      <svg class="h-5 w-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.346.346a1.5 1.5 0 01-2.122 0l-.346-.346A5 5 0 018.464 16.1z" />
      </svg>
      <span class="text-sm font-semibold text-indigo-800">Ask AI</span>
    </div>

    <!-- Previous answer -->
    <div v-if="answer" class="mb-4 rounded-lg bg-white p-3 text-sm text-gray-700 shadow-sm">
      <div class="prose prose-sm max-w-none" v-html="formattedAnswer" />

      <!-- Sources -->
      <div v-if="sources.length > 0" class="mt-3 border-t pt-3">
        <p class="mb-1 text-xs font-medium text-gray-500">Sources</p>
        <ul class="space-y-1">
          <li v-for="source in sources" :key="source.url" class="text-xs">
            <a :href="source.url" class="text-indigo-600 hover:underline">{{ source.title }}</a>
          </li>
        </ul>
      </div>

      <!-- Follow-up suggestions -->
      <div v-if="followUps.length > 0" class="mt-3 border-t pt-3">
        <p class="mb-2 text-xs font-medium text-gray-500">Continue asking:</p>
        <div class="flex flex-wrap gap-2">
          <button
            v-for="followUp in followUps"
            :key="followUp"
            class="rounded-full bg-indigo-100 px-3 py-1 text-xs text-indigo-700 hover:bg-indigo-200"
            @click="askQuestion(followUp)"
          >
            {{ followUp }}
          </button>
        </div>
      </div>
    </div>

    <!-- Question input -->
    <form @submit.prevent="askQuestion(currentQuestion)">
      <div class="flex gap-2">
        <input
          v-model="currentQuestion"
          type="text"
          placeholder="Ask a question about the content…"
          class="flex-1 rounded-lg border border-indigo-200 bg-white px-3 py-2 text-sm focus:border-indigo-400 focus:outline-none"
          :disabled="loading"
          maxlength="500"
        />
        <button
          type="submit"
          class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50"
          :disabled="loading || !currentQuestion.trim()"
        >
          <svg v-if="loading" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
          </svg>
          <span v-else>Ask</span>
        </button>
      </div>
    </form>

    <p v-if="error" class="mt-2 text-xs text-red-600">{{ error }}</p>
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'

interface Source {
  id: string
  title: string
  url: string
  relevance: number
}

const props = withDefaults(defineProps<{
  apiUrl?: string
  spaceId?: string
}>(), {
  apiUrl: '/api/v1',
  spaceId: 'default',
})

const currentQuestion = ref('')
const answer = ref('')
const sources = ref<Source[]>([])
const followUps = ref<string[]>([])
const loading = ref(false)
const error = ref('')
const conversationId = ref<string | null>(null)

const formattedAnswer = computed(() => {
  // Simple markdown-like rendering: bold citations
  return answer.value
    .replace(/\[(\d+)\]/g, '<sup class="text-indigo-600 font-bold">[$1]</sup>')
    .replace(/\n/g, '<br>')
})

async function askQuestion(question: string) {
  if (!question.trim() || loading.value) return

  currentQuestion.value = question
  loading.value = true
  error.value = ''

  try {
    const res = await fetch(`${props.apiUrl}/search/ask`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
      body: JSON.stringify({
        question: question.trim(),
        space_id: props.spaceId,
        conversation_id: conversationId.value,
      }),
    })

    if (!res.ok) throw new Error(`HTTP ${res.status}`)

    const data = await res.json()
    answer.value = data.answer ?? ''
    sources.value = data.sources ?? []
    followUps.value = data.follow_ups ?? []
    conversationId.value = data.conversation_id ?? null
    currentQuestion.value = ''

  } catch (e: unknown) {
    error.value = 'Failed to get an answer. Please try again.'
    console.error(e)
  } finally {
    loading.value = false
  }
}
</script>
