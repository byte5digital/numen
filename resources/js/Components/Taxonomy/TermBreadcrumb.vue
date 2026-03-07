<script setup>
defineProps({
    // Array of ancestor term objects [{ id, name, slug }, ...]
    ancestors: { type: Array, default: () => [] },
    current: { type: Object, required: true },
});
</script>

<template>
    <nav class="flex items-center gap-1 text-xs text-gray-500">
        <template v-for="(ancestor, i) in ancestors" :key="ancestor.id">
            <span class="text-gray-400">{{ ancestor.name }}</span>
            <span class="text-gray-700">/</span>
        </template>
        <span class="text-gray-200 font-medium">{{ current.name }}</span>
    </nav>
</template>
