<script setup>
import { ref, computed, watch, onMounted } from 'vue';

const props = defineProps({
    spaceId: { type: String, required: true },
    metric: { type: String, default: 'views' },
});

const period = ref('30d');
const periods = [
    { value: '7d', label: '7 days' },
    { value: '30d', label: '30 days' },
    { value: '90d', label: '90 days' },
];
const activeMetric = ref(props.metric);
const metrics = ['views', 'engagement', 'conversions', 'composite_score'];
const loading = ref(false);
const snapshots = ref([]);

const metricLabels = {
    views: 'Views',
    engagement: 'Engagement',
    conversions: 'Conversions',
    composite_score: 'Composite Score',
};

const metricColors = {
    views: '#6366f1',
    engagement: '#10b981',
    conversions: '#f59e0b',
    composite_score: '#ec4899',
};

async function fetchTrends() {
    loading.value = true;
    try {
        const res = await fetch(`/api/v1/spaces/${props.spaceId}/performance/snapshots?period=${period.value}`, {
            credentials: 'include',
        });
        if (res.ok) {
            const json = await res.json();
            snapshots.value = json.data ?? json ?? [];
        }
    } catch (e) {
        console.error('Failed to fetch trends', e);
    } finally {
        loading.value = false;
    }
}

const chartPoints = computed(() => {
    if (!snapshots.value.length) return [];
    const data = snapshots.value.slice().sort((a, b) => new Date(a.snapped_at ?? a.created_at) - new Date(b.snapped_at ?? b.created_at));
    const key = activeMetric.value;
    const values = data.map(s => {
        const metrics = s.metrics ?? s;
        return parseFloat(metrics[key] ?? metrics.views ?? 0);
    });
    const max = Math.max(...values, 1);
    return values.map((v, i) => ({
        x: (i / Math.max(values.length - 1, 1)) * 100,
        y: 100 - (v / max) * 80 - 10,
        value: v,
        date: data[i].snapped_at ?? data[i].created_at ?? '',
    }));
});

const svgPath = computed(() => {
    if (chartPoints.value.length < 2) return '';
    return chartPoints.value.map((p, i) => `${i === 0 ? 'M' : 'L'} ${p.x} ${p.y}`).join(' ');
});

const svgArea = computed(() => {
    if (chartPoints.value.length < 2) return '';
    const line = chartPoints.value.map((p, i) => `${i === 0 ? 'M' : 'L'} ${p.x} ${p.y}`).join(' ');
    const last = chartPoints.value[chartPoints.value.length - 1];
    const first = chartPoints.value[0];
    return `${line} L ${last.x} 95 L ${first.x} 95 Z`;
});

watch([period, activeMetric], fetchTrends);
onMounted(fetchTrends);
</script>

<template>
    <div class="bg-gray-900 rounded-xl border border-gray-800 p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-sm font-semibold text-white">Performance Trends</h3>
            <div class="flex items-center gap-2">
                <button
                    v-for="m in metrics"
                    :key="m"
                    @click="activeMetric = m"
                    class="px-2 py-1 text-xs rounded-md transition"
                    :class="activeMetric === m
                        ? 'bg-indigo-500/20 text-indigo-300 border border-indigo-500/30'
                        : 'text-gray-500 hover:text-gray-300'"
                >
                    {{ metricLabels[m] }}
                </button>
            </div>
        </div>

        <div class="flex gap-2 mb-4">
            <button
                v-for="p in periods"
                :key="p.value"
                @click="period = p.value"
                class="px-3 py-1 text-xs rounded-full transition"
                :class="period === p.value
                    ? 'bg-gray-700 text-white'
                    : 'text-gray-500 hover:text-gray-300'"
            >
                {{ p.label }}
            </button>
        </div>

        <div v-if="loading" class="h-48 flex items-center justify-center">
            <span class="text-gray-500 text-sm">Loading trends…</span>
        </div>

        <div v-else-if="chartPoints.length < 2" class="h-48 flex items-center justify-center">
            <span class="text-gray-600 text-sm">Not enough data to display trends</span>
        </div>

        <svg v-else viewBox="0 0 100 100" preserveAspectRatio="none" class="w-full h-48">
            <!-- Grid lines -->
            <line v-for="y in [25, 50, 75]" :key="y"
                  x1="0" :y1="y" x2="100" :y2="y"
                  stroke="#374151" stroke-width="0.3" stroke-dasharray="2,2" />

            <!-- Area fill -->
            <path :d="svgArea" :fill="metricColors[activeMetric]" fill-opacity="0.1" />

            <!-- Line -->
            <path :d="svgPath" fill="none" :stroke="metricColors[activeMetric]"
                  stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />

            <!-- Data points -->
            <circle v-for="(point, i) in chartPoints" :key="i"
                    :cx="point.x" :cy="point.y" r="1.5"
                    :fill="metricColors[activeMetric]" />
        </svg>
    </div>
</template>
