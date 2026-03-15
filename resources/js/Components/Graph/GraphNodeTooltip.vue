<script setup>
defineProps({
    node: {
        type: Object,
        default: null,
    },
    x: {
        type: Number,
        default: 0,
    },
    y: {
        type: Number,
        default: 0,
    },
    visible: {
        type: Boolean,
        default: false,
    },
});
</script>

<template>
    <Transition name="tooltip-fade">
        <div
            v-if="visible && node"
            class="absolute z-50 pointer-events-none"
            :style="{ left: `${x + 12}px`, top: `${y - 8}px` }"
        >
            <div class="bg-gray-900 border border-gray-700 rounded-xl shadow-2xl p-4 w-64">
                <!-- Title -->
                <p class="text-sm font-semibold text-white leading-snug mb-1">{{ node.title }}</p>

                <!-- Content type -->
                <span class="inline-block text-xs px-2 py-0.5 rounded-full bg-indigo-500/20 text-indigo-300 mb-2">
                    {{ node.content_type || 'content' }}
                </span>

                <!-- Entity labels -->
                <div v-if="node.entity_labels && node.entity_labels.length" class="flex flex-wrap gap-1 mb-2">
                    <span
                        v-for="label in node.entity_labels.slice(0, 6)"
                        :key="label"
                        class="text-xs px-1.5 py-0.5 rounded bg-gray-800 text-gray-400 border border-gray-700"
                    >
                        {{ label }}
                    </span>
                </div>

                <!-- Edge count + cluster -->
                <div class="flex items-center justify-between text-xs text-gray-500">
                    <span>{{ node.edge_count || 0 }} connections</span>
                    <span v-if="node.cluster_label" class="text-purple-400">{{ node.cluster_label }}</span>
                </div>
            </div>
        </div>
    </Transition>
</template>

<style scoped>
.tooltip-fade-enter-active,
.tooltip-fade-leave-active {
    transition: opacity 0.15s ease;
}
.tooltip-fade-enter-from,
.tooltip-fade-leave-to {
    opacity: 0;
}
</style>
