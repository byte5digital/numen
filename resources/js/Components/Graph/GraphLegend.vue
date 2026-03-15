<script setup>
const edgeTypes = [
    { type: 'SIMILAR_TO', label: 'Similar To', color: '#3b82f6' },
    { type: 'SHARES_TOPIC', label: 'Shares Topic', color: '#22c55e' },
    { type: 'CITES', label: 'Cites', color: '#f97316' },
    { type: 'CO_MENTIONS', label: 'Co-Mentions', color: '#a855f7' },
];
</script>

<template>
    <div class="bg-gray-900 border border-gray-800 rounded-xl p-4">
        <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3">Edge Types</h3>
        <div class="space-y-2">
            <div v-for="edge in edgeTypes" :key="edge.type" class="flex items-center gap-2">
                <div class="w-8 h-1 rounded-full flex-shrink-0" :style="{ backgroundColor: edge.color }"></div>
                <span class="text-sm text-gray-300">{{ edge.label }}</span>
            </div>
        </div>
    </div>
</template>
