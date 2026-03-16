<script setup lang="ts">
import { ref, reactive, computed, onMounted } from "vue"
import { router } from "@inertiajs/vue3"
import StageDragList from "@/Components/Templates/StageDragList.vue"

interface Stage {
  _uid: string
  name: string
  type: string
  agent_role: string | null
  model: string | null
  prompt_key: string | null
  timeout_seconds: number | null
  allow_failure: boolean
  human_gate: boolean
  config: Record<string, unknown>
}

interface FormState {
  name: string
  slug: string
  description: string
  category: string
  icon: string
  author_name: string
  author_url: string
  version: string
  changelog: string
  stages: Stage[]
}

const props = defineProps<{ spaceId: string; templateId?: string }>()
const isEdit = computed(() => !!props.templateId)
const saving = ref(false)
const error = ref<string | null>(null)
const success = ref(false)
let stageCounter = 0

const stageTypes = [
  { value: "ai_generate", label: "AI Generate" },
  { value: "ai_illustrate", label: "AI Illustrate" },
  { value: "ai_transform", label: "AI Transform" },
  { value: "ai_review", label: "AI Review" },
  { value: "auto_publish", label: "Auto Publish" },
  { value: "human_gate", label: "Human Gate" },
  { value: "plugin_stage", label: "Plugin Stage" },
]

const categories = [
  { value: "blog", label: "Blog" },
  { value: "social_media", label: "Social Media" },
  { value: "seo", label: "SEO" },
  { value: "ecommerce", label: "E-Commerce" },
  { value: "newsletter", label: "Newsletter" },
  { value: "technical", label: "Technical" },
  { value: "custom", label: "Custom" },
]

const form = reactive<FormState>({
  name: "", slug: "", description: "", category: "blog",
  icon: "", author_name: "", author_url: "",
  version: "1.0.0", changelog: "Initial version", stages: [],
})

function makeStage(type = "ai_generate"): Stage {
  stageCounter++
  return {
    _uid: `stage-${stageCounter}`,
    name: `Stage ${stageCounter}`,
    type, agent_role: null, model: null, prompt_key: null,
    timeout_seconds: null, allow_failure: false,
    human_gate: type === "human_gate", config: {},
  }
}

function addStage(): void { form.stages.push(makeStage()) }
function removeStage(uid: string): void {
  form.stages = form.stages.filter((s) => s._uid !== uid)
}


function xsrfToken(): string {
  const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/)
  return match ? decodeURIComponent(match[1]) : ''
}

function buildDefinition() {
  return {
    schema_version: '1.0',
    stages: form.stages.map(({ _uid, ...rest }) => rest),
  }
}

async function loadTemplate(): Promise<void> {
  if (!props.templateId) return
  const res = await fetch(`/api/v1/spaces/${props.spaceId}/pipeline-templates/${props.templateId}`, {
    credentials: 'include',
    headers: { Accept: 'application/json', 'X-XSRF-TOKEN': xsrfToken() },
  })
  if (!res.ok) throw new Error(`HTTP ${res.status}`)
  const data = await res.json()
  const t = data.data ?? data
  form.name = t.name
  form.slug = t.slug
  form.description = t.description ?? ''
  form.category = t.category ?? 'blog'
  form.icon = t.icon ?? ''
  form.author_name = t.author_name ?? ''
  form.author_url = t.author_url ?? ''
  const def = t.latest_version?.definition
  if (def?.stages) {
    form.stages = (def.stages as Record<string, unknown>[]).map((s: Record<string, unknown>, idx: number) => ({
      _uid: `stage-${++stageCounter}`,
      name: String(s.name ?? `Stage ${idx + 1}`),
      type: String(s.type ?? 'ai_generate'),
      agent_role: (s.agent_role as string) ?? null,
      model: (s.model as string) ?? null,
      prompt_key: (s.prompt_key as string) ?? null,
      timeout_seconds: (s.timeout_seconds as number) ?? null,
      allow_failure: Boolean(s.allow_failure),
      human_gate: Boolean(s.human_gate),
      config: (s.config as Record<string, unknown>) ?? {},
    }))
  }
}

async function save(): Promise<void> {
  saving.value = true
  error.value = null
  success.value = false
  try {
    await fetch('/sanctum/csrf-cookie', { credentials: 'include' })
    const payload = {
      name: form.name, slug: form.slug || undefined,
      description: form.description || null, category: form.category,
      icon: form.icon || null, author_name: form.author_name || null,
      author_url: form.author_url || null, version: form.version,
      changelog: form.changelog || null, definition: buildDefinition(),
    }
    const url = isEdit.value
      ? `/api/v1/spaces/${props.spaceId}/pipeline-templates/${props.templateId}`
      : `/api/v1/spaces/${props.spaceId}/pipeline-templates`
    const res = await fetch(url, {
      method: isEdit.value ? 'PATCH' : 'POST',
      credentials: 'include',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-XSRF-TOKEN': xsrfToken() },
      body: JSON.stringify(payload),
    })
    if (!res.ok) {
      const body = await res.json().catch(() => ({}))
      throw new Error((body as { message?: string }).message ?? `HTTP ${res.status}`)
    }
    success.value = true
    setTimeout(() => router.visit('/admin/pipeline-templates'), 800)
  } catch (e: unknown) {
    error.value = e instanceof Error ? e.message : 'Save failed'
  } finally { saving.value = false }
}

function slugify(val: string): string {
  return val.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '')
}
function onNameInput(): void { if (!isEdit.value) form.slug = slugify(form.name) }

function stageColorClass(type: string): string {
  const map: Record<string, string> = {
    ai_generate: 'bg-indigo-900/30 border-indigo-700 text-indigo-300',
    ai_illustrate: 'bg-pink-900/30 border-pink-700 text-pink-300',
    ai_transform: 'bg-purple-900/30 border-purple-700 text-purple-300',
    ai_review: 'bg-amber-900/30 border-amber-700 text-amber-300',
    auto_publish: 'bg-emerald-900/30 border-emerald-700 text-emerald-300',
    human_gate: 'bg-gray-800 border-gray-700 text-gray-300',
    plugin_stage: 'bg-blue-900/30 border-blue-700 text-blue-300',
  }
  return map[type] ?? 'bg-gray-900 border-gray-800 text-gray-400'
}

onMounted(async () => {
  await fetch('/sanctum/csrf-cookie', { credentials: 'include' })
  if (isEdit.value) {
    try { await loadTemplate() }
    catch (e: unknown) { error.value = e instanceof Error ? e.message : 'Failed to load template' }
  }
})
</script>

<template>
  <div class="p-6 max-w-5xl mx-auto">
    <div class="flex items-center justify-between mb-8">
      <div>
        <h1 class="text-2xl font-bold text-white">{{ isEdit ? 'Edit Template' : 'New Template' }}</h1>
        <p class="text-gray-500 mt-1">Configure your pipeline template</p>
      </div>
      <button class="text-sm text-gray-500 hover:text-gray-300 transition" @click="router.visit('/admin/pipeline-templates')">
        Back to Library
      </button>
    </div>
    <div v-if="success" class="mb-6 bg-emerald-900/20 border border-emerald-800 rounded-xl p-4 text-emerald-400 text-sm">
      Template saved successfully. Redirecting...
    </div>
    <div v-if="error" class="mb-6 bg-red-900/20 border border-red-800 rounded-xl p-4 text-red-400 text-sm">{{ error }}</div>
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <div class="lg:col-span-1 space-y-4">
        <div class="bg-gray-900 border border-gray-800 rounded-xl p-5">
          <h2 class="text-sm font-semibold text-white mb-4">Metadata</h2>
          <label class="block mb-3">
            <span class="text-xs text-gray-500 mb-1 block">Name *</span>
            <input v-model="form.name" type="text" @input="onNameInput"
              class="w-full px-3 py-2 bg-gray-800 border border-gray-700 rounded-lg text-sm text-white focus:outline-none focus:border-indigo-500"
              placeholder="My Pipeline Template" />
          </label>
          <label class="block mb-3">
            <span class="text-xs text-gray-500 mb-1 block">Slug</span>
            <input v-model="form.slug" type="text"
              class="w-full px-3 py-2 bg-gray-800 border border-gray-700 rounded-lg text-sm text-gray-300 font-mono focus:outline-none focus:border-indigo-500"
              placeholder="auto-generated" />
          </label>
          <label class="block mb-3">
            <span class="text-xs text-gray-500 mb-1 block">Description</span>
            <textarea v-model="form.description" rows="3"
              class="w-full px-3 py-2 bg-gray-800 border border-gray-700 rounded-lg text-sm text-gray-300 focus:outline-none focus:border-indigo-500 resize-none"
              placeholder="What does this template do?" />
          </label>
          <label class="block mb-3">
            <span class="text-xs text-gray-500 mb-1 block">Category</span>
            <select v-model="form.category"
              class="w-full px-3 py-2 bg-gray-800 border border-gray-700 rounded-lg text-sm text-gray-300 focus:outline-none focus:border-indigo-500">
              <option v-for="cat in categories" :key="cat.value" :value="cat.value">{{ cat.label }}</option>
            </select>
          </label>
          <label class="block mb-3">
            <span class="text-xs text-gray-500 mb-1 block">Icon (emoji)</span>
            <input v-model="form.icon" type="text"
              class="w-full px-3 py-2 bg-gray-800 border border-gray-700 rounded-lg text-sm text-gray-300 focus:outline-none focus:border-indigo-500"
              placeholder="?" />
          </label>
          <label class="block">
            <span class="text-xs text-gray-500 mb-1 block">Author Name</span>
            <input v-model="form.author_name" type="text"
              class="w-full px-3 py-2 bg-gray-800 border border-gray-700 rounded-lg text-sm text-gray-300 focus:outline-none focus:border-indigo-500"
              placeholder="Your name" />
          </label>
        </div>
        <div class="bg-gray-900 border border-gray-800 rounded-xl p-5">
          <h2 class="text-sm font-semibold text-white mb-4">Version</h2>
          <label class="block mb-3">
            <span class="text-xs text-gray-500 mb-1 block">Version</span>
            <input v-model="form.version" type="text"
              class="w-full px-3 py-2 bg-gray-800 border border-gray-700 rounded-lg text-sm text-gray-300 font-mono focus:outline-none focus:border-indigo-500"
              placeholder="1.0.0" />
          </label>
          <label class="block">
            <span class="text-xs text-gray-500 mb-1 block">Changelog</span>
            <input v-model="form.changelog" type="text"
              class="w-full px-3 py-2 bg-gray-800 border border-gray-700 rounded-lg text-sm text-gray-300 focus:outline-none focus:border-indigo-500"
              placeholder="What changed?" />
          </label>
        </div>
      </div>
      <div class="lg:col-span-2">
        <div class="bg-gray-900 border border-gray-800 rounded-xl p-5">
          <div class="flex items-center justify-between mb-4">
            <h2 class="text-sm font-semibold text-white">
              Pipeline Stages <span class="ml-2 text-xs text-gray-600 font-normal">drag to reorder</span>
            </h2>
            <button class="px-3 py-1.5 text-xs bg-indigo-600/20 text-indigo-400 border border-indigo-800 rounded-lg hover:bg-indigo-600/30 transition" @click="addStage">
              + Add Stage
            </button>
          </div>
          <StageDragList v-model="form.stages" :stage-types="stageTypes" :stage-color-class="stageColorClass" @remove="removeStage" />
          <div v-if="!form.stages.length" class="py-12 text-center text-gray-600 text-sm border border-dashed border-gray-800 rounded-xl">
            No stages yet. Click &quot;+ Add Stage&quot; to begin.
          </div>
        </div>
        <div v-if="form.stages.length" class="mt-4 bg-gray-900 border border-gray-800 rounded-xl p-5">
          <h2 class="text-sm font-semibold text-white mb-4">Preview</h2>
          <div class="flex items-center gap-3 overflow-x-auto pb-2">
            <template v-for="(stage, i) in form.stages" :key="stage._uid">
              <div class="flex-shrink-0 px-4 py-2 rounded-lg border text-sm" :class="stageColorClass(stage.type)">
                <p class="font-medium">{{ stage.name || 'Unnamed' }}</p>
                <p class="text-xs opacity-60">{{ stage.type }}</p>
              </div>
              <svg v-if="i < form.stages.length - 1" class="w-5 h-5 text-gray-700 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
              </svg>
            </template>
          </div>
        </div>
      </div>
    </div>
    <div class="mt-6 flex justify-end gap-3">
      <button class="px-4 py-2 text-sm text-gray-500 hover:text-gray-300 transition" @click="router.visit('/admin/pipeline-templates')">
        Cancel
      </button>
      <button class="px-6 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-500 transition font-medium text-sm disabled:opacity-50"
        :disabled="saving || !form.name" @click="save">
        {{ saving ? 'Saving...' : isEdit ? 'Save Changes' : 'Create Template' }}
      </button>
    </div>
  </div>
</template>
