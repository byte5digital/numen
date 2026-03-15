<script setup>
import { ref, computed, watch, onUnmounted } from 'vue';
import FormatSelector from './FormatSelector.vue';
import RepurposedContentCard from './RepurposedContentCard.vue';

const props = defineProps({
    content:  { type: Object, required: true },
    open:     { type: Boolean, default: false },
});
const emit = defineEmits(['close', 'repurposed']);
const formats = ref([]); const personas = ref([]); const repurposedList = ref([]);
const selectedFormat = ref(null); const selectedPersona = ref(null);
const loadingFormats = ref(false); const loadingList = ref(false);
const submitting = ref(false); const error = ref(null);
let pollTimer = null;
function hasInFlight() { return repurposedList.value.some(i => i.status === 'pending' || i.status === 'processing'); }
function stopPolling() { if (pollTimer) { clearInterval(pollTimer); pollTimer = null; } }
onUnmounted(stopPolling);
async function csrfCookie() { await fetch('/sanctum/csrf-cookie', { credentials: 'include' }); }
function xsrfToken() { const m = document.cookie.match(/XSRF-TOKEN=([^;]+)/); return m ? decodeURIComponent(m[1]) : ''; }
async function apiFetch(url, options = {}) {
    await csrfCookie();
    const res = await fetch(url, { credentials: 'include', headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-XSRF-TOKEN': xsrfToken(), ...options.headers }, ...options });
    if (!res.ok) { const b = await res.json().catch(() => ({})); throw new Error(b.message ?? 'HTTP ' + res.status); }
    return res.status === 204 ? null : res.json();
}
function startPolling() {
    if (pollTimer) return;
    pollTimer = setInterval(async () => {
        const inflight = repurposedList.value.filter(i => i.status === 'pending' || i.status === 'processing');
        if (!inflight.length) { stopPolling(); return; }
        for (const item of inflight) {
            try {
                const res = await apiFetch('/api/repurposed/' + item.id);
                const updated = res && res.data ? res.data : res;
                const idx = repurposedList.value.findIndex(i => i.id === item.id);
                if (idx !== -1) { repurposedList.value[idx] = updated; if (updated.status === 'completed') emit('repurposed', updated); }
            } catch {}
        }
        if (!hasInFlight()) stopPolling();
    }, 3000);
}
const formatMap = computed(() => { const m = {}; for (const f of formats.value) m[f.key] = f; return m; });
const groupedByFormat = computed(() => { const g = {}; for (const item of repurposedList.value) { if (!g[item.format_key]) g[item.format_key] = []; g[item.format_key].push(item); } return g; });
async function loadFormats() {
    if (formats.value.length) return;
    loadingFormats.value = true;
    try { const r = await apiFetch('/api/format-templates/supported'); formats.value = (r && r.data) ? r.data : (r || []); }
    catch (e) { error.value = e.message; } finally { loadingFormats.value = false; }
}
async function loadPersonas() {
    if (personas.value.length) return;
    try { const r = await apiFetch('/api/personas'); personas.value = (r && r.data) ? r.data : (r || []); } catch {}
}
async function loadRepurposed() {
    if (!props.content || !props.content.id) return;
    loadingList.value = true;
    try { const r = await apiFetch('/api/content/' + props.content.id + '/repurposed'); repurposedList.value = (r && r.data) ? r.data : (r || []); if (hasInFlight()) startPolling(); }
    catch (e) { error.value = e.message; } finally { loadingList.value = false; }
}
async function submitRepurpose() {
    if (!selectedFormat.value || submitting.value) return;
    submitting.value = true; error.value = null;
    try {
        const body = { format_key: selectedFormat.value };
        if (selectedPersona.value) body.persona_id = selectedPersona.value;
        const r = await apiFetch('/api/content/' + props.content.id + '/repurpose', { method: 'POST', body: JSON.stringify(body) });
        repurposedList.value.unshift(r && r.data ? r.data : r);
        selectedFormat.value = null; startPolling();
    } catch (e) { error.value = e.message; } finally { submitting.value = false; }
}
async function retryItem(item) {
    error.value = null;
    try {
        const body = { format_key: item.format_key };
        if (item.persona_id) body.persona_id = item.persona_id;
        const r = await apiFetch('/api/content/' + props.content.id + '/repurpose', { method: 'POST', body: JSON.stringify(body) });
        repurposedList.value.unshift(r && r.data ? r.data : r); startPolling();
    } catch (e) { error.value = e.message; }
}
watch(() => props.open, (val) => { if (val) { loadFormats(); loadPersonas(); loadRepurposed(); } else stopPolling(); }, { immediate: true });
</script>

<template>
    <Teleport to="body">
        <Transition name="backdrop">
            <div v-if="open" class="fixed inset-0 z-40 bg-black/60 backdrop-blur-sm" @click="emit('close')" />
        </Transition>
        <Transition name="slide-over">
            <div v-if="open" class="fixed inset-y-0 right-0 z-50 flex flex-col w-full max-w-lg bg-gray-900 border-l border-gray-800 shadow-2xl">
                <div class="flex items-center justify-between px-5 py-4 border-b border-gray-800 shrink-0">
                    <div>
                        <h2 class="text-sm font-semibold text-white">♻️ Repurpose Content</h2>
                        <p class="text-xs text-gray-500 mt-0.5 truncate max-w-xs">{{ content?.title }}</p>
                    </div>
                    <button @click="emit('close')" class="text-gray-500 hover:text-white transition text-lg leading-none">✕</button>
                </div>
                <div class="flex-1 overflow-y-auto px-5 py-5 space-y-6">
                    <div v-if="error" class="flex items-start gap-2 px-3 py-2 bg-red-900/30 border border-red-500/30 rounded-lg text-red-400 text-xs">
                        <span class="shrink-0">⚠️</span>
                        <span class="flex-1">{{ error }}</span>
                        <button @click="error = null" class="shrink-0 opacity-60 hover:opacity-100">✕</button>
                    </div>
                    <section>
                        <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3">Choose format</h3>
                        <div v-if="loadingFormats" class="text-xs text-gray-600 text-center py-4">Loading formats…</div>
                        <FormatSelector v-else :formats="formats" v-model="selectedFormat" />
                    </section>
                    <section v-if="personas.length">
                        <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">
                            Persona <span class="text-gray-600 normal-case font-normal">(optional)</span>
                        </h3>
                        <select v-model="selectedPersona" class="w-full bg-gray-950 border border-gray-700 text-sm text-white rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                            <option :value="null">— No persona —</option>
                            <option v-for="p in personas" :key="p.id" :value="p.id">{{ p.name }}</option>
                        </select>
                    </section>
                    <button
                        :disabled="!selectedFormat || submitting"
                        @click="submitRepurpose"
                        class="w-full py-2.5 rounded-xl text-sm font-semibold text-white transition focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        :class="selectedFormat && !submitting ? 'bg-indigo-600 hover:bg-indigo-500' : 'bg-gray-800 opacity-50 cursor-not-allowed'"
                    >
                        {{ submitting ? '⏳ Repurposing…' : '✨ Repurpose' }}
                    </button>
                    <section v-if="repurposedList.length">
                        <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3">Results</h3>
                        <div v-for="(items, fmtKey) in groupedByFormat" :key="fmtKey" class="mb-4">
                            <p class="text-xs text-gray-500 mb-1.5">{{ formatMap[fmtKey] ? formatMap[fmtKey].label : fmtKey }}</p>
                            <div class="space-y-2">
                                <RepurposedContentCard
                                    v-for="item in items"
                                    :key="item.id"
                                    :item="item"
                                    :format="formatMap[fmtKey] ?? {}"
                                    @retry="retryItem"
                                />
                            </div>
                        </div>
                    </section>
                    <div v-else-if="!loadingList" class="text-xs text-gray-600 text-center py-4">
                        No repurposed content yet. Choose a format above and click Repurpose.
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
.backdrop-enter-active, .backdrop-leave-active { transition: opacity 0.25s ease; }
.backdrop-enter-from, .backdrop-leave-to { opacity: 0; }
.slide-over-enter-active, .slide-over-leave-active { transition: transform 0.3s ease; }
.slide-over-enter-from, .slide-over-leave-to { transform: translateX(100%); }
</style>
