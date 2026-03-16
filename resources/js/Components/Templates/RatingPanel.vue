<script setup lang="ts">
import { ref, onMounted } from 'vue'

interface Rating {
  id: string
  user_id: string
  rating: number
  review: string | null
  created_at: string
  user?: { name?: string }
}

const props = defineProps<{
  spaceId: string
  templateId: string
}>()

const ratings = ref<Rating[]>([])
const loading = ref(false)
const submitting = ref(false)
const userRating = ref(0)
const userReview = ref('')
const submitted = ref(false)
const error = ref<string | null>(null)

function xsrfToken(): string {
  const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/)
  return match ? decodeURIComponent(match[1]) : ''
}

async function fetchRatings(): Promise<void> {
  loading.value = true
  try {
    const res = await fetch(
      `/api/v1/spaces/${props.spaceId}/pipeline-templates/${props.templateId}/ratings`,
      { credentials: 'include', headers: { Accept: 'application/json', 'X-XSRF-TOKEN': xsrfToken() } },
    )
    if (!res.ok) throw new Error(`HTTP ${res.status}`)
    const data = await res.json()
    ratings.value = data.data ?? data
  } catch {
    // silent - ratings are non-critical
  } finally { loading.value = false }
}

async function submitRating(): Promise<void> {
  if (!userRating.value) return
  submitting.value = true
  error.value = null
  try {
    const res = await fetch(
      `/api/v1/spaces/${props.spaceId}/pipeline-templates/${props.templateId}/ratings`,
      {
        method: 'POST',
        credentials: 'include',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-XSRF-TOKEN': xsrfToken() },
        body: JSON.stringify({ rating: userRating.value, review: userReview.value || null }),
      },
    )
    if (!res.ok) {
      const body = await res.json().catch(() => ({}))
      throw new Error((body as { message?: string }).message ?? `HTTP ${res.status}`)
    }
    submitted.value = true
    await fetchRatings()
  } catch (e: unknown) {
    error.value = e instanceof Error ? e.message : 'Failed to submit'
  } finally { submitting.value = false }
}

function setRating(n: number): void { userRating.value = n }

function avgRating(): string {
  if (!ratings.value.length) return '0.0'
  return (ratings.value.reduce((s, r) => s + r.rating, 0) / ratings.value.length).toFixed(1)
}

onMounted(fetchRatings)
</script>

<template>
  <div class="space-y-6">
    <!-- Summary -->
    <div class="flex items-center gap-4">
      <div class="text-center">
        <p class="text-4xl font-bold text-white">{{ avgRating() }}</p>
        <div class="flex gap-0.5 mt-1 justify-center">
          <span v-for="n in 5" :key="n" class="text-sm"
            :class="n <= Math.round(Number(avgRating())) ? 'text-yellow-400' : 'text-gray-700'">&#9733;</span>
        </div>
        <p class="text-xs text-gray-500 mt-1">{{ ratings.length }} reviews</p>
      </div>
      <div class="flex-1">
        <div v-for="star in [5, 4, 3, 2, 1]" :key="star" class="flex items-center gap-2 mb-1">
          <span class="text-xs text-gray-600 w-2">{{ star }}</span>
          <div class="flex-1 h-1.5 bg-gray-800 rounded-full overflow-hidden">
            <div
              class="h-full bg-yellow-500 rounded-full transition-all"
              :style="{ width: ratings.length ? (ratings.filter(r => r.rating === star).length / ratings.length * 100) + '%' : '0%' }"
            />
          </div>
          <span class="text-xs text-gray-600 w-4">{{ ratings.filter(r => r.rating === star).length }}</span>
        </div>
      </div>
    </div>

    <!-- Submit form -->
    <div v-if="!submitted" class="bg-gray-900 border border-gray-800 rounded-xl p-5">
      <h3 class="text-sm font-semibold text-white mb-3">Leave a Review</h3>
      <div class="flex gap-1 mb-3">
        <button
          v-for="n in 5"
          :key="n"
          class="text-2xl transition hover:scale-110"
          :class="n <= userRating ? 'text-yellow-400' : 'text-gray-700'"
          @click="setRating(n)"
        >&#9733;</button>
      </div>
      <textarea v-model="userReview" rows="3"
        class="w-full px-3 py-2 bg-gray-800 border border-gray-700 rounded-lg text-sm text-gray-300 placeholder-gray-600 focus:outline-none focus:border-indigo-500 resize-none mb-3"
        placeholder="Share your experience (optional)..." />
      <div v-if="error" class="text-red-400 text-xs mb-2">{{ error }}</div>
      <button
        class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-500 transition text-sm font-medium disabled:opacity-50"
        :disabled="!userRating || submitting"
        @click="submitRating"
      >
        {{ submitting ? 'Submitting...' : 'Submit Review' }}
      </button>
    </div>
    <div v-else class="bg-emerald-900/20 border border-emerald-800 rounded-xl p-4 text-emerald-400 text-sm">
      Thanks for your review!
    </div>

    <!-- Reviews list -->
    <div v-if="loading" class="flex justify-center py-6">
      <div class="w-6 h-6 border-2 border-indigo-500 border-t-transparent rounded-full animate-spin" />
    </div>
    <div v-else class="space-y-3">
      <div v-for="r in ratings" :key="r.id" class="bg-gray-900 border border-gray-800 rounded-xl p-4">
        <div class="flex items-center justify-between mb-2">
          <div class="flex gap-0.5">
            <span v-for="n in 5" :key="n" class="text-sm"
              :class="n <= r.rating ? 'text-yellow-400' : 'text-gray-700'">&#9733;</span>
          </div>
          <span class="text-xs text-gray-600">{{ r.created_at }}</span>
        </div>
        <p v-if="r.review" class="text-gray-300 text-sm">{{ r.review }}</p>
      </div>
      <div v-if="!ratings.length && !loading" class="text-center py-8 text-gray-600 text-sm">
        No reviews yet. Be the first!
      </div>
    </div>
  </div>
</template>
