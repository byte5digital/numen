<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue';

interface Props {
    spaceId: string;
    contentId?: string | null;
    briefId?: string | null;
}

const props = withDefaults(defineProps<Props>(), {
    contentId: null,
    briefId: null,
});

interface Summary {
    total_analyses: number;
    avg_differentiation_score: number;
    avg_similarity_score: number;
    max_differentiation_score: number;
    min_differentiation_score: number;
    last_analyzed_at: string | null;
}

const summary = ref<Summary | null>(null);
const loading = ref(true);
const error = ref<string | null>(null);

async function fetchSummary(): Promise<void> {
    loading.value = true;
    error.value = null;
    try {
        const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
        const token = match ? decodeURIComponent(match[1]) : '';

        const url = new URL('/api/v1/competitor/differentiation/summary', window.location.origin);
        url.searchParams.set('space_id', props.spaceId);

        const res = await fetch(url.toString(), {
            credentials: 'include',
            headers: {
                Accept: 'application/json',
                'X-XSRF-TOKEN': token,
            },
        });
        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        const json = await res.json();
        summary.value = json.data as Summary;
    } catch (e: unknown) {
        error.value = e instanceof Error ? e.message : String(e);
    } finally {
        loading.value = false;
    }
}

const differentiationPercent = computed(() => {
    if (!summary.value) return 0;
    return Math.round(summary.value.avg_differentiation_score * 100);
});

const scoreClass = computed(() => {
    const score = differentiationPercent.value;
    if (score >= 70) return 'text-green-400';
    if (score >= 40) return 'text-yellow-400';
    return 'text-red-400';
});

onMounted(fetchSummary);
watch(() => [props.spaceId, props.contentId, props.briefId], fetchSummary);
</script>

<template>
    <div class="bg-gray-900 rounded-xl border border-gray-800 p-5">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-sm font-semibold text-white flex items-center gap-2">
                <span>📊</span> Differentiation Score
            </h2>
            <button
                class="text-xs text-gray-400 hover:text-white transition"
                :disabled="loading"
                @click="fetchSummary"
            >
                ↻
            </button>
        </div>

        <div v-if="loading" class="animate-pulse space-y-3">
            <div class="h-12 bg-gray-800 rounded"></div>
            <div class="h-4 bg-gray-800 rounded w-3/4"></div>
        </div>

        <div v-else-if="error" class="text-red-400 text-sm">{{ error }}</div>

        <div v-else-if="summary">
            <!-- Big Score -->
            <div class="flex items-end gap-2 mb-4">
                <span :class="['text-5xl font-bold', scoreClass]">{{ differentiationPercent }}%</span>
                <span class="text-gray-400 text-sm mb-1">avg. differentiation</span>
            </div>

            <!-- Stats grid -->
            <div class="grid grid-cols-2 gap-3 text-sm">
                <div class="bg-gray-800 rounded-lg p-3">
                    <div class="text-gray-400 text-xs mb-1">Avg. Similarity</div>
                    <div class="text-white font-semibold">
                        {{ Math.round(summary.avg_similarity_score * 100) }}%
                    </div>
                </div>
                <div class="bg-gray-800 rounded-lg p-3">
                    <div class="text-gray-400 text-xs mb-1">Total Analyses</div>
                    <div class="text-white font-semibold">{{ summary.total_analyses }}</div>
                </div>
                <div class="bg-gray-800 rounded-lg p-3">
                    <div class="text-gray-400 text-xs mb-1">Best Score</div>
                    <div class="text-green-400 font-semibold">
                        {{ Math.round(summary.max_differentiation_score * 100) }}%
                    </div>
                </div>
                <div class="bg-gray-800 rounded-lg p-3">
                    <div class="text-gray-400 text-xs mb-1">Worst Score</div>
                    <div class="text-red-400 font-semibold">
                        {{ Math.round(summary.min_differentiation_score * 100) }}%
                    </div>
                </div>
            </div>

            <div v-if="summary.last_analyzed_at" class="mt-3 text-xs text-gray-500">
                Last analyzed: {{ new Date(summary.last_analyzed_at).toLocaleDateString() }}
            </div>
        </div>

        <div v-else class="text-gray-500 text-sm">No analysis data yet.</div>
    </div>
</template>
