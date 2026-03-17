<script setup>
import { ref, onMounted, onUnmounted, computed } from 'vue';
import axios from 'axios';

const props = defineProps({
    spaceId: { type: String, required: true },
    session: { type: Object, required: true },
});

const emit = defineEmits(['import-complete']);

const importing = ref(false);
const paused = ref(false);
const error = ref(null);
const progress = ref(null);
const errors = ref([]);
let pollInterval = null;
let eventSource = null;

const overallPercent = computed(() => {
    if (!progress.value) return 0;
    const p = progress.value;
    const total = (p.content?.total ?? 0) + (p.media?.total ?? 0) + (p.users?.total ?? 0) + (p.taxonomies?.total ?? 0);
    const done = (p.content?.completed ?? 0) + (p.media?.completed ?? 0) + (p.users?.completed ?? 0) + (p.taxonomies?.completed ?? 0);
    return total > 0 ? Math.round((done / total) * 100) : 0;
});

const categories = computed(() => {
    if (!progress.value) return [];
    const p = progress.value;
    return [
        { key: 'content', label: 'Content', icon: '📝', ...normalizeCategory(p.content) },
        { key: 'media', label: 'Media', icon: '🖼️', ...normalizeCategory(p.media) },
        { key: 'users', label: 'Users', icon: '👥', ...normalizeCategory(p.users) },
        { key: 'taxonomies', label: 'Taxonomies', icon: '🏷️', ...normalizeCategory(p.taxonomies) },
    ].filter(c => c.total > 0);
});

function normalizeCategory(cat) {
    if (!cat) return { completed: 0, total: 0, failed: 0 };
    return {
        completed: cat.completed ?? cat.done ?? 0,
        total: cat.total ?? 0,
        failed: cat.failed ?? cat.errors ?? 0,
    };
}

onMounted(async () => {
    await startImport();
});

onUnmounted(() => {
    stopPolling();
});

async function startImport() {
    importing.value = true;
    error.value = null;

    try {
        await axios.post(`/api/v1/spaces/${props.spaceId}/migrations/${props.session.id}/execute`);
        startPolling();
    } catch (err) {
        error.value = err.response?.data?.message ?? 'Failed to start import';
        importing.value = false;
    }
}

function startPolling() {
    // Try SSE first, fall back to polling
    try {
        const sseUrl = `/api/v1/spaces/${props.spaceId}/migrations/${props.session.id}/progress`;
        eventSource = new EventSource(sseUrl);

        eventSource.onmessage = (event) => {
            handleProgressUpdate(JSON.parse(event.data));
        };

        eventSource.onerror = () => {
            // Fall back to polling
            eventSource?.close();
            eventSource = null;
            startPollFallback();
        };
    } catch {
        startPollFallback();
    }
}

function startPollFallback() {
    if (pollInterval) return;
    pollInterval = setInterval(async () => {
        try {
            const { data } = await axios.get(
                `/api/v1/spaces/${props.spaceId}/migrations/${props.session.id}/progress`
            );
            handleProgressUpdate(data.data ?? data);
        } catch {
            // Silently retry
        }
    }, 2000);
}

function handleProgressUpdate(data) {
    progress.value = data;

    if (data.errors?.length) {
        errors.value = data.errors;
    }

    if (data.status === 'completed' || data.status === 'complete') {
        stopPolling();
        importing.value = false;
        emit('import-complete', data);
    }

    if (data.status === 'paused') {
        paused.value = true;
    }

    if (data.status === 'failed') {
        stopPolling();
        importing.value = false;
        error.value = data.message ?? 'Import failed';
    }
}

function stopPolling() {
    if (eventSource) {
        eventSource.close();
        eventSource = null;
    }
    if (pollInterval) {
        clearInterval(pollInterval);
        pollInterval = null;
    }
}

async function pauseImport() {
    try {
        await axios.post(`/api/v1/spaces/${props.spaceId}/migrations/${props.session.id}/pause`);
        paused.value = true;
    } catch (err) {
        error.value = err.response?.data?.message ?? 'Failed to pause';
    }
}

async function resumeImport() {
    try {
        await axios.post(`/api/v1/spaces/${props.spaceId}/migrations/${props.session.id}/resume`);
        paused.value = false;
        if (!pollInterval && !eventSource) startPolling();
    } catch (err) {
        error.value = err.response?.data?.message ?? 'Failed to resume';
    }
}
</script>

<template>
    <div class="space-y-6">
        <div>
            <h2 class="text-lg font-semibold text-white mb-1">Importing Content</h2>
            <p class="text-sm text-gray-500">Your content is being migrated to Numen.</p>
        </div>

        <!-- Error -->
        <div v-if="error" class="p-4 bg-red-900/20 border border-red-800 rounded-lg">
            <p class="text-sm text-red-400">{{ error }}</p>
        </div>

        <!-- Overall Progress -->
        <div class="space-y-2">
            <div class="flex items-center justify-between text-sm">
                <span class="text-gray-300 font-medium">Overall Progress</span>
                <span class="text-white font-bold">{{ overallPercent }}%</span>
            </div>
            <div class="w-full h-3 bg-gray-800 rounded-full overflow-hidden">
                <div
                    class="h-full rounded-full transition-all duration-500 ease-out"
                    :class="paused ? 'bg-amber-500' : 'bg-indigo-500'"
                    :style="{ width: overallPercent + '%' }"
                />
            </div>
            <div v-if="paused" class="flex items-center gap-2 text-amber-400 text-sm">
                <span>⏸️</span> Import paused
            </div>
        </div>

        <!-- Category Progress -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div
                v-for="cat in categories"
                :key="cat.key"
                class="p-4 bg-gray-800/50 border border-gray-700 rounded-lg"
            >
                <div class="flex items-center justify-between mb-2">
                    <span class="text-sm text-gray-300">{{ cat.icon }} {{ cat.label }}</span>
                    <span class="text-xs text-gray-400">{{ cat.completed }} / {{ cat.total }}</span>
                </div>
                <div class="w-full h-2 bg-gray-700 rounded-full overflow-hidden">
                    <div
                        class="h-full bg-emerald-500 rounded-full transition-all duration-300"
                        :style="{ width: (cat.total > 0 ? (cat.completed / cat.total) * 100 : 0) + '%' }"
                    />
                </div>
                <div v-if="cat.failed > 0" class="mt-1 text-xs text-red-400">
                    {{ cat.failed }} failed
                </div>
            </div>
        </div>

        <!-- Error List -->
        <div v-if="errors.length > 0" class="space-y-2">
            <h3 class="text-sm font-medium text-red-400">Failed Items</h3>
            <div class="max-h-48 overflow-y-auto space-y-1">
                <div
                    v-for="(err, index) in errors"
                    :key="index"
                    class="p-2 bg-red-900/10 border border-red-900/30 rounded text-xs text-red-300"
                >
                    <span class="font-medium">{{ err.item ?? err.title ?? `Item ${index + 1}` }}:</span>
                    {{ err.message ?? err.error ?? 'Unknown error' }}
                </div>
            </div>
        </div>

        <!-- Pause/Resume Controls -->
        <div v-if="importing" class="flex justify-center gap-4 pt-4">
            <button
                v-if="!paused"
                @click="pauseImport"
                class="px-5 py-2.5 bg-amber-600 text-white rounded-lg text-sm font-medium hover:bg-amber-500 transition flex items-center gap-2"
            >
                ⏸️ Pause
            </button>
            <button
                v-else
                @click="resumeImport"
                class="px-5 py-2.5 bg-emerald-600 text-white rounded-lg text-sm font-medium hover:bg-emerald-500 transition flex items-center gap-2"
            >
                ▶️ Resume
            </button>
        </div>
    </div>
</template>
