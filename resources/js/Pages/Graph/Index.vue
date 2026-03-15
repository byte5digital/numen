<script setup>
import { ref, computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import MainLayout from '../../Layouts/MainLayout.vue';
import KnowledgeGraphViewer from '../../Components/Graph/KnowledgeGraphViewer.vue';
import GraphLegend from '../../Components/Graph/GraphLegend.vue';

const props = defineProps({
    spaces: { type: Array, default: () => [] },
    clusters: { type: Array, default: () => [] },
    selectedSpace: { type: [String, Number], default: null },
});

const selectedSpaceId = ref(props.selectedSpace ?? props.spaces[0]?.id ?? null);
const activeEdgeTypes = ref([]);
const minWeight = ref(0);
const graphStats = ref({ nodeCount: 0, edgeCount: 0 });

const edgeTypeOptions = [
    { value: 'SIMILAR_TO', label: 'Similar To', color: 'text-blue-400' },
    { value: 'SHARES_TOPIC', label: 'Shares Topic', color: 'text-green-400' },
    { value: 'CITES', label: 'Cites', color: 'text-orange-400' },
    { value: 'CO_MENTIONS', label: 'Co-Mentions', color: 'text-purple-400' },
];

const filters = computed(() => ({
    edgeTypes: activeEdgeTypes.value,
    minWeight: minWeight.value,
}));

function toggleEdgeType(type) {
    const idx = activeEdgeTypes.value.indexOf(type);
    if (idx === -1) activeEdgeTypes.value.push(type);
    else activeEdgeTypes.value.splice(idx, 1);
}

function onNodeClick(node) {
    if (node.id) {
        window.location.href = `/admin/content/${node.id}`;
    }
}

function onLoaded(stats) {
    graphStats.value = stats;
}
</script>

<template>
    <MainLayout>
        <div class="flex flex-col h-full">
            <!-- Header -->
            <div class="mb-6 flex items-center justify-between flex-wrap gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-white">Knowledge Graph</h1>
                    <p class="text-gray-500 mt-1">
                        AI-powered content relationships
                        <span v-if="graphStats.nodeCount" class="text-indigo-400 ml-2">
                            {{ graphStats.nodeCount }} nodes &middot; {{ graphStats.edgeCount }} edges
                        </span>
                    </p>
                </div>

                <!-- Space selector -->
                <div v-if="spaces.length">
                    <select
                        v-model="selectedSpaceId"
                        class="bg-gray-800 border border-gray-700 text-gray-200 text-sm rounded-lg px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500"
                    >
                        <option v-for="space in spaces" :key="space.id" :value="space.id">
                            {{ space.name }}
                        </option>
                    </select>
                </div>
            </div>

            <div class="flex gap-4 flex-1 min-h-0" style="height: calc(100vh - 220px)">
                <!-- Graph -->
                <div class="flex-1 min-w-0">
                    <KnowledgeGraphViewer
                        v-if="selectedSpaceId"
                        :space-id="selectedSpaceId"
                        :filters="filters"
                        class="h-full"
                        @node-click="onNodeClick"
                        @loaded="onLoaded"
                    />
                    <div v-else class="flex items-center justify-center h-full">
                        <p class="text-gray-500">Select a space to view its knowledge graph.</p>
                    </div>
                </div>

                <!-- Sidebar -->
                <div class="w-72 flex-shrink-0 flex flex-col gap-4 overflow-y-auto">
                    <!-- Filters -->
                    <div class="bg-gray-900 border border-gray-800 rounded-xl p-4">
                        <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3">Filters</h3>

                        <!-- Edge type checkboxes -->
                        <div class="space-y-2 mb-4">
                            <p class="text-xs text-gray-500 mb-1">Edge Types</p>
                            <label
                                v-for="et in edgeTypeOptions"
                                :key="et.value"
                                class="flex items-center gap-2 cursor-pointer group"
                            >
                                <input
                                    type="checkbox"
                                    :checked="activeEdgeTypes.includes(et.value)"
                                    @change="toggleEdgeType(et.value)"
                                    class="w-3.5 h-3.5 rounded border-gray-600 bg-gray-800 text-indigo-500 focus:ring-indigo-500"
                                />
                                <span class="text-sm group-hover:text-white transition" :class="et.color">{{ et.label }}</span>
                            </label>
                        </div>

                        <!-- Min weight slider -->
                        <div>
                            <div class="flex justify-between mb-1">
                                <p class="text-xs text-gray-500">Min. Weight</p>
                                <span class="text-xs text-indigo-400">{{ minWeight.toFixed(1) }}</span>
                            </div>
                            <input
                                v-model.number="minWeight"
                                type="range" min="0" max="1" step="0.05"
                                class="w-full h-1.5 bg-gray-700 rounded-full appearance-none cursor-pointer accent-indigo-500"
                            />
                        </div>
                    </div>

                    <!-- Legend -->
                    <GraphLegend />

                    <!-- Clusters -->
                    <div v-if="clusters.length" class="bg-gray-900 border border-gray-800 rounded-xl p-4">
                        <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3">Clusters</h3>
                        <div class="space-y-2">
                            <div
                                v-for="cluster in clusters"
                                :key="cluster.id"
                                class="flex items-center justify-between py-1 border-b border-gray-800 last:border-0"
                            >
                                <span class="text-sm text-gray-300">{{ cluster.label ?? `Cluster ${cluster.id}` }}</span>
                                <span class="text-xs text-gray-500">{{ cluster.node_count }} nodes</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </MainLayout>
</template>
