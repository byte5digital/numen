<script setup>
import { ref, onMounted, computed, watch } from 'vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({
    contentId: { type: String, required: true },
    spaceId:   { type: String, required: true },
    limit:     { type: Number, default: 5 },
});

const related  = ref([]);
const loading  = ref(true);
const error    = ref(null);
const activeTab = ref('similar');

const edgeTypes = ['similar', 'same_topic', 'cited', 'co_mentions'];
const edgeLabels = {
    similar:      'Similar',
    same_topic:   'Same Topic',
    cited:        'Cited',
    co_mentions:  'Co-mentions',
};

const edgeColors = {
    similar:     'bg-indigo-900/40 text-indigo-300',
    same_topic:  'bg-emerald-900/40 text-emerald-300',
    cited:       'bg-amber-900/40 text-amber-300',
    co_mentions: 'bg-purple-900/40 text-purple-300',
};

async function fetchRelated() {
    loading.value = true;
    error.value = null;
    try {
        const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
        const token = match ? decodeURIComponent(match[1]) : '';
        const params = new URLSearchParams({ limit: String(props.limit) });
        if (props.spaceId) params.set('space_id', props.spaceId);
        const res = await fetch(`/api/v1/graph/related/${props.contentId}?${params}`, {
            credentials: 'include',
            headers: {
                'Accept': 'application/json',
                'X-XSRF-TOKEN': token,
            },
        });
        // Gracefully handle 404 (graph not yet indexed) and other errors
        if (res.status === 404 || res.status === 422) {
            related.value = [];
            return;
        }
        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        const data = await res.json();
        related.value = data.data ?? data ?? [];
    } catch (e) {
        // Show empty state instead of error — graph may not be set up yet
        related.value = [];
        console.warn('[RelatedContentWidget] Could not load related content:', e.message);
    } finally {
        loading.value = false;
    }
}

const grouped = computed(() => {
    const groups = {};
    for (const type of edgeTypes) {
        groups[type] = related.value.filter(item => {
            const t = (item.edge_type ?? '').replace(/-/g, '_').toLowerCase();
            return t === type;
        });
    }
    return groups;
});

const tabsWithContent = computed(() =>
    edgeTypes.filter(t => grouped.value[t]?.length > 0)
);

function weightBar(weight) {
    return Math.round((weight ?? 0) * 100);
}

function navigate(item) {
    router.visit(`/admin/content/${item.id}`);
}

onMounted(fetchRelated);
watch(() => props.contentId, fetchRelated);
</script>

<template>
    <div class="bg-gray-900 rounded-xl border border-gray-800 p-5">
        <h3 class="text-sm font-medium text-white mb-4 flex items-center gap-2">
            <span>🕸️</span> Related Content
        </h3>

        <!-- Loading skeleton -->
        <div v-if="loading" class="space-y-2">
            <div v-for="i in 4" :key="i" class="animate-pulse flex items-center gap-3">
                <div class="h-3 bg-gray-800 rounded w-3/4"></div>
                <div class="h-3 bg-gray-800 rounded w-1/4"></div>
            </div>
        </div>

        <!-- Error -->
        <div v-else-if="error" class="text-xs text-red-400">
            Failed to load related content.
        </div>

        <!-- Empty -->
        <div v-else-if="!related.length" class="text-xs text-gray-600">
            No related content found.
        </div>

        <!-- Content -->
        <div v-else>
            <!-- Tabs -->
            <div class="flex gap-1 mb-4 flex-wrap">
                <button
                    v-for="type in tabsWithContent"
                    :key="type"
                    @click="activeTab = type"
                    class="px-2.5 py-1 rounded-lg text-xs font-medium transition"
                    :class="activeTab === type
                        ? 'bg-gray-700 text-white'
                        : 'text-gray-500 hover:text-gray-300'"
                >
                    {{ edgeLabels[type] }}
                    <span class="ml-1 text-xs opacity-60">({{ grouped[type].length }})</span>
                </button>
            </div>

            <!-- Items -->
            <div class="space-y-2">
                <button
                    v-for="item in grouped[activeTab]"
                    :key="item.id"
                    @click="navigate(item)"
                    class="w-full text-left flex items-center gap-3 p-2.5 rounded-lg bg-gray-800/50 hover:bg-gray-800 transition group"
                >
                    <div class="flex-1 min-w-0">
                        <p class="text-xs font-medium text-gray-200 truncate group-hover:text-white transition">
                            {{ item.title ?? 'Untitled' }}
                        </p>
                        <div class="flex items-center gap-2 mt-1">
                            <span
                                class="text-xs px-1.5 py-0.5 rounded-full"
                                :class="edgeColors[activeTab]"
                            >
                                {{ edgeLabels[activeTab] }}
                            </span>
                        </div>
                    </div>
                    <!-- Weight bar -->
                    <div class="shrink-0 flex flex-col items-end gap-1">
                        <span class="text-xs text-gray-500">{{ weightBar(item.weight) }}%</span>
                        <div class="w-12 h-1.5 bg-gray-700 rounded-full overflow-hidden">
                            <div
                                class="h-full bg-indigo-500 rounded-full transition-all"
                                :style="{ width: weightBar(item.weight) + '%' }"
                            ></div>
                        </div>
                    </div>
                </button>
            </div>
        </div>
    </div>
</template>
