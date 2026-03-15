<template>
  <div class="media-picker">
    <!-- Selected single asset preview -->
    <div v-if="!multiple && modelValue" class="mb-2 flex items-center gap-3 rounded-lg border border-gray-200 bg-gray-50 p-3">
      <div class="h-16 w-16 flex-shrink-0 overflow-hidden rounded-md bg-gray-200">
        <img v-if="isImage(modelValue)" :src="modelValue.url" :alt="modelValue.alt_text || modelValue.filename" class="h-full w-full object-cover" />
        <div v-else class="flex h-full w-full items-center justify-center text-2xl">{{ mimeIcon(modelValue.mime_type) }}</div>
      </div>
      <div class="min-w-0 flex-1">
        <p class="truncate text-sm font-medium text-gray-900">{{ modelValue.filename }}</p>
        <p class="text-xs text-gray-500">{{ formatBytes(modelValue.size_bytes) }}</p>
      </div>
      <button type="button" class="ml-auto flex-shrink-0 rounded-full p-1 text-gray-400 hover:bg-gray-200" @click="clearSelection">
        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
      </button>
    </div>
    <!-- Multiple selected assets -->
    <div v-if="multiple && Array.isArray(modelValue) && modelValue.length" class="mb-2 flex flex-wrap gap-2">
      <div v-for="asset in modelValue" :key="asset.id" class="group relative h-20 w-20 overflow-hidden rounded-md bg-gray-200">
        <img v-if="isImage(asset)" :src="asset.url" :alt="asset.filename" class="h-full w-full object-cover" />
        <div v-else class="flex h-full w-full items-center justify-center text-2xl">{{ mimeIcon(asset.mime_type) }}</div>
        <button type="button" class="absolute right-0.5 top-0.5 hidden rounded-full bg-white/80 p-0.5 text-gray-700 group-hover:block" @click="removeAsset(asset)">
          <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
        </button>
      </div>
    </div>
    <!-- Trigger button -->
    <button type="button" class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500" @click="openPicker">
      <svg class="h-4 w-4 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
      {{ buttonLabel }}
    </button>
    <!-- Modal -->
    <Teleport to="body">
      <div v-if="isOpen" class="fixed inset-0 z-50 flex items-center justify-center" @keydown.escape="closePicker">
        <div class="absolute inset-0 bg-black/60" @click="closePicker" />
        <div class="relative z-10 flex h-[90vh] w-[95vw] max-w-7xl flex-col overflow-hidden rounded-xl bg-white shadow-2xl">
          <!-- Modal header -->
          <div class="flex items-center justify-between border-b border-gray-200 px-6 py-4">
            <h2 class="text-lg font-semibold text-gray-900">Select Media</h2>
            <div class="flex items-center gap-3">
              <span v-if="accept" class="rounded-full bg-indigo-100 px-2.5 py-0.5 text-xs font-medium text-indigo-700">{{ accept }}</span>
              <button type="button" class="rounded-full p-1.5 text-gray-400 hover:bg-gray-100" @click="closePicker">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
              </button>
            </div>
          </div>
          <!-- Modal body -->
          <div class="flex-1 overflow-auto p-6">
            <div class="mb-4 flex items-center gap-3">
              <input v-model="searchQuery" type="text" placeholder="Search assets..." class="flex-1 rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500" />
              <select v-model="typeFilter" class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none">
                <option value="">All types</option>
                <option value="image">Images</option>
                <option value="video">Video</option>
                <option value="audio">Audio</option>
                <option value="application/pdf">PDF</option>
              </select>
            </div>
            <div v-if="loading" class="flex items-center justify-center py-24 text-gray-400">
              <svg class="h-8 w-8 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" /><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" /></svg>
            </div>
            <div v-else-if="assets.length === 0" class="flex flex-col items-center justify-center py-24 text-gray-400">
              <p>No assets found</p>
            </div>
            <div v-else class="grid grid-cols-3 gap-3 sm:grid-cols-4 md:grid-cols-5 lg:grid-cols-6">
              <button
                v-for="asset in assets"
                :key="asset.id"
                type="button"
                class="group relative aspect-square overflow-hidden rounded-lg border-2 bg-gray-100 transition-all focus:outline-none"
                :class="isSelected(asset) ? 'border-indigo-500 ring-2 ring-indigo-400 ring-offset-1' : 'border-transparent hover:border-gray-300'"
                @click="toggleAsset(asset)"
              >
                <img v-if="isImage(asset)" :src="asset.url" :alt="asset.filename" class="h-full w-full object-cover" loading="lazy" />
                <div v-else class="flex h-full w-full flex-col items-center justify-center gap-1 p-2 text-gray-500">
                  <span class="text-3xl">{{ mimeIcon(asset.mime_type) }}</span>
                  <span class="line-clamp-2 text-center text-xs">{{ asset.filename }}</span>
                </div>
                <div v-if="isSelected(asset)" class="absolute right-1.5 top-1.5 flex h-5 w-5 items-center justify-center rounded-full bg-indigo-500 text-white shadow">
                  <svg class="h-3 w-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" /></svg>
                </div>
              </button>
            </div>
            <div v-if="hasMorePages" class="mt-6 flex justify-center">
              <button type="button" class="rounded-lg border border-gray-300 bg-white px-6 py-2 text-sm text-gray-700 hover:bg-gray-50" :disabled="loadingMore" @click="loadMore">
                {{ loadingMore ? "Loading..." : "Load more" }}
              </button>
            </div>
          </div>
          <!-- Modal footer -->
          <div class="flex items-center justify-between border-t border-gray-200 px-6 py-4">
            <span class="text-sm text-gray-500">{{ pendingSelection.length }} selected</span>
            <div class="flex gap-3">
              <button type="button" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50" @click="closePicker">Cancel</button>
              <button type="button" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50" :disabled="pendingSelection.length === 0" @click="confirmSelection">Confirm</button>
            </div>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import axios from 'axios'

const props = defineProps({
  modelValue: { type: [Object, Array], default: null },
  multiple: { type: Boolean, default: false },
  accept: { type: String, default: null },
  spaceId: { type: String, required: true },
})
const emit = defineEmits(['update:modelValue'])

const isOpen = ref(false)
const searchQuery = ref('')
const typeFilter = ref('')
const assets = ref([])
const loading = ref(false)
const loadingMore = ref(false)
const currentPage = ref(1)
const hasMorePages = ref(false)
const pendingSelection = ref([])

const buttonLabel = computed(() => {
  if (props.multiple) {
    const count = Array.isArray(props.modelValue) ? props.modelValue.length : 0
    return count > 0 ? count + ' asset(s) selected' : 'Select Media'
  }
  return props.modelValue ? 'Change' : 'Select Media'
})

function openPicker() {
  pendingSelection.value = props.multiple
    ? (Array.isArray(props.modelValue) ? [...props.modelValue] : [])
    : (props.modelValue ? [props.modelValue] : [])
  isOpen.value = true
  fetchAssets(1)
}
function closePicker() { isOpen.value = false; pendingSelection.value = [] }
function clearSelection() { emit('update:modelValue', props.multiple ? [] : null) }
function removeAsset(asset) {
  if (!props.multiple || !Array.isArray(props.modelValue)) return
  emit('update:modelValue', props.modelValue.filter(a => a.id !== asset.id))
}
function confirmSelection() {
  emit('update:modelValue', props.multiple ? [...pendingSelection.value] : (pendingSelection.value[0] ?? null))
  closePicker()
}
function isImage(asset) { return asset?.mime_type?.startsWith('image/') }
function isSelected(asset) { return pendingSelection.value.some(a => a.id === asset.id) }
function toggleAsset(asset) {
  if (props.multiple) {
    pendingSelection.value = isSelected(asset)
      ? pendingSelection.value.filter(a => a.id !== asset.id)
      : [...pendingSelection.value, asset]
  } else {
    pendingSelection.value = isSelected(asset) ? [] : [asset]
  }
}
function mimeIcon(mime) {
  if (!mime) return 'f'
  if (mime.startsWith('image/')) return 'i'
  if (mime.startsWith('video/')) return 'v'
  if (mime.startsWith('audio/')) return 'a'
  if (mime === 'application/pdf') return 'p'
  return 'd'
}
function formatBytes(bytes) {
  if (!bytes) return ''
  if (bytes < 1024) return bytes + ' B'
  if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB'
  return (bytes / 1048576).toFixed(1) + ' MB'
}
async function fetchAssets(page = 1) {
  if (page === 1) { loading.value = true; assets.value = [] }
  else { loadingMore.value = true }
  try {
    const params = { space_id: props.spaceId, page, per_page: 60 }
    if (searchQuery.value) params.search = searchQuery.value
    const mimeFilter = props.accept ?? typeFilter.value
    if (mimeFilter) params.mime_type = mimeFilter.replace('/*', '')
    const { data } = await axios.get('/api/v1/media', { params })
    const items = data.data ?? []
    assets.value = page === 1 ? items : [...assets.value, ...items]
    currentPage.value = data.meta?.current_page ?? page
    hasMorePages.value = (data.meta?.current_page ?? 1) < (data.meta?.last_page ?? 1)
  } catch (err) { console.error('MediaPicker fetch error', err) }
  finally { loading.value = false; loadingMore.value = false }
}
async function loadMore() { await fetchAssets(currentPage.value + 1) }

let searchTimer = null
watch(searchQuery, () => { clearTimeout(searchTimer); searchTimer = setTimeout(() => fetchAssets(1), 300) })
watch(typeFilter, () => fetchAssets(1))
</script>
