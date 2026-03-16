<script setup>
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';
import TrendChart from '../../Components/Quality/TrendChart.vue';
import SpaceLeaderboard from '../../Components/Quality/SpaceLeaderboard.vue';
import ScoreRing from '../../Components/Quality/ScoreRing.vue';
import DimensionBar from '../../Components/Quality/DimensionBar.vue';

const props = defineProps({
    spaceId: { type: String, required: true },
    spaceName: { type: String, default: '' },
    initialTrends: { type: Object, default: () => ({}) },
    initialLeaderboard: { type: Array, default: () => [] },
    initialDistribution: { type: Object, default: () => ({}) },
});

const trends = ref(props.initialTrends);
const leaderboard = ref(props.initialLeaderboard);
const distribution = ref(props.initialDistribution);
const loading = ref(false);
const period = ref(30);

const latestDate = computed(() => {
    const dates = Object.keys(trends.value).sort();
    return dates[dates.length - 1] ?? null;
});

const latestStats = computed(() => {
    if (!latestDate.value) return null;
    return trends.value[latestDate.value] ?? null;
});

const avgScore = computed(() => {
    const vals = Object.values(trends.value).map(d => d.overall).filter(v => v !== null);
    if (vals.length === 0) return null;
    return vals.reduce((a, b) => a + b, 0) / vals.length;
});

const totalScored = computed(() => {
    return Object.values(trends.value).reduce((sum, d) => sum + (d.total ?? 0), 0);
});

async function loadTrends() {
    loading.value = true;
    try {
        const from = new Date();
        from.setDate(from.getDate() - period.value);
        const res = await axios.get('/api/v1/quality/trends', {
            params: {
                space_id: props.spaceId,
                from: from.toISOString().split('T')[0],
                to: new Date().toISOString().split('T')[0],
            },
        });
        trends.value = res.data.data.trends ?? {};
        leaderboard.value = res.data.data.leaderboard ?? [];
        distribution.value = res.data.data.distribution ?? {};
    } catch (e) {
        // fail silently
    } finally {
        loading.value = false;
    }
}

function onPeriodChange(days) {
    period.value = days;
    loadTrends();
}
</script>

<template>
    <div>
        <!-- Header -->
        <div class="mb-6 flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-white">Content Quality</h1>
                <p class="mt-1 text-sm text-gray-400">{{ spaceName }}</p>
            </div>
            <div class="flex items-center gap-2">
                <span class="text-xs text-gray-500">Period:</span>
                <div class="flex rounded-lg border border-gray-700 overflow-hidden">
                    <button
                        v-for="days in [7, 14, 30, 90]"
                        :key="days"
                        class="px-3 py-1.5 text-xs transition-colors"
                        :class="period === days
                            ? 'bg-indigo-600 text-white'
                            : 'text-gray-400 hover:bg-gray-700'"
                        @click="onPeriodChange(days)"
                    >
                        {{ days }}d
                    </button>
                </div>
            </div>
        </div>

        <!-- Summary cards -->
        <div class="mb-6 grid grid-cols-2 gap-4 sm:grid-cols-4">
            <div class="rounded-xl border border-gray-800 bg-gray-900 p-5">
                <p class="text-xs text-gray-500">Avg. Score (period)</p>
                <p class="mt-1 text-2xl font-bold" :class="avgScore !== null ? 'text-white' : 'text-gray-600'">
                    {{ avgScore !== null ? Math.round(avgScore) : '—' }}
                </p>
            </div>
            <div class="rounded-xl border border-gray-800 bg-gray-900 p-5">
                <p class="text-xs text-gray-500">Scored (period)</p>
                <p class="mt-1 text-2xl font-bold text-white">{{ totalScored }}</p>
            </div>
            <div class="rounded-xl border border-gray-800 bg-gray-900 p-5">
                <p class="text-xs text-gray-500">Latest Readability</p>
                <p class="mt-1 text-2xl font-bold text-white">{{ latestStats?.readability !== null ? Math.round(latestStats?.readability ?? 0) : '—' }}</p>
            </div>
            <div class="rounded-xl border border-gray-800 bg-gray-900 p-5">
                <p class="text-xs text-gray-500">Latest SEO</p>
                <p class="mt-1 text-2xl font-bold text-white">{{ latestStats?.seo !== null ? Math.round(latestStats?.seo ?? 0) : '—' }}</p>
            </div>
        </div>

        <!-- Trend chart + leaderboard -->
        <div class="mb-6 grid grid-cols-1 gap-6 lg:grid-cols-3">
            <div class="rounded-xl border border-gray-800 bg-gray-900 p-6 lg:col-span-2">
                <h2 class="mb-4 text-base font-semibold text-white">Score Trends</h2>
                <div v-if="loading" class="flex h-64 items-center justify-center">
                    <span class="text-sm text-gray-400">Loading…</span>
                </div>
                <TrendChart v-else :trends="trends" />
            </div>

            <div>
                <SpaceLeaderboard :leaderboard="leaderboard" />
            </div>
        </div>

        <!-- Dimension distribution -->
        <div class="rounded-xl border border-gray-800 bg-gray-900 p-6">
            <h2 class="mb-4 text-base font-semibold text-white">Dimension Overview (latest)</h2>
            <div v-if="latestStats" class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <DimensionBar
                    v-for="dim in ['readability', 'seo', 'brand', 'factual', 'engagement']"
                    :key="dim"
                    :label="dim.charAt(0).toUpperCase() + dim.slice(1)"
                    :score="latestStats[dim]"
                />
            </div>
            <div v-else class="py-4 text-center text-sm text-gray-500">
                No data for selected period.
            </div>
        </div>
    </div>
</template>
