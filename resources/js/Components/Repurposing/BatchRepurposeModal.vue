<script setup>
import { ref, watch } from 'vue';
import FormatSelector from './FormatSelector.vue';

const props = defineProps({
    spaceId: { type: String, required: true },
    open:    { type: Boolean, default: false },
});
const emit = defineEmits(['close', 'started']);

const step           = ref('configure');
const formats        = ref([]);
const personas       = ref([]);
const selectedFormat = ref(null);
const selectedPersona = ref(null);
const estimate       = ref(null);
const batch          = ref(null);
const batchItems     = ref([]);
const errorMsg       = ref(null);
const loadingFormats = ref(false);
const overLimit      = ref(false);

async function csrfCookie() { await fetch('/sanctum/csrf-cookie', { credentials: 'include' }); }
function xsrfToken() { const m = document.cookie.match(/XSRF-TOKEN=([^;]+)/); return m ? decodeURIComponent(m[1]) : ''; }
async function apiFetch(url, opts = {}) {
    await csrfCookie();
    const res = await fetch(url, { credentials: 'include', headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-XSRF-TOKEN': xsrfToken(), ...opts.headers }, ...opts });
    if (!res.ok) { const b = await res.json().catch(() => ({})); throw new Error(b.message ?? 'HTTP ' + res.status); }
    return res.status === 204 ? null : res.json();
}

watch(() => props.open, async (val) => { if (val) { reset(); await Promise.all([loadFormats(), loadPersonas()]); } });

function reset() {
    step.value = 'configure'; selectedFormat.value = null; selectedPersona.value = null;
    estimate.value = null; batch.value = null; batchItems.value = [];
    errorMsg.value = null; overLimit.value = false;
}

async function loadFormats() {
    if (formats.value.length) return;
    loadingFormats.value = true;
    try { const r = await apiFetch('/api/format-templates/supported'); formats.value = r?.data ?? r ?? []; }
    catch (e) { errorMsg.value = e.message; } finally { loadingFormats.value = false; }
}

async function loadPersonas() {
    if (personas.value.length) return;
    try { const r = await apiFetch('/api/personas'); personas.value = r?.data ?? r ?? []; } catch {}
}

async function getEstimate() {
    if (!selectedFormat.value) return;
    step.value = 'estimating'; errorMsg.value = null; overLimit.value = false;
    try {
        const params = new URLSearchParams({ format_key: selectedFormat.value });
        if (selectedPersona.value) params.set('persona_id', selectedPersona.value);
        const r = await apiFetch('/api/spaces/' + props.spaceId + '/repurpose/estimate?' + params);
        estimate.value = r?.data ?? r;
        if ((estimate.value?.item_count ?? 0) > 50) overLimit.value = true;
        step.value = 'confirm';
    } catch (e) { errorMsg.value = e.message; step.value = 'error'; }
}

async function startBatch() {
    step.value = 'running'; errorMsg.value = null;
    try {
        const body = { format_key: selectedFormat.value };
        if (selectedPersona.value) body.persona_id = selectedPersona.value;
        const r = await apiFetch('/api/spaces/' + props.spaceId + '/repurpose/batch', { method: 'POST', body: JSON.stringify(body) });
        batch.value = r?.data ?? r;
        batchItems.value = (batch.value?.items ?? []).map(item => ({ ...item, _status: item.status ?? 'pending' }));
        emit('started', batch.value);
        if (batchItems.value.length > 0) startPolling();
        else step.value = 'done';
    } catch (e) { errorMsg.value = e.message; step.value = 'error'; }
}

let pollTimer = null;
function startPolling() {
    if (pollTimer) return;
    pollTimer = setInterval(async () => {
        const inflight = batchItems.value.filter(i => i._status === 'pending' || i._status === 'processing');
        if (!inflight.length) { clearInterval(pollTimer); pollTimer = null; step.value = 'done'; return; }
        for (const item of inflight) {
            try {
                const r = await apiFetch('/api/repurposed/' + item.id);
                const updated = r?.data ?? r;
                const idx = batchItems.value.findIndex(i => i.id === item.id);
                if (idx !== -1) batchItems.value[idx] = { ...updated, _status: updated.status };
            } catch {}
        }
        if (!batchItems.value.some(i => i._status === 'pending' || i._status === 'processing')) {
            clearInterval(pollTimer); pollTimer = null; step.value = 'done';
        }
    }, 3000);
}

const doneCount   = () => batchItems.value.filter(i => i._status === 'completed').length;
const failedCount = () => batchItems.value.filter(i => i._status === 'failed').length;
const progressPct = () => batchItems.value.length ? Math.round(((doneCount() + failedCount()) / batchItems.value.length) * 100) : 0;
function statusIcon(s) { return { completed: '✅', pending: '⏳', processing: '🔄', failed: '❌' }[s] ?? '❓'; }
function close() { if (pollTimer) { clearInterval(pollTimer); pollTimer = null; } emit('close'); }
function formatCost(v) { if (v == null) return '—'; return '$' + Number(v).toFixed(4); }
function formatTokens(v) { if (v == null) return '—'; return Number(v).toLocaleString(); }
</script>
<template>
    <Teleport to="body">
        <Transition name="fade">
            <div v-if="open" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-black/70 backdrop-blur-sm" @click="close" />
                <div class="relative w-full max-w-2xl rounded-2xl border border-gray-800 bg-gray-950 shadow-2xl overflow-hidden">
                    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-800">
                        <h2 class="text-lg font-semibold text-white">Batch Repurpose Content</h2>
                        <button @click="close" class="rounded-lg p-1.5 text-gray-500 hover:text-white hover:bg-gray-800 transition">✕</button>
                    </div>
                    <div class="px-6 py-5 space-y-5 max-h-[70vh] overflow-y-auto">
                        <template v-if="step === 'configure'">
                            <div>
                                <h3 class="text-sm font-medium text-gray-300 mb-3">1. Select output format</h3>
                                <div v-if="loadingFormats" class="text-sm text-gray-500">Loading formats…</div>
                                <FormatSelector v-else :formats="formats" v-model="selectedFormat" />
                            </div>
                            <div>
                                <h3 class="text-sm font-medium text-gray-300 mb-2">2. Persona (optional)</h3>
                                <select v-model="selectedPersona" class="w-full rounded-lg border border-gray-700 bg-gray-900 px-3 py-2 text-sm text-white focus:border-indigo-500 focus:outline-none">
                                    <option :value="null">No persona</option>
                                    <option v-for="p in personas" :key="p.id" :value="p.id">{{ p.name }}</option>
                                </select>
                            </div>
                        </template>
                        <div v-if="step === 'estimating'" class="flex items-center justify-center py-10 text-gray-400">
                            <span class="mr-2 animate-spin">⏳</span> Estimating cost…
                        </div>
                        <template v-if="step === 'confirm' && estimate">
                            <div class="rounded-xl border border-gray-800 bg-gray-900/60 p-5 space-y-3">
                                <h3 class="text-sm font-semibold text-gray-200">Batch estimate</h3>
                                <div class="grid grid-cols-3 gap-3 text-center">
                                    <div class="rounded-lg bg-gray-800 p-3">
                                        <p class="text-2xl font-bold text-white">{{ estimate.item_count ?? '—' }}</p>
                                        <p class="text-xs text-gray-500 mt-1">Items</p>
                                    </div>
                                    <div class="rounded-lg bg-gray-800 p-3">
                                        <p class="text-2xl font-bold text-white">{{ formatTokens(estimate.estimated_tokens) }}</p>
                                        <p class="text-xs text-gray-500 mt-1">~Tokens</p>
                                    </div>
                                    <div class="rounded-lg bg-gray-800 p-3">
                                        <p class="text-2xl font-bold text-indigo-300">{{ formatCost(estimate.estimated_cost_usd) }}</p>
                                        <p class="text-xs text-gray-500 mt-1">~Cost</p>
                                    </div>
                                </div>
                                <div v-if="overLimit" class="flex items-start gap-2 rounded-lg border border-amber-700 bg-amber-900/20 px-3 py-2.5 text-sm text-amber-300">
                                    ⚠️ <span>This space has more than 50 items. The API enforces a 50-item batch limit — the request may be rejected.</span>
                                </div>
                            </div>
                        </template>
                        <template v-if="step === 'running'">
                            <div class="space-y-4">
                                <div>
                                    <div class="flex justify-between text-sm text-gray-400 mb-1">
                                        <span>Progress</span>
                                        <span>{{ doneCount() + failedCount() }} / {{ batchItems.length }}</span>
                                    </div>
                                    <div class="h-2 rounded-full bg-gray-800 overflow-hidden">
                                        <div class="h-2 rounded-full bg-indigo-500 transition-all" :style="{ width: progressPct() + '%' }" />
                                    </div>
                                </div>
                                <div class="space-y-1.5 max-h-56 overflow-y-auto">
                                    <div v-for="item in batchItems" :key="item.id" class="flex items-center justify-between rounded-lg bg-gray-900 px-3 py-2 text-sm">
                                        <span class="text-gray-300 truncate">{{ item.content_title ?? item.content_id ?? item.id }}</span>
                                        <span>{{ statusIcon(item._status) }}</span>
                                    </div>
                                </div>
                            </div>
                        </template>
                        <div v-if="step === 'done'" class="py-10 text-center space-y-3">
                            <p class="text-4xl">🎉</p>
                            <p class="text-lg font-semibold text-white">Batch complete!</p>
                            <p class="text-sm text-gray-400">{{ doneCount() }} completed · {{ failedCount() }} failed</p>
                        </div>
                        <div v-if="step === 'error'" class="rounded-xl border border-red-800 bg-red-900/20 p-4 text-sm text-red-300">{{ errorMsg }}</div>
                    </div>
                    <div class="flex items-center justify-end gap-3 px-6 py-4 border-t border-gray-800 bg-gray-900/40">
                        <button @click="close" class="rounded-lg border border-gray-700 bg-gray-800 px-4 py-2 text-sm text-gray-300 hover:border-gray-500 hover:text-white transition">
                            {{ step === 'done' ? 'Close' : 'Cancel' }}
                        </button>
                        <button v-if="step === 'configure'" @click="getEstimate" :disabled="!selectedFormat" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500 transition disabled:opacity-40 disabled:cursor-not-allowed">
                            Estimate cost →
                        </button>
                        <button v-if="step === 'confirm'" @click="startBatch" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500 transition">
                            Start batch
                        </button>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
.fade-enter-active, .fade-leave-active { transition: opacity 0.2s; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
</style>
