<script setup>
defineProps({
    term: { type: Object, required: true },
    removable: { type: Boolean, default: false },
});

const emit = defineEmits(['remove']);
</script>

<template>
    <span class="inline-flex items-center gap-1 px-2 py-0.5 text-xs bg-indigo-900/40 text-indigo-300 border border-indigo-700/40 rounded-full">
        {{ term.name }}
        <button
            v-if="removable"
            @click.prevent="emit('remove', term)"
            class="ml-0.5 text-indigo-400 hover:text-indigo-200 leading-none"
            title="Remove"
        >
            ×
        </button>
    </span>
</template>
