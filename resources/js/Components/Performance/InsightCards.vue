<script setup>
import { ref, onMounted } from 'vue';

const props = defineProps({
    spaceId: { type: String, required: true },
});

const insights = ref([]);
const loading = ref(false);

const categoryStyles = {
    content: { bg: 'bg-indigo-500/10', border: 'border-indigo-500/20', text: 'text-indigo-300', icon: '📝' },
    timing: { bg: 'bg-amber-500/10', border: 'border-amber-500/20', text: 'text-amber-300', icon: '⏰' },
    format: { bg: 'bg-emerald-500/10', border: 'border-emerald-500/20', text: 'text-emerald-300', icon: '📐' },
    audience: { bg: 'bg-pink-500/10', border: 'border-pink-500/20', text: 'text-pink-300', icon: '👥' },
    default: { bg: 'bg-gray-500/10', border: 'border-gray-500/20', text: 'text-gray-300', icon: '💡' },
};

function getStyle(category) {
    return categoryStyles[category] ?? categoryStyles.default;
}

async function fetchInsights() {
    loading.value = true;
    try {
        const res = await fetch(`/api/v1/spaces/${props.spaceId}/performance/insights`, { credentials: 'include' });
        if (res.ok) {
            const json = await res.json();
            insights.value = json.data ?? json ?? [];
        }
    } catch (e) {
        console.error('Failed to fetch insights', e);
    } finally {
        loading.value = false;
    }
}

onMounted(fetchInsights);
</script>

<template>
    <div class="bg-gray-900 rounded-xl border border-gray-800 p-6">
        <h3 class="text-sm font-semibold text-white mb-4">Performance Insights</h3>

        <div v-if="loading" class="py-8 text-center text-gray-500 text-sm">Loading insights…</div>

        <div v-else-if="!insights.length" class="py-8 text-center text-gray-600 text-sm">
            No insights available yet. Publish more content to generate insights.
        </div>

        <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-3">
            <div v-for="insight in insights" :key="insight.id ?? insight.key"
                 class="p-4 rounded-lg border"
                 :class="[getStyle(insight.category).bg, getStyle(insight.category).border]">
                <div class="flex items-start gap-3">
                    <span class="text-xl shrink-0">{{ getStyle(insight.category).icon }}</span>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-medium" :class="getStyle(insight.category).text">
                            {{ insight.title ?? insight.insight ?? insight.summary }}
                        </p>
                        <p v-if="insight.description ?? insight.detail" class="text-xs text-gray-500 mt-1">
                            {{ insight.description ?? insight.detail }}
                        </p>
                        <div class="flex items-center gap-3 mt-2">
                            <span v-if="insight.confidence != null" class="text-xs text-gray-500">
                                Confidence: {{ Math.round((insight.confidence ?? 0) * 100) }}%
                            </span>
                            <span v-if="insight.impact" class="text-xs text-gray-500">
                                Impact: {{ insight.impact }}
                            </span>
                            <span v-if="insight.category" class="px-1.5 py-0.5 text-xs rounded capitalize"
                                  :class="[getStyle(insight.category).bg, getStyle(insight.category).text]">
                                {{ insight.category }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
