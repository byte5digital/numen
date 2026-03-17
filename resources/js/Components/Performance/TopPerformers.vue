<script setup>
import { ref, onMounted } from 'vue';

const props = defineProps({
    spaceId: { type: String, required: true },
});

const performers = ref([]);
const loading = ref(false);

async function fetchTopPerformers() {
    loading.value = true;
    try {
        const res = await fetch(`/api/v1/spaces/${props.spaceId}/performance/snapshots?sort=-composite_score&limit=10`, {
            credentials: 'include',
        });
        if (res.ok) {
            const json = await res.json();
            performers.value = json.data ?? json ?? [];
        }
    } catch (e) {
        console.error('Failed to fetch top performers', e);
    } finally {
        loading.value = false;
    }
}

function scoreColor(score) {
    if (score >= 80) return 'text-emerald-400';
    if (score >= 60) return 'text-amber-400';
    return 'text-gray-400';
}

onMounted(fetchTopPerformers);
</script>

<template>
    <div class="bg-gray-900 rounded-xl border border-gray-800 p-6">
        <h3 class="text-sm font-semibold text-white mb-4">Top Performing Content</h3>

        <div v-if="loading" class="py-8 text-center text-gray-500 text-sm">Loading…</div>

        <div v-else-if="!performers.length" class="py-8 text-center text-gray-600 text-sm">
            No performance data yet.
        </div>

        <div v-else class="space-y-2">
            <div v-for="(item, index) in performers" :key="item.id"
                 class="flex items-center gap-3 p-3 bg-gray-950 rounded-lg border border-gray-800">
                <!-- Rank -->
                <span class="w-6 text-center text-sm font-bold"
                      :class="index < 3 ? 'text-amber-400' : 'text-gray-600'">
                    {{ index + 1 }}
                </span>

                <!-- Info -->
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium text-white truncate">
                        {{ item.content_title ?? item.title ?? `Content #${item.content_id}` }}
                    </p>
                    <div class="flex items-center gap-3 mt-0.5 text-xs text-gray-500">
                        <span v-if="item.metrics?.views != null">{{ item.metrics.views.toLocaleString() }} views</span>
                        <span v-if="item.metrics?.engagement != null">{{ item.metrics.engagement }} engagement</span>
                        <span v-if="item.metrics?.conversions != null">{{ item.metrics.conversions }} conv.</span>
                    </div>
                </div>

                <!-- Score -->
                <div class="text-right shrink-0">
                    <span class="text-lg font-bold" :class="scoreColor(item.metrics?.composite_score ?? item.composite_score ?? 0)">
                        {{ Math.round(item.metrics?.composite_score ?? item.composite_score ?? 0) }}
                    </span>
                    <p class="text-xs text-gray-600">score</p>
                </div>
            </div>
        </div>
    </div>
</template>
