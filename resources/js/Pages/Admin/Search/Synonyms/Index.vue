<template>
  <div class="p-6">
    <div class="mb-6 flex items-center justify-between">
      <h1 class="text-2xl font-bold text-gray-900">Search Synonyms</h1>
      <button
        class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700"
        @click="showForm = true"
      >+ Add Synonym</button>
    </div>

    <!-- Add/Edit form -->
    <div v-if="showForm" class="mb-6 rounded-xl bg-white p-4 shadow-sm">
      <h2 class="mb-3 text-sm font-semibold text-gray-700">{{ editing ? 'Edit' : 'New' }} Synonym</h2>
      <form @submit.prevent="save">
        <div class="mb-3">
          <label class="mb-1 block text-xs text-gray-500">Canonical Term</label>
          <input v-model="form.term" type="text" class="w-full rounded border px-3 py-2 text-sm" required />
        </div>
        <div class="mb-3">
          <label class="mb-1 block text-xs text-gray-500">Synonyms (comma-separated)</label>
          <input v-model="form.synonymsRaw" type="text" class="w-full rounded border px-3 py-2 text-sm" placeholder="JS, ECMAScript" required />
        </div>
        <div class="mb-4 flex items-center gap-2">
          <input v-model="form.is_one_way" type="checkbox" class="rounded" />
          <span class="text-sm text-gray-600">One-way (synonyms → term, not reverse)</span>
        </div>
        <div class="flex gap-2">
          <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
            Save
          </button>
          <button type="button" class="rounded-lg border px-4 py-2 text-sm" @click="cancelForm">Cancel</button>
        </div>
      </form>
    </div>

    <!-- Synonyms table -->
    <div class="rounded-xl bg-white shadow-sm">
      <table class="w-full text-sm">
        <thead class="border-b bg-gray-50">
          <tr>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500">Term</th>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500">Synonyms</th>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500">Type</th>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500">Source</th>
            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y">
          <tr v-for="synonym in synonyms" :key="synonym.id">
            <td class="px-4 py-3 font-medium">{{ synonym.term }}</td>
            <td class="px-4 py-3">
              <div class="flex flex-wrap gap-1">
                <span v-for="s in synonym.synonyms" :key="s" class="rounded-full bg-gray-100 px-2 py-0.5 text-xs">{{ s }}</span>
              </div>
            </td>
            <td class="px-4 py-3 text-xs text-gray-500">{{ synonym.is_one_way ? 'One-way' : 'Two-way' }}</td>
            <td class="px-4 py-3">
              <span :class="['rounded-full px-2 py-0.5 text-xs', synonym.source === 'ai_suggested' ? 'bg-purple-100 text-purple-700' : 'bg-gray-100 text-gray-700']">
                {{ synonym.source }}
              </span>
            </td>
            <td class="px-4 py-3 text-right">
              <button class="mr-2 text-xs text-indigo-600 hover:underline" @click="edit(synonym)">Edit</button>
              <button class="text-xs text-red-600 hover:underline" @click="remove(synonym.id)">Delete</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'

interface Synonym {
  id: string
  term: string
  synonyms: string[]
  is_one_way: boolean
  source: string
  approved: boolean
}

const synonyms = ref<Synonym[]>([])
const showForm = ref(false)
const editing = ref<Synonym | null>(null)

const form = ref({
  term: '',
  synonymsRaw: '',
  is_one_way: false,
  space_id: 'default',
})

async function load() {
  const res = await fetch('/api/v1/admin/search/synonyms')
  const data = await res.json()
  synonyms.value = data.data ?? []
}

function edit(synonym: Synonym) {
  editing.value = synonym
  form.value = { term: synonym.term, synonymsRaw: synonym.synonyms.join(', '), is_one_way: synonym.is_one_way, space_id: 'default' }
  showForm.value = true
}

async function save() {
  const payload = {
    space_id: form.value.space_id,
    term: form.value.term,
    synonyms: form.value.synonymsRaw.split(',').map(s => s.trim()).filter(Boolean),
    is_one_way: form.value.is_one_way,
  }

  const url = editing.value ? `/api/v1/admin/search/synonyms/${editing.value.id}` : '/api/v1/admin/search/synonyms'
  const method = editing.value ? 'PUT' : 'POST'

  await fetch(url, { method, headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) })
  cancelForm()
  load()
}

async function remove(id: string) {
  if (!confirm('Delete this synonym?')) return
  await fetch(`/api/v1/admin/search/synonyms/${id}`, { method: 'DELETE' })
  load()
}

function cancelForm() {
  showForm.value = false
  editing.value = null
  form.value = { term: '', synonymsRaw: '', is_one_way: false, space_id: 'default' }
}

onMounted(load)
</script>
