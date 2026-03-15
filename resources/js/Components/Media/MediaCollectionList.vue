<template>
  <div class="media-collection-list">
    <!-- Header -->
    <div class="mb-3 flex items-center justify-between">
      <h3 class="text-sm font-semibold text-gray-700">Collections</h3>
      <button
        type="button"
        class="rounded-md bg-indigo-600 px-2.5 py-1 text-xs font-medium text-white hover:bg-indigo-700"
        @click="showCreateForm = true"
      >+ New</button>
    </div>

    <!-- Create form -->
    <form v-if="showCreateForm" class="mb-3 flex gap-2" @submit.prevent="createCollection">
      <input
        v-model="newName"
        type="text"
        placeholder="Collection name"
        class="flex-1 rounded-md border border-gray-300 px-2.5 py-1 text-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
        autofocus
      />
      <button type="submit" class="rounded-md bg-indigo-600 px-3 py-1 text-xs font-medium text-white hover:bg-indigo-700" :disabled="!newName.trim()">Add</button>
      <button type="button" class="rounded-md border border-gray-300 px-3 py-1 text-xs text-gray-600 hover:bg-gray-50" @click="showCreateForm = false; newName = ''">Cancel</button>
    </form>

    <!-- Loading -->
    <div v-if="loading" class="flex items-center justify-center py-8 text-gray-400">
      <svg class="h-5 w-5 animate-spin" fill="none" viewBox="0 0 24 24">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
      </svg>
    </div>

    <!-- Empty -->
    <p v-else-if="collections.length === 0" class="py-6 text-center text-xs text-gray-400">No collections yet</p>

    <!-- List -->
    <ul v-else class="space-y-1">
      <li
        v-for="col in collections"
        :key="col.id"
        class="group flex cursor-pointer items-center justify-between rounded-lg px-3 py-2 text-sm transition-colors"
        :class="activeId === col.id ? 'bg-indigo-50 text-indigo-700' : 'hover:bg-gray-50 text-gray-700'"
        @click="selectCollection(col)"
      >
        <div class="flex min-w-0 items-center gap-2">
          <svg class="h-4 w-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
          </svg>
          <span class="truncate">{{ col.name }}</span>
          <span v-if="col.is_smart" class="rounded-full bg-purple-100 px-1.5 py-0.5 text-xs font-medium text-purple-700">Smart</span>
        </div>
        <div class="flex items-center gap-2">
          <span class="text-xs text-gray-400">{{ col.items_count ?? 0 }}</span>
          <button
            type="button"
            class="hidden rounded p-0.5 text-gray-400 hover:bg-red-50 hover:text-red-500 group-hover:block"
            @click.stop="deleteCollection(col)"
            title="Delete collection"
          >
            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
            </svg>
          </button>
        </div>
      </li>
    </ul>

    <!-- Clear filter -->
    <button
      v-if="activeId"
      type="button"
      class="mt-3 w-full rounded-md border border-gray-300 px-3 py-1.5 text-xs text-gray-500 hover:bg-gray-50"
      @click="clearSelection"
    >Show all assets</button>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'

const props = defineProps({
  spaceId: { type: String, required: true },
})

const emit = defineEmits(['collection-selected', 'collection-cleared'])

const collections = ref([])
const loading = ref(false)
const showCreateForm = ref(false)
const newName = ref('')
const activeId = ref(null)

onMounted(fetchCollections)

async function fetchCollections() {
  loading.value = true
  try {
    const { data } = await axios.get('/api/v1/media/collections', { params: { space_id: props.spaceId } })
    collections.value = data.data ?? []
  } catch (err) {
    console.error('MediaCollectionList fetch error', err)
  } finally {
    loading.value = false
  }
}

async function createCollection() {
  if (!newName.value.trim()) return
  try {
    const { data } = await axios.post('/api/v1/media/collections', {
      space_id: props.spaceId,
      name: newName.value.trim(),
    })
    collections.value.unshift(data.data)
    newName.value = ''; showCreateForm.value = false
  } catch (err) {
    console.error('Create collection error', err)
  }
}

async function deleteCollection(col) {
  if (!confirm('Delete collection "' + col.name + '"?')) return
  try {
    await axios.delete('/api/v1/media/collections/' + col.id)
    collections.value = collections.value.filter(c => c.id !== col.id)
    if (activeId.value === col.id) clearSelection()
  } catch (err) {
    console.error('Delete collection error', err)
  }
}

function selectCollection(col) {
  activeId.value = col.id
  emit('collection-selected', col)
}

function clearSelection() {
  activeId.value = null
  emit('collection-cleared')
}
</script>
