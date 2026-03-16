<script setup lang="ts">
import { ref, onMounted, watch, computed } from 'vue';

interface Props {
    spaceId: string;
    days?: number;
}

const props = withDefaults(defineProps<Props>(), {
    days: 30,
});

interface AnalysisItem {
    differentiation_score: number;
    similarity_score: number;
    analyzed_at: string | null;
    competitor_content?: {
        title?: string;
        external_url?: string;
    };
}

const analyses = ref<AnalysisItem[]>([]);
const loading = ref(true);
const error = ref<string | null>(null);

async function fetchAnalyses(): Promise<void> {
    loading.value = true;
    error.value = null;
    try {
        const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
        const token = match ? decodeURIComponent(match[1]) : '';

        const url = new URL('/api/v1/competitor/differentiation', window.location.origin);
        url.searchParams.set('space_id', props.spaceId);
        url.searchParams.set('per_page', '50');

        const res = await fetch(url.toString(), {
            credentials: 'include',
            headers: { Accept: 'application/json', 'X-XSRF-TOKEN': token },
        });
        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        const json = await res.json();
        analyses.value = json.data as AnalysisItem[];
    } catch (e: unknown) {
        error.value = e instanceof Error ? e.message : String(e);
    } finally {
        loading.value = false;
    }
}

// Simple trend data — last N items sorted by date
const trendData = computed(() => {
    return [...analyses.value]
        .filter(a => a.analyzed_at !== null)
        .sort((a, b) => new Date(a.analyzed_at!).getTime() - new Date(b.analyzed_at!).getTime())
        .slice(-20);
});

// SVG chart dimensions
const chartWidth = 400;
const chartHeight = 120;
const padding = { top: 10, right: 10, bottom: 20, left: 30 };

const innerWidth = computed(() => chartWidth - padding.left - padding.right);
const innerHeight = computed(() => chartHeight - padding.top - padding.bottom);

function xScale(index: number): number {
    const count = trendData.value.length;
    if (count <= 1) return padding.left;
    return padding.left + (index / (count - 1)) * innerWidth.value;
}

function yScale(score: number): number {
    return padding.top + (1 - score) * innerHeight.value;
}

const diffPath = computed(() => {
    const pts = trendData.value;
    if (pts.length === 0) return '';
    return pts.map((p, i) => `${i === 0 ? 'M' : 'L'}${xScale(i)},${yScale(p.differentiation_score)}`).join(' ');
});

const simPath = computed(() => {
    const pts = trendData.value;
    if (pts.length === 0) return '';
    return pts.map((p, i) => `${i === 0 ? 'M' : 'L'}${xScale(i)},${yScale(p.similarity_score)}`).join(' ');
});

onMounted(fetchAnalyses);
watch(() => [props.spaceId, props.days], fetchAnalyses);
</script>

<template>
    <div class="bg-gray-900 rounded-xl border border-gray-800 p-5">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-sm font-semibold text-white flex items-center gap-2">
                <span>📈</span> Differentiation Trend
            </h2>
            <button
                class="text-xs text-gray-400 hover:text-white transition"
                :disabled="loading"
                @click="fetchAnalyses"
            >
                ↻
            </button>
        </div>

        <div v-if="loading" class="animate-pulse">
            <div class="h-32 bg-gray-800 rounded"></div>
        </div>

        <div v-else-if="error" class="text-red-400 text-sm">{{ error }}</div>

        <div v-else-if="trendData.length > 0">
            <!-- SVG Chart -->
            <svg
                :viewBox="`0 0 ${chartWidth} ${chartHeight}`"
                class="w-full"
                :height="chartHeight"
            >
                <!-- Grid lines -->
                <line
                    v-for="y in [0.25, 0.5, 0.75]"
                    :key="y"
                    :x1="padding.left"
                    :y1="yScale(y)"
                    :x2="chartWidth - padding.right"
                    :y2="yScale(y)"
                    stroke="#374151"
                    stroke-width="1"
                    stroke-dasharray="4,4"
                />
                <!-- Axes labels -->
                <text
                    v-for="y in [0, 0.5, 1]"
                    :key="y"
                    :x="padding.left - 4"
                    :y="yScale(y) + 4"
                    text-anchor="end"
                    class="text-xs fill-gray-500"
                    font-size="10"
                    fill="#6b7280"
                >{{ Math.round(y * 100) }}</text>

                <!-- Similarity line -->
                <path
                    v-if="simPath"
                    :d="simPath"
                    fill="none"
                    stroke="#f97316"
                    stroke-width="2"
                    opacity="0.7"
                />

                <!-- Differentiation line -->
                <path
                    v-if="diffPath"
                    :d="diffPath"
                    fill="none"
                    stroke="#6366f1"
                    stroke-width="2"
                />

                <!-- Data points -->
                <circle
                    v-for="(point, i) in trendData"
                    :key="i"
                    :cx="xScale(i)"
                    :cy="yScale(point.differentiation_score)"
                    r="3"
                    fill="#6366f1"
                />
            </svg>

            <!-- Legend -->
            <div class="flex items-center gap-4 mt-2 text-xs text-gray-400">
                <span class="flex items-center gap-1">
                    <span class="inline-block w-3 h-0.5 bg-indigo-500"></span> Differentiation
                </span>
                <span class="flex items-center gap-1">
                    <span class="inline-block w-3 h-0.5 bg-orange-500 opacity-70"></span> Similarity
                </span>
            </div>
        </div>

        <div v-else class="text-gray-500 text-sm text-center py-6">
            No trend data available yet.
        </div>
    </div>
</template>
