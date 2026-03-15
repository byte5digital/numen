<script setup>
import { ref, onMounted, watch } from 'vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({
    spaceId: { type: String, required: true },
});

const gaps    = ref([]);
const loading = ref(true);
const error   = ref(null);

async function fetchGaps() {
    loading.value = true;
    error.value = null;
    try {
        const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
        const token = match ? decodeURIComponent(match[1]) : '';
        const res = await fetch(`/api/v1/graph/gaps?space_id=${props.spaceId}`, {
            credentials: 'include',
            headers: {
                'Accept': 'application/json',
                'X-XSRF-TOKEN': token,
            },
        });
        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        const data = await res.json();
        gaps.value = data.data ?? data ?? [];
    } catch (e) {
        error.value = e.message;
    } finally {
        loading.value = false;
    }
}

function createBrief(gap) {
    const topic = gap.suggested_topic ?? gap.label ?? '';
    router.visit(`/admin/briefs/create?topic=${encodeURIComponent(topic)}`);
}

onMounted(fetchGaps);
watch(() => props.spaceId, fetchGaps);
</script>

<template>
    <div class="bg-gray-900 rounded-xl border border-gray-800 p-5">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-sm font-semibold text-white flex items-center gap-2">
                <span>🔍</span> Content Gap Analysis
            </h2>
            <button
                @click="fetchGaps"
                class="text-xs text-gray-500 hover:text-gray-300 transition"
                title="Refresh"
            >↻</button>
        </div>

        <!-- Loading skeleton -->
        <div v-if="loading" class="space-y-3">
            <div v-for="i in 3" :key="i" class="animate-pulse space-y-2">
                <div class="h-3 bg-gray-800 rounded w-2/3"></div>
                <div class="h-2 bg-gray-800 rounded w-1/2"></div>
            </div>
        </div>

        <!-- Error -->
        <div v-else-if="error" class="text-xs text-red-400">
            Failed to load content gaps.
        </div>

        <!-- Empty -->
        <div v-else-if="!gaps.length" class="text-xs text-gray-600">
            No content gaps detected. Great coverage!
        </div>

        <!-- Gap clusters -->
        <div v-else class="space-y-3">
            <div
                v-for="gap in gaps"
                :key="gap.id ?? gap.suggested_topic"
                class="p-3 rounded-lg border border-gray-800 bg-gray-950"
            >
                <div class="flex items-start justify-between gap-3">
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-white truncate">
                            {{ gap.suggested_topic ?? gap.label ?? 'Unknown Topic' }}
                        </p>
                        <!-- Entity labels -->
                        <div v-if="gap.entities?.length" class="flex flex-wrap gap-1 mt-1.5">
                            <span
                                v-for="entity in gap.entities"
                                :key="entity"
                                class="text-xs px-1.5 py-0.5 rounded-full bg-gray-800 text-gray-400"
                            >
                                {{ entity }}
                            </span>
                        </div>
                        <p v-if="gap.description" class="text-xs text-gray-500 mt-1">
                            {{ gap.description }}
                        </p>
                    </div>

                    <button
                        @click="createBrief(gap)"
                        class="shrink-0 px-3 py-1.5 text-xs bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg transition"
                    >
                        ✍️ Create
                    </button>
                </div>

                <!-- Opportunity score -->
                <div v-if="gap.score != null" class="mt-2 flex items-center gap-2">
                    <span class="text-xs text-gray-600">Opportunity</span>
                    <div class="flex-1 h-1.5 bg-gray-800 rounded-full overflow-hidden">
                        <div
                            class="h-full bg-amber-500 rounded-full"
                            :style="{ width: Math.round((gap.score ?? 0) * 100) + '%' }"
                        ></div>
                    </div>
                    <span class="text-xs text-gray-500">{{ Math.round((gap.score ?? 0) * 100) }}%</span>
                </div>
            </div>
        </div>
    </div>
</template>
