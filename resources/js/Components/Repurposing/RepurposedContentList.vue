<script setup>
import { ref, computed, watch, onMounted } from "vue";

const props = defineProps({
    spaceId: { type: String, required: true },
});

const contentItems   = ref([]);
const repurposedMap  = ref({});
const loading        = ref(false);
const error          = ref(null);
const page           = ref(1);
const perPage        = ref(20);
const totalPages     = ref(1);
const filterFormat   = ref("");
const filterStatus   = ref("");

async function csrfCookie() { await fetch("/sanctum/csrf-cookie", { credentials: "include" }); }
function xsrfToken() { const m = document.cookie.match(/XSRF-TOKEN=([^;]+)/); return m ? decodeURIComponent(m[1]) : ""; }
async function apiFetch(url, opts = {}) {
    await csrfCookie();
    const res = await fetch(url, {
        credentials: "include",
        headers: { "Accept": "application/json", "Content-Type": "application/json", "X-XSRF-TOKEN": xsrfToken(), ...opts.headers },
        ...opts,
    });
    if (!res.ok) { const b = await res.json().catch(() => ({})); throw new Error(b.message ?? "HTTP " + res.status); }
    return res.status === 204 ? null : res.json();
}

async function loadContent() {
    loading.value = true; error.value = null;
    try {
        const params = new URLSearchParams({ page: page.value, per_page: perPage.value });
        const r = await apiFetch(`/api/spaces/${props.spaceId}/content?${params}`);
        contentItems.value = r?.data ?? r ?? [];
        if (r?.meta) totalPages.value = r.meta.last_page ?? Math.ceil((r.meta.total ?? 0) / perPage.value);
        await loadRepurposedForAll();
    } catch (e) { error.value = e.message; }
    finally { loading.value = false; }
}

async function loadRepurposedForAll() {
    const results = await Promise.allSettled(
        contentItems.value.map(c => apiFetch(`/api/content/${c.id}/repurposed`).then(r => ({ id: c.id, items: r?.data ?? r ?? [] })))
    );
    const map = {};
    for (const result of results) {
        if (result.status === "fulfilled") map[result.value.id] = result.value.items;
    }
    repurposedMap.value = map;
}

const allFormats = computed(() => {
    const keys = new Set();
    for (const items of Object.values(repurposedMap.value)) { for (const item of items) keys.add(item.format_key); }
    return Array.from(keys).sort();
});

const filteredGroups = computed(() => {
    return contentItems.value.map(content => {
        let items = repurposedMap.value[content.id] ?? [];
        if (filterFormat.value) items = items.filter(i => i.format_key === filterFormat.value);
        if (filterStatus.value) items = items.filter(i => i.status === filterStatus.value);
        return { content, items };
    }).filter(g => g.items.length > 0 || (!filterFormat.value && !filterStatus.value));
});

function isStale(item) {
    if (!item.content_updated_at || !item.generated_at) return false;
    return new Date(item.content_updated_at) > new Date(item.generated_at);
}

function statusClass(status) {
    return { completed: "bg-emerald-900/40 text-emerald-300", pending: "bg-yellow-900/40 text-yellow-300", processing: "bg-blue-900/40 text-blue-300", failed: "bg-red-900/40 text-red-300" }[status] ?? "bg-gray-800 text-gray-400";
}

function fmtDate(iso) { if (!iso) return "—"; return new Date(iso).toLocaleDateString(undefined, { month: "short", day: "numeric", year: "numeric" }); }
function prevPage() { if (page.value > 1) { page.value--; loadContent(); } }
function nextPage() { if (page.value < totalPages.value) { page.value++; loadContent(); } }
function applyFilters() { page.value = 1; loadContent(); }

watch(() => props.spaceId, () => { page.value = 1; loadContent(); });
onMounted(loadContent);
</script>

<template>
    <div class="space-y-4">
        <div class="flex flex-wrap items-center gap-3">
            <div class="flex items-center gap-2">
                <label class="text-sm text-gray-400">Format</label>
                <select v-model="filterFormat" @change="applyFilters" class="rounded-lg border border-gray-700 bg-gray-900 px-3 py-1.5 text-sm text-white focus:border-indigo-500 focus:outline-none">
                    <option value="">All formats</option>
                    <option v-for="key in allFormats" :key="key" :value="key">{{ key.replace(/_/g, " ") }}</option>
                </select>
            </div>
            <div class="flex items-center gap-2">
                <label class="text-sm text-gray-400">Status</label>
                <select v-model="filterStatus" @change="applyFilters" class="rounded-lg border border-gray-700 bg-gray-900 px-3 py-1.5 text-sm text-white focus:border-indigo-500 focus:outline-none">
                    <option value="">All statuses</option>
                    <option value="completed">Completed</option>
                    <option value="pending">Pending</option>
                    <option value="processing">Processing</option>
                    <option value="failed">Failed</option>
                </select>
            </div>
            <button @click="loadContent" class="ml-auto flex items-center gap-1.5 rounded-lg border border-gray-700 bg-gray-800 px-3 py-1.5 text-sm text-gray-300 hover:border-gray-500 hover:text-white transition">
                ↻ Refresh
            </button>
        </div>

        <div v-if="loading" class="flex items-center justify-center py-16 text-gray-500">
            Loading repurposed content…
        </div>
        <div v-else-if="error" class="rounded-xl border border-red-800 bg-red-900/20 p-4 text-sm text-red-300">{{ error }}</div>
        <div v-else-if="filteredGroups.length === 0" class="rounded-xl border border-gray-800 bg-gray-900/40 py-16 text-center text-gray-500">
            <p class="text-2xl mb-2">🗂️</p>
            <p>No repurposed content found.</p>
        </div>

        <div v-else class="space-y-6">
            <div v-for="group in filteredGroups" :key="group.content.id" class="rounded-xl border border-gray-800 bg-gray-900/40 overflow-hidden">
                <div class="flex items-center justify-between px-4 py-3 border-b border-gray-800 bg-gray-900/60">
                    <div>
                        <h3 class="font-medium text-white text-sm">{{ group.content.title }}</h3>
                        <p class="text-xs text-gray-500 mt-0.5">{{ group.items.length }} repurposed item{{ group.items.length !== 1 ? "s" : "" }}</p>
                    </div>
                    <span class="text-xs text-gray-600">ID {{ group.content.id }}</span>
                </div>
                <div v-if="group.items.length" class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-800 text-left text-xs text-gray-500 uppercase tracking-wider">
                                <th class="px-4 py-2">Format</th>
                                <th class="px-4 py-2">Status</th>
                                <th class="px-4 py-2">Generated</th>
                                <th class="px-4 py-2">Stale</th>
                                <th class="px-4 py-2">Tokens</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-800">
                            <tr v-for="item in group.items" :key="item.id" :class="isStale(item) ? "bg-amber-950/20" : """ class="hover:bg-gray-800/40 transition">
                                <td class="px-4 py-2.5"><span class="rounded-md bg-gray-800 px-2 py-0.5 text-xs font-mono text-gray-300">{{ item.format_key }}</span></td>
                                <td class="px-4 py-2.5"><span :class="statusClass(item.status)" class="rounded-full px-2 py-0.5 text-xs font-medium">{{ item.status }}</span></td>
                                <td class="px-4 py-2.5 text-gray-400 text-xs">{{ fmtDate(item.generated_at) }}</td>
                                <td class="px-4 py-2.5"><span v-if="isStale(item)" class="text-amber-400 text-xs font-medium">⚠ Stale</span><span v-else class="text-gray-600 text-xs">—</span></td>
                                <td class="px-4 py-2.5 text-gray-400 text-xs">{{ item.tokens_used != null ? item.tokens_used.toLocaleString() : "—" }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div v-else class="px-4 py-6 text-center text-sm text-gray-600">No repurposed items match the current filters.</div>
            </div>
        </div>

        <div v-if="totalPages > 1" class="flex items-center justify-between pt-2">
            <p class="text-sm text-gray-500">Page {{ page }} of {{ totalPages }}</p>
            <div class="flex gap-2">
                <button @click="prevPage" :disabled="page === 1" class="rounded-lg border border-gray-700 bg-gray-800 px-3 py-1.5 text-sm text-gray-300 hover:border-gray-500 hover:text-white transition disabled:opacity-40 disabled:cursor-not-allowed">← Prev</button>
                <button @click="nextPage" :disabled="page === totalPages" class="rounded-lg border border-gray-700 bg-gray-800 px-3 py-1.5 text-sm text-gray-300 hover:border-gray-500 hover:text-white transition disabled:opacity-40 disabled:cursor-not-allowed">Next →</button>
            </div>
        </div>
    </div>
</template>
