<script setup lang="ts">
import { ref, onMounted, watch } from 'vue';
import DifferentiationScoreWidget from './DifferentiationScoreWidget.vue';
import DifferentiationTrendChart from './DifferentiationTrendChart.vue';
import CompetitorSourceManager from './CompetitorSourceManager.vue';

interface Props {
    spaceId: string;
}

const props = defineProps<Props>();

interface ContentItem {
    id: string;
    title: string | null;
    external_url: string;
    published_at: string | null;
    crawled_at: string | null;
    source?: {
        name: string;
    };
}

interface Analysis {
    id: string;
    differentiation_score: number;
    similarity_score: number;
    analyzed_at: string | null;
    competitor_content?: ContentItem;
}

const recentContent = ref<ContentItem[]>([]);
const recentAnalyses = ref<Analysis[]>([]);
const contentLoading = ref(true);
const analysesLoading = ref(true);

function getXsrfToken(): string {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}

async function fetchRecentContent(): Promise<void> {
    contentLoading.value = true;
    try {
        const res = await fetch(
            `/api/v1/competitor/content?space_id=${props.spaceId}&per_page=5`,
            {
                credentials: 'include',
                headers: { Accept: 'application/json', 'X-XSRF-TOKEN': getXsrfToken() },
            },
        );
        if (!res.ok) return;
        const json = await res.json();
        recentContent.value = json.data as ContentItem[];
    } finally {
        contentLoading.value = false;
    }
}

async function fetchRecentAnalyses(): Promise<void> {
    analysesLoading.value = true;
    try {
        const res = await fetch(
            `/api/v1/competitor/differentiation?space_id=${props.spaceId}&per_page=5`,
            {
                credentials: 'include',
                headers: { Accept: 'application/json', 'X-XSRF-TOKEN': getXsrfToken() },
            },
        );
        if (!res.ok) return;
        const json = await res.json();
        recentAnalyses.value = json.data as Analysis[];
    } finally {
        analysesLoading.value = false;
    }
}

function scoreColor(score: number): string {
    if (score >= 0.7) return 'text-green-400';
    if (score >= 0.4) return 'text-yellow-400';
    return 'text-red-400';
}

onMounted(() => {
    fetchRecentContent();
    fetchRecentAnalyses();
});

watch(() => props.spaceId, () => {
    fetchRecentContent();
    fetchRecentAnalyses();
});
</script>

<template>
    <div class="space-y-6">
        <!-- Header -->
        <div class="flex items-center justify-between">
            <h1 class="text-xl font-bold text-white flex items-center gap-2">
                <span>🔍</span> Competitor Intelligence
            </h1>
        </div>

        <!-- Top row: Score + Trend -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
            <DifferentiationScoreWidget :space-id="spaceId" />
            <DifferentiationTrendChart :space-id="spaceId" />
        </div>

        <!-- Bottom row: Sources + Recent Activity -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
            <!-- Source Manager -->
            <CompetitorSourceManager :space-id="spaceId" />

            <!-- Recent Competitor Content -->
            <div class="bg-gray-900 rounded-xl border border-gray-800 p-5">
                <h2 class="text-sm font-semibold text-white flex items-center gap-2 mb-4">
                    <span>📰</span> Recent Competitor Content
                </h2>

                <div v-if="contentLoading" class="animate-pulse space-y-2">
                    <div v-for="i in 3" :key="i" class="h-10 bg-gray-800 rounded"></div>
                </div>

                <div v-else class="space-y-2">
                    <div
                        v-for="item in recentContent"
                        :key="item.id"
                        class="bg-gray-800 rounded-lg px-3 py-2"
                    >
                        <a
                            :href="item.external_url"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="text-sm text-indigo-300 hover:text-indigo-200 transition truncate block"
                        >
                            {{ item.title ?? item.external_url }}
                        </a>
                        <div class="flex items-center gap-2 mt-1 text-xs text-gray-500">
                            <span v-if="item.source">{{ item.source.name }}</span>
                            <span v-if="item.crawled_at">
                                · {{ new Date(item.crawled_at).toLocaleDateString() }}
                            </span>
                        </div>
                    </div>
                    <div v-if="recentContent.length === 0" class="text-gray-500 text-sm text-center py-3">
                        No competitor content crawled yet.
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Analyses -->
        <div class="bg-gray-900 rounded-xl border border-gray-800 p-5">
            <h2 class="text-sm font-semibold text-white flex items-center gap-2 mb-4">
                <span>🧪</span> Recent Differentiation Analyses
            </h2>

            <div v-if="analysesLoading" class="animate-pulse space-y-2">
                <div v-for="i in 3" :key="i" class="h-12 bg-gray-800 rounded"></div>
            </div>

            <div v-else class="divide-y divide-gray-800">
                <div
                    v-for="analysis in recentAnalyses"
                    :key="analysis.id"
                    class="flex items-center justify-between py-3"
                >
                    <div class="min-w-0">
                        <div class="text-sm text-gray-300 truncate">
                            {{ analysis.competitor_content?.title ?? 'Untitled' }}
                        </div>
                        <div v-if="analysis.analyzed_at" class="text-xs text-gray-500 mt-0.5">
                            {{ new Date(analysis.analyzed_at).toLocaleString() }}
                        </div>
                    </div>
                    <div class="flex items-center gap-4 ml-4 shrink-0 text-sm">
                        <div class="text-center">
                            <div :class="['font-semibold', scoreColor(analysis.differentiation_score)]">
                                {{ Math.round(analysis.differentiation_score * 100) }}%
                            </div>
                            <div class="text-xs text-gray-500">diff</div>
                        </div>
                        <div class="text-center">
                            <div class="font-semibold text-orange-400">
                                {{ Math.round(analysis.similarity_score * 100) }}%
                            </div>
                            <div class="text-xs text-gray-500">sim</div>
                        </div>
                    </div>
                </div>

                <div v-if="recentAnalyses.length === 0" class="text-gray-500 text-sm text-center py-4">
                    No analyses run yet.
                </div>
            </div>
        </div>
    </div>
</template>
