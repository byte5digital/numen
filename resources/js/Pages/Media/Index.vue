<script setup>
import { ref, reactive, computed, onMounted, watch } from 'vue';
import MediaGrid       from '@/Components/Media/MediaGrid.vue';
import MediaUploadZone from '@/Components/Media/MediaUploadZone.vue';

const props = defineProps({ initialAssets: { type: Object, default: null } });
const assets     = ref([]);
const loading    = ref(false);
const pagination = reactive({ current_page: 1, last_page: 1, total: 0, per_page: 24 });
const view       = ref('grid');
const selected   = ref([]);
const activeAsset = ref(null);
const filters    = reactive({ search: '', type: '', tag: '' });

async function csrfCookie() { await fetch('/sanctum/csrf-cookie', { credentials: 'include' }); }
function xsrfToken() {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}

async function fetchAssets(page = 1) {
    loading.value = true;
    try {
        const params = new URLSearchParams({ page, per_page: pagination.per_page });
        if (filters.search) params.set('search', filters.search);
        if (filters.type)   params.set('type',   filters.type);
        if (filters.tag)    params.set('tag',     filters.tag);
        await csrfCookie();
        const res = await fetch(`/api/media?${params}`, {
            credentials: 'include',
            headers: { 'Accept': 'application/json', 'X-XSRF-TOKEN': xsrfToken() },
        });
        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        const data = await res.json();
        assets.value = data.data ?? data;
        if (data.meta) {
            pagination.current_page = data.meta.current_page;
            pagination.last_page    = data.meta.last_page;
            pagination.total        = data.meta.total;
            pagination.per_page     = data.meta.per_page;
        }
    } catch (e) { console.error('Failed to fetch media assets:', e); }
    finally { loading.value = false; }
}

let debounceTimer = null;
watch(filters, () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => fetchAssets(1), 350);
}, { deep: true });

onMounted(() => fetchAssets(1));

function goToPage(page) {
    if (page < 1 || page > pagination.last_page) return;
    fetchAssets(page);
}

const pages = computed(() => {
    const total = pagination.last_page;
    if (total <= 7) return Array.from({ length: total }, (_, i) => i + 1);
    const cur = pagination.current_page;
    const set = new Set([1, total, cur, cur - 1, cur + 1].filter(p => p >= 1 && p <= total));
    return [...set].sort((a, b) => a - b);
});

function onUploaded(asset) { assets.value.unshift(asset); pagination.total += 1; }
function onUploadError(msg) { console.error('Upload error:', msg); }
function openAsset(asset)   { activeAsset.value = asset; }
function closeDetail()      { activeAsset.value = null; }

async function deleteAsset(asset) {
    if (!confirm(`Delete "${asset.filename}"? This cannot be undone.`)) return;
    await csrfCookie();
    const res = await fetch(`/api/media/${asset.id}`, {
        method: 'DELETE', credentials: 'include',
        headers: { 'Accept': 'application/json', 'X-XSRF-TOKEN': xsrfToken() },
    });
    if (res.ok) {
        assets.value = assets.value.filter(a => a.id !== asset.id);
        if (activeAsset.value?.id === asset.id) closeDetail();
        pagination.total = Math.max(0, pagination.total - 1);
    }
}

function formatBytes(bytes) {
    if (!bytes) return '—';
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / 1048576).toFixed(1) + ' MB';
}

function formatDate(str) {
    if (!str) return '—';
    return new Date(str).toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
}
</script>

<template>
    <div class="space-y-6">
        <!-- Header -->
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-white">Media Library</h1>
                <p class="text-gray-500 mt-1">{{ pagination.total }} asset{{ pagination.total === 1 ? '' : 's' }}</p>
            </div>
            <div class="flex items-center gap-1 bg-gray-900 rounded-lg border border-gray-800 p-1">
                <button class="px-3 py-1.5 rounded text-sm transition-colors"
                    :class="view === 'grid' ? 'bg-indigo-600 text-white' : 'text-gray-400 hover:text-white'"
                    @click="view = 'grid'">Grid</button>
                <button class="px-3 py-1.5 rounded text-sm transition-colors"
                    :class="view === 'list' ? 'bg-indigo-600 text-white' : 'text-gray-400 hover:text-white'"
                    @click="view = 'list'">List</button>
            </div>
        </div>
        <!-- Upload zone -->
        <MediaUploadZone :multiple="true" accept="image/*,video/*,application/pdf"
            @uploaded="onUploaded" @error="onUploadError" />
        <!-- Filter bar -->
        <div class="flex flex-wrap items-center gap-3">
            <input v-model="filters.search" type="text" placeholder="Search assets..."
                class="flex-1 min-w-52 bg-gray-900 border border-gray-700 rounded-lg px-4 py-2 text-sm text-gray-200 placeholder-gray-600 focus:outline-none focus:border-indigo-500" />
            <select v-model="filters.type"
                class="bg-gray-900 border border-gray-700 rounded-lg px-3 py-2 text-sm text-gray-300 focus:outline-none focus:border-indigo-500">
                <option value="">All types</option>
                <option value="image">Images</option>
                <option value="video">Videos</option>
                <option value="document">Documents</option>
            </select>
            <input v-model="filters.tag" type="text" placeholder="Filter by tag..."
                class="bg-gray-900 border border-gray-700 rounded-lg px-3 py-2 text-sm text-gray-300 placeholder-gray-600 focus:outline-none focus:border-indigo-500 w-40" />
            <button v-if="filters.search || filters.type || filters.tag"
                class="text-xs text-gray-500 hover:text-gray-300 transition-colors"
                @click="Object.assign(filters, { search: '', type: '', tag: '' })">Clear</button>
        </div>
        <!-- Loading -->
        <div v-if="loading" class="flex items-center justify-center py-16">
            <div class="w-8 h-8 border-2 border-indigo-500 border-t-transparent rounded-full animate-spin" />
        </div>
        <!-- Grid view -->
        <template v-else-if="view === 'grid'">
            <MediaGrid :assets="assets" :selectable="false" :selected="selected"
                @open="openAsset"
                @select="a => selected.push(a)"
                @deselect="a => selected = selected.filter(s => s.id !== a.id)" />
        </template>
        <!-- List view -->
        <template v-else>
            <div class="bg-gray-900 rounded-xl border border-gray-800 overflow-hidden">
                <table class="w-full">
                    <thead class="border-b border-gray-800">
                        <tr>
                            <th class="text-left px-4 py-3 text-xs font-medium text-gray-500 uppercase">Name</th>
                            <th class="text-left px-4 py-3 text-xs font-medium text-gray-500 uppercase">Type</th>
                            <th class="text-left px-4 py-3 text-xs font-medium text-gray-500 uppercase">Size</th>
                            <th class="text-left px-4 py-3 text-xs font-medium text-gray-500 uppercase">Uploaded</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-800">
                        <tr v-for="asset in assets" :key="asset.id"
                            class="hover:bg-gray-800/50 cursor-pointer" @click="openAsset(asset)">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded bg-gray-800 flex items-center justify-center overflow-hidden shrink-0">
                                        <img v-if="asset.mime_type?.startsWith('image/')"
                                            :src="asset.variants?.thumbnail || asset.url" :alt="asset.filename"
                                            class="w-full h-full object-cover" loading="lazy" />
                                        <span v-else class="text-lg">F</span>
                                    </div>
                                    <div>
                                        <p class="text-sm text-gray-200 font-medium truncate max-w-xs">{{ asset.filename }}</p>
                                        <p class="text-xs text-gray-600">{{ asset.id }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-400">{{ asset.mime_type }}</td>
                            <td class="px-4 py-3 text-sm text-gray-400">{{ formatBytes(asset.file_size) }}</td>
                            <td class="px-4 py-3 text-sm text-gray-400">{{ formatDate(asset.created_at) }}</td>
                            <td class="px-4 py-3 text-right">
                                <button class="text-xs text-red-500/60 hover:text-red-400 transition-colors px-2 py-1"
                                    @click.stop="deleteAsset(asset)">Delete</button>
                            </td>
                        </tr>
                        <tr v-if="assets.length === 0">
                            <td colspan="5" class="px-4 py-12 text-center text-gray-600 text-sm">No assets found</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </template>
        <!-- Pagination -->
        <div v-if="!loading && pagination.last_page > 1" class="flex items-center justify-center gap-1">
            <button class="px-3 py-1.5 rounded text-sm text-gray-400 hover:text-white disabled:opacity-30 transition-colors"
                :disabled="pagination.current_page === 1" @click="goToPage(pagination.current_page - 1)">Prev</button>
            <template v-for="(page, i) in pages" :key="page">
                <span v-if="i > 0 && page - pages[i-1] > 1" class="text-gray-700 px-1">...</span>
                <button class="w-8 h-8 rounded text-sm transition-colors"
                    :class="page === pagination.current_page ? 'bg-indigo-600 text-white' : 'text-gray-400 hover:text-white hover:bg-gray-800'"
                    @click="goToPage(page)">{{ page }}</button>
            </template>
            <button class="px-3 py-1.5 rounded text-sm text-gray-400 hover:text-white disabled:opacity-30 transition-colors"
                :disabled="pagination.current_page === pagination.last_page" @click="goToPage(pagination.current_page + 1)">Next</button>
        </div>
    </div>
    <!-- Detail panel -->
    <Teleport to="body">
        <Transition name="panel">
            <div v-if="activeAsset" class="fixed inset-0 z-50 flex">
                <div class="flex-1 bg-black/60" @click="closeDetail" />
                <div class="w-full max-w-sm bg-gray-950 border-l border-gray-800 overflow-y-auto p-6 space-y-5 shrink-0">
                    <div class="flex items-center justify-between">
                        <h2 class="text-base font-semibold text-white truncate max-w-xs">{{ activeAsset.filename }}</h2>
                        <button class="text-gray-500 hover:text-white text-xl leading-none" @click="closeDetail">x</button>
                    </div>
                    <div class="aspect-video bg-gray-900 rounded-lg overflow-hidden flex items-center justify-center">
                        <img v-if="activeAsset.mime_type?.startsWith('image/')"
                            :src="activeAsset.url" :alt="activeAsset.filename"
                            class="max-w-full max-h-full object-contain" />
                        <span v-else class="text-5xl">F</span>
                    </div>
                    <dl class="space-y-2 text-sm">
                        <div class="flex justify-between"><dt class="text-gray-500">Type</dt><dd class="text-gray-300">{{ activeAsset.mime_type }}</dd></div>
                        <div class="flex justify-between"><dt class="text-gray-500">Size</dt><dd class="text-gray-300">{{ formatBytes(activeAsset.file_size) }}</dd></div>
                        <div class="flex justify-between"><dt class="text-gray-500">Uploaded</dt><dd class="text-gray-300">{{ formatDate(activeAsset.created_at) }}</dd></div>
                        <div v-if="activeAsset.tags?.length" class="flex justify-between"><dt class="text-gray-500">Tags</dt><dd class="text-gray-300 text-right">{{ activeAsset.tags.join(', ') }}</dd></div>
                    </dl>
                    <div class="flex gap-2 pt-2 border-t border-gray-800">
                        <a :href="activeAsset.url" target="_blank"
                            class="flex-1 text-center px-3 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg text-sm transition-colors">View</a>
                        <button class="px-3 py-2 bg-red-900/40 hover:bg-red-900/70 text-red-400 rounded-lg text-sm transition-colors"
                            @click="deleteAsset(activeAsset)">Delete</button>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
.panel-enter-active, .panel-leave-active { transition: opacity 0.2s ease; }
.panel-enter-from,   .panel-leave-to     { opacity: 0; }
</style>
