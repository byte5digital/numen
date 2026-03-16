<script setup>
import { ref, computed, watch } from "vue";
import { router, usePage } from "@inertiajs/vue3";

const props = defineProps({
    locales:   { type: Array, default: () => [] },
    supported: { type: Array, default: () => [] },
});

const flash = computed(() => usePage().props.flash ?? {});
const showAddModal  = ref(false);
const search        = ref("");
const addingLocale  = ref(null);
const adding        = ref(false);

const filteredSupported = computed(() => {
    const q = search.value.toLowerCase();
    const active = new Set(props.locales.map(l => l.locale));
    return props.supported.filter(
        s => !active.has(s.code) && (
            s.code.toLowerCase().includes(q) ||
            s.label.toLowerCase().includes(q)
        )
    );
});

function openAddModal() {
    search.value = ""; addingLocale.value = null; showAddModal.value = true;
}
function selectLocale(s) { addingLocale.value = s; }

function xsrfToken() {
    return decodeURIComponent(document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? '');
}

function authHeaders(withBody = false) {
    const headers = {
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-XSRF-TOKEN': xsrfToken(),
    };
    if (withBody) headers['Content-Type'] = 'application/json';
    return headers;
}

async function confirmAdd() {
    if (!addingLocale.value) return;
    adding.value = true;
    try {
        const res = await fetch("/api/v1/locales", {
            method: 'POST',
            credentials: 'include',
            headers: authHeaders(true),
            body: JSON.stringify({
                locale: addingLocale.value.code,
                label:  addingLocale.value.label,
            }),
        });
        if (res.ok) {
            showAddModal.value = false;
            router.reload({ only: ['locales'] });
        } else {
            const data = await res.json().catch(() => ({}));
            console.error('Failed to add locale:', data);
        }
    } finally {
        adding.value = false;
    }
}

async function toggleActive(locale) {
    const res = await fetch("/api/v1/locales/" + locale.id, {
        method: 'PATCH',
        credentials: 'include',
        headers: authHeaders(true),
        body: JSON.stringify({ is_active: !locale.is_active }),
    });
    if (res.ok) router.reload({ only: ['locales'] });
}

async function setDefault(locale) {
    const res = await fetch("/api/v1/locales/" + locale.id, {
        method: 'PATCH',
        credentials: 'include',
        headers: authHeaders(true),
        body: JSON.stringify({ is_default: true }),
    });
    if (res.ok) router.reload({ only: ['locales'] });
}

const dragging     = ref(null);
const dragOver     = ref(null);
const localLocales = ref([...props.locales]);

watch(() => props.locales, v => { localLocales.value = [...v]; });

function onDragStart(i) { dragging.value = i; }
function onDragOver(i)  { dragOver.value  = i; }
function onDragEnd()    { dragging.value  = null; dragOver.value = null; }

async function onDrop(index) {
    if (dragging.value === null || dragging.value === index) return;
    const arr = [...localLocales.value];
    const [moved] = arr.splice(dragging.value, 1);
    arr.splice(index, 0, moved);
    localLocales.value = arr;
    dragging.value = null; dragOver.value = null;
    const res = await fetch("/api/v1/locales/reorder", {
        method: 'PATCH',
        credentials: 'include',
        headers: authHeaders(true),
        body: JSON.stringify({
            order: arr.map((l, i) => ({ id: l.id, sort_order: i + 1 })),
        }),
    });
    if (res.ok) router.reload({ only: ['locales'] });
}

const deleteError = ref(null);
const deleting    = ref(null);

async function deleteLocale(locale) {
    if (!confirm("Delete " + locale.label + " locale? This cannot be undone.")) return;
    deleting.value = locale.id;
    try {
        const res = await fetch("/api/v1/locales/" + locale.id, {
            method: 'DELETE',
            credentials: 'include',
            headers: authHeaders(),
        });
        if (res.ok) {
            router.reload({ only: ['locales'] });
        } else {
            const data = await res.json().catch(() => ({}));
            deleteError.value = data.message ?? data.locale ?? "Cannot delete: content may exist in this locale.";
        }
    } finally {
        deleting.value = null;
    }
}
</script>

<template>
    <div class="max-w-4xl mx-auto py-8 px-4">
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Locales</h1>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Manage the languages available in this space.</p>
            </div>
            <div class="flex items-center gap-3">
                <a href="/content/translations" class="text-sm text-indigo-600 hover:text-indigo-700 dark:text-indigo-400 font-medium">
                    Translation Matrix &rarr;
                </a>
                <button @click="openAddModal"
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium transition">
                    + Add Locale
                </button>
            </div>
        </div>

        <div v-if="flash.success" class="mb-4 p-3 rounded-lg bg-green-50 dark:bg-green-900/20 text-green-700 dark:text-green-400 text-sm">
            {{ flash.success }}
        </div>
        <div v-if="deleteError" class="mb-4 p-3 rounded-lg bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-400 text-sm flex justify-between">
            <span>{{ deleteError }}</span>
            <button @click="deleteError = null" class="ml-4 text-red-400 hover:text-red-600">&#x2715;</button>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead>
                    <tr class="bg-gray-50 dark:bg-gray-700/50">
                        <th class="w-8 px-3 py-3"></th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Locale</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Label</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Default</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Active</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                    <tr v-for="(locale, index) in localLocales" :key="locale.id"
                        draggable="true"
                        @dragstart="onDragStart(index)" @dragover.prevent="onDragOver(index)"
                        @drop="onDrop(index)" @dragend="onDragEnd"
                        :class="['transition-colors',
                            dragOver === index && dragging !== index
                                ? 'bg-indigo-50 dark:bg-indigo-900/20'
                                : 'hover:bg-gray-50 dark:hover:bg-gray-700/30']">
                        <td class="px-3 py-3 cursor-grab text-gray-400 select-none text-center">&#8286;</td>
                        <td class="px-4 py-3">
                            <code class="text-xs font-mono bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 px-2 py-0.5 rounded">{{ locale.locale }}</code>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-800 dark:text-gray-200">{{ locale.label }}</td>
                        <td class="px-4 py-3 text-center">
                            <span v-if="locale.is_default" class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-indigo-100 dark:bg-indigo-900/40 text-indigo-700 dark:text-indigo-300">Default</span>
                            <button v-else @click="setDefault(locale)" class="text-xs text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400 transition">Set default</button>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <button @click="toggleActive(locale)" :disabled="locale.is_default"
                                :title="locale.is_default ? 'Default locale cannot be deactivated' : ''"
                                :class="['relative inline-flex h-5 w-9 rounded-full transition-colors focus:outline-none',
                                    locale.is_active ? 'bg-indigo-600' : 'bg-gray-300 dark:bg-gray-600',
                                    locale.is_default ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer']">
                                <span :class="['inline-block h-4 w-4 mt-0.5 rounded-full bg-white shadow transform transition-transform',
                                    locale.is_active ? 'translate-x-4' : 'translate-x-0.5']" />
                            </button>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <button @click="deleteLocale(locale)"
                                :disabled="locale.is_default || deleting === locale.id"
                                :class="['text-xs transition',
                                    locale.is_default ? 'text-gray-300 dark:text-gray-600 cursor-not-allowed' : 'text-red-400 hover:text-red-600 dark:hover:text-red-400']">
                                <span v-if="deleting === locale.id">&#8230;</span><span v-else>Delete</span>
                            </button>
                        </td>
                    </tr>
                    <tr v-if="localLocales.length === 0">
                        <td colspan="6" class="px-4 py-10 text-center text-sm text-gray-400 dark:text-gray-500">No locales configured yet.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <Teleport to="body">
            <div v-if="showAddModal"
                 class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm"
                 @click.self="showAddModal = false">
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-xl w-full max-w-md mx-4 p-6">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Add Locale</h2>
                    <input v-model="search" type="text" placeholder="Search by code or name…" autofocus
                           class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 mb-3" />
                    <div class="max-h-60 overflow-y-auto rounded-lg border border-gray-200 dark:border-gray-700 divide-y divide-gray-100 dark:divide-gray-700">
                        <button v-for="s in filteredSupported" :key="s.code" @click="selectLocale(s)"
                                :class="['w-full text-left px-3 py-2 text-sm transition',
                                    addingLocale && addingLocale.code === s.code
                                        ? 'bg-indigo-50 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300'
                                        : 'hover:bg-gray-50 dark:hover:bg-gray-700/40 text-gray-800 dark:text-gray-200']">
                            <span class="font-mono text-xs text-gray-400 dark:text-gray-500 mr-2">{{ s.code }}</span>{{ s.label }}
                        </button>
                        <div v-if="filteredSupported.length === 0" class="px-3 py-4 text-center text-sm text-gray-400 dark:text-gray-500">
                            No matching locales found.
                        </div>
                    </div>
                    <div class="flex items-center justify-end gap-3 mt-4">
                        <button @click="showAddModal = false"
                                class="px-4 py-2 text-sm text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200 transition">Cancel</button>
                        <button @click="confirmAdd" :disabled="!addingLocale || adding"
                                class="px-4 py-2 text-sm font-medium rounded-lg bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white transition">
                            <span v-if="adding">Adding…</span><span v-else>Add Locale</span>
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </div>
</template>
