<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { router } from '@inertiajs/vue3'

interface TemplateVersion {
  id: string
  version: string
  is_latest: boolean
  definition: Record<string, unknown>
}

interface Template {
  id: string
  name: string
  slug: string
  description: string | null
  category: string | null
  icon: string | null
  is_published: boolean
  author_name: string | null
  downloads_count: number
  space_id: string | null
  latest_version: TemplateVersion | null
  average_rating: number
}

const props = defineProps<{ spaceId: string }>()

const templates = ref<Template[]>([])
const loading = ref(false)
const search = ref('')
const selectedCategory = ref('')
const activeTab = ref<'library' | 'marketplace'>('library')
const error = ref<string | null>(null)

const categories = [
  { value: '', label: 'All Categories' },
  { value: 'blog', label: 'Blog' },
  { value: 'social_media', label: 'Social Media' },
  { value: 'seo', label: 'SEO' },
  { value: 'ecommerce', label: 'E-Commerce' },
  { value: 'newsletter', label: 'Newsletter' },
  { value: 'technical', label: 'Technical' },
  { value: 'custom', label: 'Custom' },
]

function xsrfToken(): string {
  const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/)
  return match ? decodeURIComponent(match[1]) : ''
}

async function fetchTemplates(): Promise<void> {
  loading.value = true
  error.value = null
  try {
    await fetch('/sanctum/csrf-cookie', { credentials: 'include' })
    const res = await fetch(`/api/v1/spaces/${props.spaceId}/pipeline-templates`, {
      credentials: 'include',
      headers: { Accept: 'application/json', 'X-XSRF-TOKEN': xsrfToken() },
    })
    if (!res.ok) throw new Error(`HTTP ${res.status}`)
    const data = await res.json()
    templates.value = data.data ?? data
  } catch (e: unknown) {
    error.value = e instanceof Error ? e.message : 'Failed to load templates'
  } finally {
    loading.value = false
  }
}

async function deleteTemplate(id: string): Promise<void> {
  if (!confirm('Delete this template?')) return
  try {
    await fetch(`/api/v1/spaces/${props.spaceId}/pipeline-templates/${id}`, {
      method: 'DELETE',
      credentials: 'include',
      headers: { 'X-XSRF-TOKEN': xsrfToken() },
    })
    templates.value = templates.value.filter((t) => t.id !== id)
  } catch {
    alert('Failed to delete template')
  }
}

async function publishTemplate(id: string): Promise<void> {
  try {
    await fetch(`/api/v1/spaces/${props.spaceId}/pipeline-templates/${id}/publish`, {
      method: 'POST',
      credentials: 'include',
      headers: { 'X-XSRF-TOKEN': xsrfToken() },
    })
    await fetchTemplates()
  } catch {
    alert('Failed to publish template')
  }
}

const filteredTemplates = computed(() => {
  let list = templates.value
  if (activeTab.value === 'marketplace') {
    list = list.filter((t) => t.is_published && !t.space_id)
  } else {
    list = list.filter((t) => !!t.space_id)
  }
  if (search.value) {
    const q = search.value.toLowerCase()
    list = list.filter(
      (t) =>
        t.name.toLowerCase().includes(q) ||
        (t.description ?? '').toLowerCase().includes(q),
    )
  }
  if (selectedCategory.value) {
    list = list.filter((t) => t.category === selectedCategory.value)
  }
  return list
})

function categoryLabel(cat: string | null): string {
  return categories.find((c) => c.value === cat)?.label ?? cat ?? 'Uncategorized'
}

function stageCount(t: Template): number {
  const def = t.latest_version?.definition as { stages?: unknown[] } | null
  return def?.stages?.length ?? 0
}

function goToEditor(templateId?: string): void {
  if (templateId) {
    router.visit(`/admin/pipeline-templates/${templateId}/edit`)
  } else {
    router.visit('/admin/pipeline-templates/create')
  }
}

function starClass(n: number, rating: number): string {
  return n <= Math.round(rating) ? 'text-yellow-400' : 'text-gray-700'
}

onMounted(fetchTemplates)
</script>

<template>
  <div class="p-6">
    <!-- Header -->
    <div class="flex items-center justify-between mb-8">
      <div>
        <h1 class="text-2xl font-bold text-white">Pipeline Templates</h1>
        <p class="text-gray-500 mt-1">Reusable AI pipeline configurations</p>
      </div>
      <button
        class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-500 transition font-medium text-sm"
        @click="goToEditor()"
      >
        + New Template
      </button>
    </div>

    <!-- Tabs -->
    <div class="flex gap-1 mb-6 bg-gray-900 p-1 rounded-xl w-fit border border-gray-800">
      <button
        v-for="tab in (['library', 'marketplace'] as const)"
        :key="tab"
        class="px-5 py-2 rounded-lg text-sm font-medium capitalize transition"
        :class="activeTab === tab
          ? 'bg-indigo-600 text-white'
          : 'text-gray-400 hover:text-white'"
        @click="activeTab = tab"
      >
        {{ tab }}
      </button>
    </div>

    <!-- Filters -->
    <div class="flex flex-wrap gap-3 mb-6">
      <input
        v-model="search"
        type="text"
        placeholder="Search templates…"
        class="px-3 py-2 bg-gray-900 border border-gray-800 rounded-lg text-sm text-gray-300 placeholder-gray-600 focus:outline-none focus:border-indigo-500 w-64"
      />
      <select
        v-model="selectedCategory"
        class="px-3 py-2 bg-gray-900 border border-gray-800 rounded-lg text-sm text-gray-300 focus:outline-none focus:border-indigo-500"
      >
        <option v-for="cat in categories" :key="cat.value" :value="cat.value">
          {{ cat.label }}
        </option>
      </select>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="flex items-center justify-center py-20">
      <div class="w-8 h-8 border-2 border-indigo-500 border-t-transparent rounded-full animate-spin" />
    </div>

    <!-- Error -->
    <div v-else-if="error" class="bg-red-900/20 border border-red-800 rounded-xl p-6 text-red-400 text-sm">
      {{ error }}
    </div>

    <!-- Empty -->
    <div v-else-if="!filteredTemplates.length" class="text-center py-20 text-gray-600">
      <p class="text-lg mb-2">No templates found</p>
      <p v-if="activeTab === 'library'" class="text-sm">
        Create your first template to get started.
      </p>
    </div>

    <!-- Grid -->
    <div v-else class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
      <div
        v-for="template in filteredTemplates"
        :key="template.id"
        class="bg-gray-900 border border-gray-800 rounded-xl p-5 flex flex-col hover:border-gray-700 transition-colors"
      >
        <!-- Top row -->
        <div class="flex items-start justify-between mb-3">
          <div class="flex items-center gap-3">
            <span v-if="template.icon" class="text-2xl">{{ template.icon }}</span>
            <div
              v-else
              class="w-10 h-10 rounded-lg bg-indigo-900/40 border border-indigo-800 flex items-center justify-center text-indigo-400 font-bold text-sm"
            >
              {{ template.name.charAt(0).toUpperCase() }}
            </div>
            <div>
              <p class="font-semibold text-white text-sm leading-tight">{{ template.name }}</p>
              <p class="text-xs text-gray-500">{{ categoryLabel(template.category) }}</p>
            </div>
          </div>
          <span
            v-if="template.is_published"
            class="px-2 py-0.5 text-xs bg-emerald-900/40 text-emerald-400 border border-emerald-800 rounded-full"
          >
            Published
          </span>
        </div>

        <!-- Description -->
        <p class="text-gray-400 text-sm mb-4 flex-1 line-clamp-2">
          {{ template.description ?? 'No description.' }}
        </p>

        <!-- Meta row -->
        <div class="flex items-center justify-between text-xs text-gray-600 mb-4">
          <span>{{ stageCount(template) }} stage{{ stageCount(template) === 1 ? '' : 's' }}</span>
          <span v-if="template.author_name">by {{ template.author_name }}</span>
          <div class="flex gap-0.5">
            <span
              v-for="n in 5"
              :key="n"
              class="text-xs"
              :class="starClass(n, template.average_rating)"
            >★</span>
          </div>
          <span>{{ template.downloads_count }} installs</span>
        </div>

        <!-- Actions -->
        <div class="flex gap-2">
          <button
            v-if="template.space_id"
            class="flex-1 px-3 py-1.5 text-xs bg-indigo-600/20 text-indigo-400 border border-indigo-800 rounded-lg hover:bg-indigo-600/30 transition"
            @click="goToEditor(template.id)"
          >
            Edit
          </button>
          <button
            v-if="template.space_id && !template.is_published"
            class="flex-1 px-3 py-1.5 text-xs bg-emerald-600/20 text-emerald-400 border border-emerald-800 rounded-lg hover:bg-emerald-600/30 transition"
            @click="publishTemplate(template.id)"
          >
            Publish
          </button>
          <button
            v-if="template.space_id"
            class="px-3 py-1.5 text-xs bg-red-600/10 text-red-500 border border-red-900 rounded-lg hover:bg-red-600/20 transition"
            @click="deleteTemplate(template.id)"
          >
            Delete
          </button>
          <button
            v-if="!template.space_id"
            class="flex-1 px-3 py-1.5 text-xs bg-indigo-600/20 text-indigo-400 border border-indigo-800 rounded-lg hover:bg-indigo-600/30 transition"
            @click="router.visit('/admin/pipeline-templates/install?template=' + template.id)"
          >
            Install
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
