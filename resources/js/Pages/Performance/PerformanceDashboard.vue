<script setup>
import { ref, computed, onMounted } from 'vue';
import { usePage } from '@inertiajs/vue3';
import TrendChart from '../../Components/Performance/TrendChart.vue';
import ABTestManager from '../../Components/Performance/ABTestManager.vue';
import RefreshQueue from '../../Components/Performance/RefreshQueue.vue';
import InsightCards from '../../Components/Performance/InsightCards.vue';
import TopPerformers from '../../Components/Performance/TopPerformers.vue';

const page = usePage();
const currentSpace = computed(() => page.props.currentSpace);
const spaceId = computed(() => currentSpace.value?.id ?? '');

const activeTab = ref('overview');
const tabs = [
    { key: 'overview', label: 'Overview', icon: '📊' },
    { key: 'trends', label: 'Trends', icon: '📈' },
    { key: 'ab-tests', label: 'A/B Tests', icon: '🧪' },
    { key: 'refresh', label: 'Refresh Queue', icon: '🔄' },
    { key: 'insights', label: 'Insights', icon: '💡' },
];

const overview = ref(null);
const loadingOverview = ref(false);

const statCards = computed(() => {
    if (!overview.value) return [];
    const o = overview.value;
    return [
        { label: 'Total Content', value: o.total_content ?? o.content_count ?? 0, icon: '📝', color: 'indigo' },
        { label: 'Avg Score', value: Math.round(o.average_score ?? o.avg_composite_score ?? 0), icon: '⭐', color: 'amber' },
        { label: 'Total Views', value: (o.total_views ?? 0).toLocaleString(), icon: '👀', color: 'emerald' },
        { label: 'Avg Engagement', value: (o.average_engagement ?? o.avg_engagement ?? 0).toFixed(1), icon: '💬', color: 'pink' },
    ];
});

async function fetchOverview() {
    if (!spaceId.value) return;
    loadingOverview.value = true;
    try {
        const res = await fetch(`/api/v1/spaces/${spaceId.value}/performance/overview`, { credentials: 'include' });
        if (res.ok) {
            const json = await res.json();
            overview.value = json.data ?? json ?? {};
        }
    } catch (e) {
        console.error('Failed to fetch overview', e);
    } finally {
        loadingOverview.value = false;
    }
}

onMounted(fetchOverview);
</script>

<template>
    <div>
        <div class="mb-8">
            <h1 class="text-2xl font-bold text-white">Performance Dashboard</h1>
            <p class="text-gray-500 mt-1">Track content performance, run A/B tests, and discover insights</p>
        </div>

        <!-- Overview Stats -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
            <div v-if="loadingOverview" v-for="n in 4" :key="n"
                 class="bg-gray-900 rounded-xl border border-gray-800 p-5 animate-pulse">
                <div class="h-8 bg-gray-800 rounded w-1/2 mb-2"></div>
                <div class="h-4 bg-gray-800 rounded w-2/3"></div>
            </div>
            <div v-else v-for="stat in statCards" :key="stat.label"
                 class="bg-gray-900 rounded-xl border border-gray-800 p-5">
                <div class="flex items-center justify-between">
                    <span class="text-2xl">{{ stat.icon }}</span>
                    <span class="text-2xl font-bold text-white">{{ stat.value }}</span>
                </div>
                <p class="mt-2 text-sm text-gray-500">{{ stat.label }}</p>
            </div>
        </div>

        <!-- Tabs -->
        <div class="flex items-center gap-1 mb-6 border-b border-gray-800 pb-px overflow-x-auto">
            <button
                v-for="tab in tabs"
                :key="tab.key"
                @click="activeTab = tab.key"
                class="px-4 py-2.5 text-sm font-medium transition whitespace-nowrap border-b-2 -mb-px"
                :class="activeTab === tab.key
                    ? 'border-indigo-500 text-white'
                    : 'border-transparent text-gray-500 hover:text-gray-300'"
            >
                <span class="mr-1.5">{{ tab.icon }}</span>
                {{ tab.label }}
            </button>
        </div>

        <!-- Tab content -->
        <div v-if="!spaceId" class="py-12 text-center text-gray-500">
            Select a space to view performance data.
        </div>

        <template v-else>
            <!-- Overview Tab -->
            <div v-if="activeTab === 'overview'" class="space-y-6">
                <TopPerformers :space-id="spaceId" />
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <TrendChart :space-id="spaceId" metric="views" />
                    <InsightCards :space-id="spaceId" />
                </div>
            </div>

            <!-- Trends Tab -->
            <div v-if="activeTab === 'trends'">
                <TrendChart :space-id="spaceId" metric="composite_score" />
            </div>

            <!-- A/B Tests Tab -->
            <div v-if="activeTab === 'ab-tests'">
                <ABTestManager :space-id="spaceId" />
            </div>

            <!-- Refresh Queue Tab -->
            <div v-if="activeTab === 'refresh'">
                <RefreshQueue :space-id="spaceId" />
            </div>

            <!-- Insights Tab -->
            <div v-if="activeTab === 'insights'">
                <InsightCards :space-id="spaceId" />
            </div>
        </template>
    </div>
</template>
