<script setup lang="ts">
import { ref, computed, onMounted } from "vue"
import { router } from "@inertiajs/vue3"

interface Template {
  id: string; name: string; slug: string
  description: string | null; category: string | null
  icon: string | null; is_published: boolean
  author_name: string | null; downloads_count: number
  space_id: string | null; average_rating: number
  latest_version: { id: string; version: string; definition: Record<string, unknown> } | null
}

const props = defineProps<{ spaceId: string }>()
const templates = ref<Template[]>([])
const loading = ref(false)
const error = ref<string | null>(null)
const search = ref("")
const selectedCategory = ref("")
const sortBy = ref<"popular" | "rating" | "newest">("popular")
const selectedTemplate = ref<Template | null>(null)

const categories = [
  { value: "", label: "All" },
  { value: "blog", label: "Blog" },
  { value: "social_media", label: "Social Media" },
  { value: "seo", label: "SEO" },
  { value: "ecommerce", label: "E-Commerce" },
  { value: "newsletter", label: "Newsletter" },
  { value: "technical", label: "Technical" },
]

function xsrfToken(): string {
  const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/)
  return match ? decodeURIComponent(match[1]) : ""
}

async function fetchTemplates(): Promise<void> {
  loading.value = true
  error.value = null
  try {
    await fetch("/sanctum/csrf-cookie", { credentials: "include" })
    const res = await fetch(`/api/v1/spaces/${props.spaceId}/pipeline-templates`, {
      credentials: "include",
      headers: { Accept: "application/json", "X-XSRF-TOKEN": xsrfToken() },
    })
    if (!res.ok) throw new Error(`HTTP ${res.status}`)
    const data = await res.json()
    templates.value = (data.data ?? data).filter((t: Template) => !t.space_id && t.is_published)
  } catch (e: unknown) {
    error.value = e instanceof Error ? e.message : "Failed to load"
  } finally { loading.value = false }
}


const filteredTemplates = computed(() => {
  let list = [...templates.value]
  if (search.value) {
    const q = search.value.toLowerCase()
    list = list.filter((t) => t.name.toLowerCase().includes(q) || (t.description ?? '').toLowerCase().includes(q))
  }
  if (selectedCategory.value) list = list.filter((t) => t.category === selectedCategory.value)
  if (sortBy.value === 'popular') list.sort((a, b) => b.downloads_count - a.downloads_count)
  if (sortBy.value === 'rating') list.sort((a, b) => b.average_rating - a.average_rating)
  return list
})

function stageCount(t: Template): number {
  const def = t.latest_version?.definition as { stages?: unknown[] } | null
  return def?.stages?.length ?? 0
}

function starClass(n: number, rating: number): string {
  return n <= Math.round(rating) ? 'text-yellow-400' : 'text-gray-700'
}

function openDetail(t: Template): void { selectedTemplate.value = t }
function closeDetail(): void { selectedTemplate.value = null }
function goToInstall(id: string): void {
  router.visit(`/admin/pipeline-templates/install?template=${id}`)
}

onMounted(fetchTemplates)
</script>

<template>
  <div class="p-6">
    <div class="mb-8">
      <h1 class="text-2xl font-bold text-white">Template Marketplace</h1>
      <p class="text-gray-500 mt-1">Browse and install community pipeline templates</p>
    </div>
    <div class="flex flex-wrap items-center gap-3 mb-6">
      <input v-model="search" type="text" placeholder="Search marketplace..."
        class="px-3 py-2 bg-gray-900 border border-gray-800 rounded-lg text-sm text-gray-300 placeholder-gray-600 focus:outline-none focus:border-indigo-500 w-60" />
      <div class="flex gap-1 bg-gray-900 p-1 rounded-lg border border-gray-800">
        <button v-for="cat in categories" :key="cat.value"
          class="px-3 py-1 rounded text-xs font-medium transition"
          :class="selectedCategory === cat.value ? 'bg-indigo-600 text-white' : 'text-gray-500 hover:text-gray-300'"
          @click="selectedCategory = cat.value">
          {{ cat.label }}
        </button>
      </div>
      <select v-model="sortBy"
        class="px-3 py-2 bg-gray-900 border border-gray-800 rounded-lg text-sm text-gray-300 focus:outline-none focus:border-indigo-500 ml-auto">
        <option value="popular">Most Popular</option>
        <option value="rating">Top Rated</option>
        <option value="newest">Newest</option>
      </select>
    </div>
    <div v-if="loading" class="flex justify-center py-20">
      <div class="w-8 h-8 border-2 border-indigo-500 border-t-transparent rounded-full animate-spin" />
    </div>
    <div v-else-if="error" class="bg-red-900/20 border border-red-800 rounded-xl p-6 text-red-400 text-sm">{{ error }}</div>
    <div v-else-if="!filteredTemplates.length" class="text-center py-20 text-gray-600">
      <p class="text-lg">No templates found</p>
    </div>
    <div v-else class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
      <div v-for="template in filteredTemplates" :key="template.id"
        class="bg-gray-900 border border-gray-800 rounded-xl p-5 hover:border-gray-700 transition-colors cursor-pointer group"
        @click="openDetail(template)">
        <div class="flex items-start gap-3 mb-3">
          <span v-if="template.icon" class="text-2xl">{{ template.icon }}</span>
          <div v-else class="w-10 h-10 rounded-lg bg-indigo-900/40 border border-indigo-800 flex items-center justify-center text-indigo-400 font-bold text-sm flex-shrink-0">
            {{ template.name.charAt(0).toUpperCase() }}
          </div>
          <div class="flex-1 min-w-0">
            <p class="font-semibold text-white text-sm group-hover:text-indigo-300 transition-colors">{{ template.name }}</p>
            <p class="text-xs text-gray-500">{{ template.category ?? 'Uncategorized' }}</p>
          </div>
        </div>
        <p class="text-gray-400 text-sm mb-4 line-clamp-2">{{ template.description ?? 'No description.' }}</p>
        <div class="flex items-center justify-between text-xs text-gray-600 mb-4">
          <div class="flex gap-0.5">
            <span v-for="n in 5" :key="n" :class="starClass(n, template.average_rating)">&#9733;</span>
            <span class="ml-1">{{ template.average_rating.toFixed(1) }}</span>
          </div>
          <span>{{ stageCount(template) }} stages</span>
          <span>{{ template.downloads_count }} installs</span>
        </div>
        <div class="flex gap-2">
          <button class="flex-1 px-3 py-1.5 text-xs bg-indigo-600 text-white rounded-lg hover:bg-indigo-500 transition font-medium"
            @click.stop="goToInstall(template.id)">Install</button>
          <button class="px-3 py-1.5 text-xs bg-gray-800 text-gray-400 rounded-lg hover:bg-gray-700 transition"
            @click.stop="openDetail(template)">Details</button>
        </div>
      </div>
    </div>

    <Teleport to="body">
      <div v-if="selectedTemplate" class="fixed inset-0 bg-black/70 backdrop-blur-sm z-50 flex items-center justify-center p-4"
        @click.self="closeDetail">
        <div class="bg-gray-950 border border-gray-800 rounded-2xl w-full max-w-2xl max-h-[90vh] overflow-y-auto">
          <div class="p-6">
            <div class="flex items-start justify-between mb-6">
              <div class="flex items-center gap-4">
                <span v-if="selectedTemplate.icon" class="text-4xl">{{ selectedTemplate.icon }}</span>
                <div v-else class="w-14 h-14 rounded-xl bg-indigo-900/40 border border-indigo-800 flex items-center justify-center text-indigo-400 font-bold text-xl">
                  {{ selectedTemplate.name.charAt(0).toUpperCase() }}
                </div>
                <div>
                  <h2 class="text-xl font-bold text-white">{{ selectedTemplate.name }}</h2>
                  <p class="text-sm text-gray-500">by {{ selectedTemplate.author_name ?? 'Unknown' }}</p>
                </div>
              </div>
              <button class="text-gray-600 hover:text-gray-400 transition" @click="closeDetail">&#x2715;</button>
            </div>
            <p class="text-gray-300 mb-6">{{ selectedTemplate.description ?? 'No description.' }}</p>
            <div class="grid grid-cols-3 gap-4 mb-6">
              <div class="bg-gray-900 rounded-xl p-4 text-center">
                <p class="text-2xl font-bold text-white">{{ selectedTemplate.downloads_count }}</p>
                <p class="text-xs text-gray-500 mt-1">Installs</p>
              </div>
              <div class="bg-gray-900 rounded-xl p-4 text-center">
                <p class="text-2xl font-bold text-white">{{ selectedTemplate.average_rating.toFixed(1) }}</p>
                <p class="text-xs text-gray-500 mt-1">Rating</p>
              </div>
              <div class="bg-gray-900 rounded-xl p-4 text-center">
                <p class="text-2xl font-bold text-white">{{ stageCount(selectedTemplate) }}</p>
                <p class="text-xs text-gray-500 mt-1">Stages</p>
              </div>
            </div>
            <div class="flex gap-3">
              <button class="flex-1 px-6 py-3 bg-indigo-600 text-white rounded-xl hover:bg-indigo-500 transition font-medium"
                @click="goToInstall(selectedTemplate!.id)">
                Install Template
              </button>
              <button class="px-6 py-3 bg-gray-900 text-gray-400 rounded-xl hover:bg-gray-800 transition" @click="closeDetail">
                Close
              </button>
            </div>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>
