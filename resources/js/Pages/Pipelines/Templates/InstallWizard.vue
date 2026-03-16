<script setup lang="ts">
import { ref, computed, onMounted } from "vue"
import { router } from "@inertiajs/vue3"

interface Template {
  id: string; name: string; slug: string
  description: string | null; category: string | null; icon: string | null
  author_name: string | null; downloads_count: number
  average_rating: number
  latest_version: {
    id: string; version: string
    definition: { stages?: { name: string; type: string; agent_role?: string | null }[] }
  } | null
}

const props = defineProps<{ spaceId: string; templateId?: string }>()

// 4 steps: select, configure, preview, install
type Step = "select" | "configure" | "preview" | "install"
const steps: { id: Step; label: string }[] = [
  { id: "select", label: "Select Template" },
  { id: "configure", label: "Configure" },
  { id: "preview", label: "Preview" },
  { id: "install", label: "Install" },
]

const currentStep = ref<Step>("select")
const selectedTemplate = ref<Template | null>(null)
const allTemplates = ref<Template[]>([])
const loading = ref(false)
const installing = ref(false)
const error = ref<string | null>(null)
const installResult = ref<{ pipeline_id?: string } | null>(null)

const config = ref({
  pipeline_name: "",
  persona_prefix: "",
  overwrite_existing: false,
})


const stepIndex = computed(() => steps.findIndex((s) => s.id === currentStep.value))

function xsrfToken(): string {
  const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/)
  return match ? decodeURIComponent(match[1]) : ''
}

async function fetchTemplates(): Promise<void> {
  loading.value = true
  try {
    await fetch('/sanctum/csrf-cookie', { credentials: 'include' })
    const res = await fetch(`/api/v1/spaces/${props.spaceId}/pipeline-templates`, {
      credentials: 'include',
      headers: { Accept: 'application/json', 'X-XSRF-TOKEN': xsrfToken() },
    })
    if (!res.ok) throw new Error(`HTTP ${res.status}`)
    const data = await res.json()
    allTemplates.value = data.data ?? data
    if (props.templateId) {
      const found = allTemplates.value.find((t) => t.id === props.templateId)
      if (found) selectTemplate(found)
    }
  } catch (e: unknown) {
    error.value = e instanceof Error ? e.message : 'Failed to load templates'
  } finally { loading.value = false }
}

function selectTemplate(t: Template): void {
  selectedTemplate.value = t
  config.value.pipeline_name = t.name
  config.value.persona_prefix = t.slug
}

function goToStep(step: Step): void {
  currentStep.value = step
}

function nextStep(): void {
  const idx = stepIndex.value
  if (idx < steps.length - 1) {
    currentStep.value = steps[idx + 1].id
  }
}

function prevStep(): void {
  const idx = stepIndex.value
  if (idx > 0) {
    currentStep.value = steps[idx - 1].id
  }
}

async function doInstall(): Promise<void> {
  if (!selectedTemplate.value?.latest_version) return
  installing.value = true
  error.value = null
  try {
    const payload = {
      pipeline_name: config.value.pipeline_name || selectedTemplate.value.name,
      persona_prefix: config.value.persona_prefix || null,
      overwrite_existing: config.value.overwrite_existing,
    }
    const res = await fetch(
      `/api/v1/spaces/${props.spaceId}/pipeline-templates/installs/${selectedTemplate.value.latest_version.id}`,
      {
        method: 'POST',
        credentials: 'include',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-XSRF-TOKEN': xsrfToken() },
        body: JSON.stringify(payload),
      },
    )
    if (!res.ok) {
      const body = await res.json().catch(() => ({}))
      throw new Error((body as { message?: string }).message ?? `HTTP ${res.status}`)
    }
    installResult.value = await res.json()
    currentStep.value = 'install'
  } catch (e: unknown) {
    error.value = e instanceof Error ? e.message : 'Install failed'
  } finally { installing.value = false }
}

function stageCount(t: Template): number {
  return t.latest_version?.definition?.stages?.length ?? 0
}

function starClass(n: number, rating: number): string {
  return n <= Math.round(rating) ? 'text-yellow-400' : 'text-gray-700'
}

onMounted(fetchTemplates)
</script>

<template>
  <div class="p-6 max-w-4xl mx-auto">
    <div class="mb-8">
      <h1 class="text-2xl font-bold text-white">Install Pipeline Template</h1>
      <p class="text-gray-500 mt-1">Set up a pipeline from a template in minutes</p>
    </div>

    <!-- Progress bar -->
    <div class="flex items-center gap-2 mb-8">
      <template v-for="(step, i) in steps" :key="step.id">
        <button
          class="flex items-center gap-2 text-sm transition"
          :class="stepIndex >= i ? 'text-white' : 'text-gray-600'"
          :disabled="stepIndex < i"
          @click="stepIndex >= i && goToStep(step.id)"
        >
          <span
            class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold border transition"
            :class="stepIndex > i ? 'bg-emerald-600 border-emerald-600 text-white' :
                    stepIndex === i ? 'bg-indigo-600 border-indigo-600 text-white' :
                    'bg-transparent border-gray-700 text-gray-600'"
          >
            {{ stepIndex > i ? '&#10003;' : i + 1 }}
          </span>
          <span class="hidden sm:block">{{ step.label }}</span>
        </button>
        <div v-if="i < steps.length - 1" class="flex-1 h-px bg-gray-800" />
      </template>
    </div>

    <div v-if="error" class="mb-6 bg-red-900/20 border border-red-800 rounded-xl p-4 text-red-400 text-sm">
      {{ error }}
    </div>

    <!-- Step 1: Select Template -->
    <div v-if="currentStep === 'select'">
      <div v-if="loading" class="flex justify-center py-20">
        <div class="w-8 h-8 border-2 border-indigo-500 border-t-transparent rounded-full animate-spin" />
      </div>
      <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div
          v-for="t in allTemplates"
          :key="t.id"
          class="bg-gray-900 border rounded-xl p-4 cursor-pointer transition"
          :class="selectedTemplate?.id === t.id ? 'border-indigo-500 ring-1 ring-indigo-500' : 'border-gray-800 hover:border-gray-700'"
          @click="selectTemplate(t)"
        >
          <div class="flex items-center gap-3 mb-2">
            <span v-if="t.icon" class="text-xl">{{ t.icon }}</span>
            <div v-else class="w-8 h-8 rounded-lg bg-indigo-900/40 border border-indigo-800 flex items-center justify-center text-indigo-400 font-bold text-xs">
              {{ t.name.charAt(0).toUpperCase() }}
            </div>
            <div>
              <p class="font-semibold text-white text-sm">{{ t.name }}</p>
              <p class="text-xs text-gray-500">{{ stageCount(t) }} stages</p>
            </div>
          </div>
          <p class="text-gray-400 text-xs line-clamp-2">{{ t.description ?? 'No description.' }}</p>
        </div>
      </div>
      <div class="mt-6 flex justify-end">
        <button
          class="px-6 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-500 transition font-medium text-sm disabled:opacity-50"
          :disabled="!selectedTemplate"
          @click="nextStep"
        >
          Continue
        </button>
      </div>
    </div>

    <!-- Step 2: Configure -->
    <div v-if="currentStep === 'configure'" class="max-w-lg">
      <div class="bg-gray-900 border border-gray-800 rounded-xl p-6 space-y-4">
        <h2 class="text-lg font-semibold text-white mb-2">Configuration</h2>
        <label class="block">
          <span class="text-xs text-gray-500 mb-1 block">Pipeline Name</span>
          <input v-model="config.pipeline_name" type="text"
            class="w-full px-3 py-2 bg-gray-800 border border-gray-700 rounded-lg text-sm text-white focus:outline-none focus:border-indigo-500"
            placeholder="My Pipeline" />
        </label>
        <label class="block">
          <span class="text-xs text-gray-500 mb-1 block">Persona Name Prefix <span class="text-gray-700">(optional)</span></span>
          <input v-model="config.persona_prefix" type="text"
            class="w-full px-3 py-2 bg-gray-800 border border-gray-700 rounded-lg text-sm text-gray-300 focus:outline-none focus:border-indigo-500"
            placeholder="e.g. blog-" />
        </label>
        <label class="flex items-center gap-3 cursor-pointer">
          <input v-model="config.overwrite_existing" type="checkbox" class="rounded" />
          <span class="text-sm text-gray-400">Overwrite if pipeline with same name exists</span>
        </label>
      </div>
      <div class="mt-6 flex justify-between">
        <button class="px-4 py-2 text-sm text-gray-500 hover:text-gray-300 transition" @click="prevStep">
          Back
        </button>
        <button
          class="px-6 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-500 transition font-medium text-sm disabled:opacity-50"
          :disabled="!config.pipeline_name"
          @click="nextStep"
        >
          Continue
        </button>
      </div>
    </div>

    <!-- Step 3: Preview -->
    <div v-if="currentStep === 'preview' && selectedTemplate">
      <div class="bg-gray-900 border border-gray-800 rounded-xl p-6 mb-4">
        <h2 class="text-lg font-semibold text-white mb-4">Installation Preview</h2>
        <div class="grid grid-cols-2 gap-4 mb-4">
          <div>
            <p class="text-xs text-gray-500 mb-1">Template</p>
            <p class="text-white font-medium">{{ selectedTemplate.name }}</p>
          </div>
          <div>
            <p class="text-xs text-gray-500 mb-1">Pipeline Name</p>
            <p class="text-white font-medium">{{ config.pipeline_name }}</p>
          </div>
          <div>
            <p class="text-xs text-gray-500 mb-1">Version</p>
            <p class="text-white">{{ selectedTemplate.latest_version?.version ?? 'N/A' }}</p>
          </div>
          <div>
            <p class="text-xs text-gray-500 mb-1">Stages</p>
            <p class="text-white">{{ stageCount(selectedTemplate) }}</p>
          </div>
        </div>
        <div v-if="stageCount(selectedTemplate) > 0">
          <p class="text-xs text-gray-500 mb-3">Pipeline Stages</p>
          <div class="flex flex-wrap gap-2">
            <div
              v-for="(stage, i) in selectedTemplate.latest_version!.definition.stages"
              :key="i"
              class="px-3 py-1.5 bg-gray-800 border border-gray-700 rounded-lg text-xs text-gray-400"
            >
              {{ i + 1 }}. {{ stage.name }}
              <span class="ml-1 opacity-50">{{ stage.type }}</span>
            </div>
          </div>
        </div>
      </div>
      <div class="bg-amber-900/10 border border-amber-900/30 rounded-xl p-4 mb-6 text-amber-400 text-sm">
        This will create a new pipeline and associated persona configurations in your space.
      </div>
      <div class="flex justify-between">
        <button class="px-4 py-2 text-sm text-gray-500 hover:text-gray-300 transition" @click="prevStep">Back</button>
        <button
          class="px-6 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-500 transition font-medium text-sm disabled:opacity-50"
          :disabled="installing"
          @click="doInstall"
        >
          {{ installing ? 'Installing...' : 'Install Template' }}
        </button>
      </div>
    </div>

    <!-- Step 4: Done -->
    <div v-if="currentStep === 'install'" class="text-center py-12">
      <div class="w-16 h-16 bg-emerald-900/40 border border-emerald-700 rounded-full flex items-center justify-center text-emerald-400 text-3xl mx-auto mb-6">
        &#10003;
      </div>
      <h2 class="text-2xl font-bold text-white mb-2">Template Installed!</h2>
      <p class="text-gray-400 mb-8">Your pipeline is ready to use.</p>
      <div class="flex justify-center gap-3">
        <button
          class="px-6 py-3 bg-indigo-600 text-white rounded-xl hover:bg-indigo-500 transition font-medium"
          @click="router.visit('/admin/pipelines')"
        >
          View Pipelines
        </button>
        <button
          class="px-6 py-3 bg-gray-900 text-gray-400 rounded-xl hover:bg-gray-800 transition"
          @click="router.visit('/admin/pipeline-templates')"
        >
          Back to Library
        </button>
      </div>
    </div>
  </div>
</template>
