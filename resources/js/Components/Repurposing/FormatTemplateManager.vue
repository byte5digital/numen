<script setup>
import { ref, onMounted } from 'vue';

const props = defineProps({
    spaceId: { type: String, required: true },
});

const templates    = ref([]);
const loading      = ref(false);
const error        = ref(null);
const saving       = ref(false);
const deleting     = ref(null);
const editingId    = ref(null);
const showCreate   = ref(false);

const editForm = ref({ system_prompt: '', user_prompt_template: '', max_tokens: 1024 });
const createForm = ref({ format_key: '', name: '', system_prompt: '', user_prompt_template: '', max_tokens: 1024 });

async function csrfCookie() { await fetch('/sanctum/csrf-cookie', { credentials: 'include' }); }
function xsrfToken() { const m = document.cookie.match(/XSRF-TOKEN=([^;]+)/); return m ? decodeURIComponent(m[1]) : ''; }
async function apiFetch(url, opts = {}) {
    await csrfCookie();
    const res = await fetch(url, { credentials: 'include', headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-XSRF-TOKEN': xsrfToken(), ...opts.headers }, ...opts });
    if (!res.ok) { const b = await res.json().catch(() => ({})); throw new Error(b.message ?? 'HTTP ' + res.status); }
    return res.status === 204 ? null : res.json();
}

async function loadTemplates() {
    loading.value = true; error.value = null;
    try {
        const r = await apiFetch('/api/format-templates?space_id=' + props.spaceId);
        templates.value = r?.data ?? r ?? [];
    } catch (e) { error.value = e.message; }
    finally { loading.value = false; }
}

async function toggleActive(tmpl) {
    try {
        await apiFetch('/api/format-templates/' + tmpl.id, { method: 'PATCH', body: JSON.stringify({ is_active: !tmpl.is_active }) });
        tmpl.is_active = !tmpl.is_active;
    } catch (e) { error.value = e.message; }
}

function startEdit(tmpl) {
    editingId.value = tmpl.id;
    editForm.value = { system_prompt: tmpl.system_prompt ?? '', user_prompt_template: tmpl.user_prompt_template ?? '', max_tokens: tmpl.max_tokens ?? 1024 };
}

function cancelEdit() { editingId.value = null; }

async function saveEdit(tmpl) {
    saving.value = true; error.value = null;
    try {
        const r = await apiFetch('/api/format-templates/' + tmpl.id, { method: 'PATCH', body: JSON.stringify(editForm.value) });
        const updated = r?.data ?? r;
        const idx = templates.value.findIndex(t => t.id === tmpl.id);
        if (idx !== -1) templates.value[idx] = { ...templates.value[idx], ...updated };
        editingId.value = null;
    } catch (e) { error.value = e.message; }
    finally { saving.value = false; }
}

async function deleteTemplate(tmpl) {
    if (!confirm('Delete template  + tmpl.name + ? This cannot be undone.')) return;
    deleting.value = tmpl.id; error.value = null;
    try {
        await apiFetch('/api/format-templates/' + tmpl.id, { method: 'DELETE' });
        templates.value = templates.value.filter(t => t.id !== tmpl.id);
    } catch (e) { error.value = e.message; }
    finally { deleting.value = null; }
}

function resetCreateForm() {
    createForm.value = { format_key: '', name: '', system_prompt: '', user_prompt_template: '', max_tokens: 1024 };
}

async function submitCreate() {
    saving.value = true; error.value = null;
    try {
        const body = { ...createForm.value, space_id: props.spaceId };
        const r = await apiFetch('/api/format-templates', { method: 'POST', body: JSON.stringify(body) });
        templates.value.push(r?.data ?? r);
        showCreate.value = false; resetCreateForm();
    } catch (e) { error.value = e.message; }
    finally { saving.value = false; }
}

function isCustom(tmpl) { return !!tmpl.space_id; }

onMounted(loadTemplates);
</script>
<template>
    <div class="space-y-5">
        <!-- Header -->
        <div class="flex items-center justify-between">
            <h2 class="text-base font-semibold text-white">Format Templates</h2>
            <button @click="showCreate = !showCreate" class="flex items-center gap-1.5 rounded-lg bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-indigo-500 transition">
                + Create custom
            </button>
        </div>

        <!-- Error -->
        <div v-if="error" class="rounded-xl border border-red-800 bg-red-900/20 p-3 text-sm text-red-300">{{ error }}</div>

        <!-- Create form -->
        <div v-if="showCreate" class="rounded-xl border border-indigo-800 bg-indigo-950/30 p-5 space-y-4">
            <h3 class="text-sm font-semibold text-indigo-300">New custom template</h3>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs text-gray-400 mb-1">Format key</label>
                    <input v-model="createForm.format_key" type="text" placeholder="e.g. linkedin_post" class="w-full rounded-lg border border-gray-700 bg-gray-900 px-3 py-2 text-sm text-white focus:border-indigo-500 focus:outline-none" />
                </div>
                <div>
                    <label class="block text-xs text-gray-400 mb-1">Name</label>
                    <input v-model="createForm.name" type="text" placeholder="My template" class="w-full rounded-lg border border-gray-700 bg-gray-900 px-3 py-2 text-sm text-white focus:border-indigo-500 focus:outline-none" />
                </div>
            </div>
            <div>
                <label class="block text-xs text-gray-400 mb-1">System prompt</label>
                <textarea v-model="createForm.system_prompt" rows="3" class="w-full rounded-lg border border-gray-700 bg-gray-900 px-3 py-2 text-sm text-white focus:border-indigo-500 focus:outline-none font-mono" />
            </div>
            <div>
                <label class="block text-xs text-gray-400 mb-1">User prompt template</label>
                <textarea v-model="createForm.user_prompt_template" rows="3" class="w-full rounded-lg border border-gray-700 bg-gray-900 px-3 py-2 text-sm text-white focus:border-indigo-500 focus:outline-none font-mono" />
            </div>
            <div class="w-40">
                <label class="block text-xs text-gray-400 mb-1">Max tokens</label>
                <input v-model.number="createForm.max_tokens" type="number" min="64" max="8192" class="w-full rounded-lg border border-gray-700 bg-gray-900 px-3 py-2 text-sm text-white focus:border-indigo-500 focus:outline-none" />
            </div>
            <div class="flex gap-2">
                <button @click="submitCreate" :disabled="saving || !createForm.format_key || !createForm.name" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500 transition disabled:opacity-40 disabled:cursor-not-allowed">
                    {{ saving ? 'Saving…' : 'Create template' }}
                </button>
                <button @click="showCreate = false; resetCreateForm()" class="rounded-lg border border-gray-700 bg-gray-800 px-4 py-2 text-sm text-gray-300 hover:border-gray-500 hover:text-white transition">Cancel</button>
            </div>
        </div>

        <!-- Loading -->
        <div v-if="loading" class="py-12 text-center text-gray-500">Loading templates…</div>

        <!-- Table -->
        <div v-else-if="templates.length" class="rounded-xl border border-gray-800 overflow-hidden">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-800 text-left text-xs text-gray-500 uppercase tracking-wider bg-gray-900/60">
                        <th class="px-4 py-3">Format key</th>
                        <th class="px-4 py-3">Name</th>
                        <th class="px-4 py-3">Type</th>
                        <th class="px-4 py-3">Max tokens</th>
                        <th class="px-4 py-3">Active</th>
                        <th class="px-4 py-3">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-800">
                    <template v-for="tmpl in templates" :key="tmpl.id">
                        <!-- Row -->
                        <tr v-if="editingId !== tmpl.id" class="hover:bg-gray-800/40 transition">
                            <td class="px-4 py-3"><span class="font-mono text-xs text-gray-300">{{ tmpl.format_key }}</span></td>
                            <td class="px-4 py-3 text-gray-200">{{ tmpl.name }}</td>
                            <td class="px-4 py-3">
                                <span :class="isCustom(tmpl) ? 'bg-violet-900/40 text-violet-300' : 'bg-gray-800 text-gray-400'" class="rounded-full px-2 py-0.5 text-xs font-medium">
                                    {{ isCustom(tmpl) ? 'Custom' : 'Global' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-gray-400 text-xs">{{ tmpl.max_tokens?.toLocaleString() }}</td>
                            <td class="px-4 py-3">
                                <button @click="toggleActive(tmpl)" :class="tmpl.is_active ? 'bg-emerald-600 hover:bg-emerald-500' : 'bg-gray-700 hover:bg-gray-600'" class="relative inline-flex h-5 w-9 flex-shrink-0 rounded-full transition focus:outline-none">
                                    <span :class="tmpl.is_active ? 'translate-x-4' : 'translate-x-0'" class="inline-block h-5 w-5 transform rounded-full bg-white shadow transition" />
                                </button>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2">
                                    <button @click="startEdit(tmpl)" class="text-xs text-indigo-400 hover:text-indigo-200 transition">Edit</button>
                                    <button v-if="isCustom(tmpl)" @click="deleteTemplate(tmpl)" :disabled="deleting === tmpl.id" class="text-xs text-red-400 hover:text-red-200 transition disabled:opacity-40">
                                        {{ deleting === tmpl.id ? '…' : 'Delete' }}
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <!-- Inline edit row -->
                        <tr v-else class="bg-indigo-950/20">
                            <td colspan="6" class="px-4 py-4">
                                <div class="space-y-3">
                                    <div>
                                        <label class="block text-xs text-gray-400 mb-1">System prompt</label>
                                        <textarea v-model="editForm.system_prompt" rows="3" class="w-full rounded-lg border border-gray-700 bg-gray-900 px-3 py-2 text-sm text-white focus:border-indigo-500 focus:outline-none font-mono" />
                                    </div>
                                    <div>
                                        <label class="block text-xs text-gray-400 mb-1">User prompt template</label>
                                        <textarea v-model="editForm.user_prompt_template" rows="3" class="w-full rounded-lg border border-gray-700 bg-gray-900 px-3 py-2 text-sm text-white focus:border-indigo-500 focus:outline-none font-mono" />
                                    </div>
                                    <div class="flex items-center gap-4">
                                        <div class="w-40">
                                            <label class="block text-xs text-gray-400 mb-1">Max tokens</label>
                                            <input v-model.number="editForm.max_tokens" type="number" min="64" max="8192" class="w-full rounded-lg border border-gray-700 bg-gray-900 px-3 py-2 text-sm text-white focus:border-indigo-500 focus:outline-none" />
                                        </div>
                                        <div class="flex gap-2 mt-4">
                                            <button @click="saveEdit(tmpl)" :disabled="saving" class="rounded-lg bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-indigo-500 transition disabled:opacity-40">{{ saving ? 'Saving…' : 'Save' }}</button>
                                            <button @click="cancelEdit()" class="rounded-lg border border-gray-700 bg-gray-800 px-3 py-1.5 text-sm text-gray-300 hover:border-gray-500 hover:text-white transition">Cancel</button>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>

        <!-- Empty -->
        <div v-else class="rounded-xl border border-gray-800 bg-gray-900/40 py-12 text-center text-gray-500">
            <p class="text-2xl mb-2">📄</p>
            <p>No format templates found for this space.</p>
        </div>
    </div>
</template>
