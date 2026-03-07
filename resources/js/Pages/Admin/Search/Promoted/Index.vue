<template>
  <div class="p-6">
    <div class="mb-6 flex items-center justify-between">
      <h1 class="text-2xl font-bold text-gray-900">Promoted Results</h1>
      <button
        class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700"
        @click="showForm = true"
      >+ Add Promotion</button>
    </div>

    <!-- Form -->
    <div v-if="showForm" class="mb-6 rounded-xl bg-white p-4 shadow-sm">
      <h2 class="mb-3 text-sm font-semibold text-gray-700">{{ editing ? 'Edit' : 'New' }} Promoted Result</h2>
      <form @submit.prevent="save">
        <div class="mb-3">
          <label class="mb-1 block text-xs text-gray-500">Query Pattern</label>
          <input v-model="form.query" type="text" class="w-full rounded border px-3 py-2 text-sm" placeholder="e.g. getting started" required />
        </div>
        <div class="mb-3">
          <label class="mb-1 block text-xs text-gray-500">Content ID</label>
          <input v-model="form.content_id" type="text" class="w-full rounded border px-3 py-2 text-sm font-mono text-xs" placeholder="01HX..." required />
        </div>
        <div class="mb-3">
          <label class="mb-1 block text-xs text-gray-500">Position</label>
          <input v-model.number="form.position" type="number" min="1" class="w-32 rounded border px-3 py-2 text-sm" />
        </div>
        <div class="mb-3 grid grid-cols-2 gap-3">
          <div>
            <label class="mb-1 block text-xs text-gray-500">Starts At (optional)</label>
            <input v-model="form.starts_at" type="datetime-local" class="w-full rounded border px-3 py-2 text-sm" />
          </div>
          <div>
            <label class="mb-1 block text-xs text-gray-500">Expires At (optional)</label>
            <input v-model="form.expires_at" type="datetime-local" class="w-full rounded border px-3 py-2 text-sm" />
          </div>
        </div>
        <div class="flex gap-2">
          <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white">Save</button>
          <button type="button" class="rounded-lg border px-4 py-2 text-sm" @click="cancelForm">Cancel</button>
        </div>
      </form>
    </div>

    <!-- Table -->
    <div class="rounded-xl bg-white shadow-sm">
      <table class="w-full text-sm">
        <thead class="border-b bg-gray-50">
          <tr>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500">Query</th>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500">Content</th>
            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500">Pos.</th>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500">Active</th>
            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y">
          <tr v-for="p in promoted" :key="p.id">
            <td class="px-4 py-3 font-medium">{{ p.query }}</td>
            <td class="px-4 py-3 text-gray-600">{{ p.content?.current_version?.title ?? p.content_id }}</td>
            <td class="px-4 py-3 text-center">{{ p.position }}</td>
            <td class="px-4 py-3">
              <span :class="['rounded-full px-2 py-0.5 text-xs', isActive(p) ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500']">
                {{ isActive(p) ? 'Active' : 'Inactive' }}
              </span>
            </td>
            <td class="px-4 py-3 text-right">
              <button class="mr-2 text-xs text-indigo-600 hover:underline" @click="edit(p)">Edit</button>
              <button class="text-xs text-red-600 hover:underline" @click="remove(p.id)">Delete</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'

interface PromotedResult {
  id: string
  query: string
  content_id: string
  position: number
  starts_at?: string
  expires_at?: string
  content?: { current_version?: { title: string } }
}

const promoted = ref<PromotedResult[]>([])
const showForm = ref(false)
const editing = ref<PromotedResult | null>(null)

const form = ref({
  query: '',
  content_id: '',
  position: 1,
  starts_at: '',
  expires_at: '',
  space_id: 'default',
})

async function load() {
  const res = await fetch('/api/v1/admin/search/promoted')
  const data = await res.json()
  promoted.value = data.data ?? []
}

function edit(p: PromotedResult) {
  editing.value = p
  form.value = { query: p.query, content_id: p.content_id, position: p.position, starts_at: p.starts_at ?? '', expires_at: p.expires_at ?? '', space_id: 'default' }
  showForm.value = true
}

async function save() {
  const url = editing.value ? `/api/v1/admin/search/promoted/${editing.value.id}` : '/api/v1/admin/search/promoted'
  const method = editing.value ? 'PUT' : 'POST'
  await fetch(url, { method, headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(form.value) })
  cancelForm()
  load()
}

async function remove(id: string) {
  if (!confirm('Delete this promotion?')) return
  await fetch(`/api/v1/admin/search/promoted/${id}`, { method: 'DELETE' })
  load()
}

function isActive(p: PromotedResult): boolean {
  const now = new Date()
  if (p.starts_at && new Date(p.starts_at) > now) return false
  if (p.expires_at && new Date(p.expires_at) < now) return false
  return true
}

function cancelForm() {
  showForm.value = false
  editing.value = null
  form.value = { query: '', content_id: '', position: 1, starts_at: '', expires_at: '', space_id: 'default' }
}

onMounted(load)
</script>
