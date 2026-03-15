<script setup lang="ts">
import { computed } from 'vue'

const props = defineProps<{
    currentPage?: string
    spaceId?: string
}>()

const emit = defineEmits<{
    select: [chip: string]
}>()

const chips = computed((): string[] => {
    const page = (props.currentPage ?? '').toLowerCase()

    if (page.includes('dashboard')) {
        return ['Show recent drafts', 'Create a blog post', "What's pending review?"]
    }

    if (page.includes('content')) {
        return ['Filter by published', 'Create new content', 'Show content stats']
    }

    if (page.includes('pipeline')) {
        return ['Run pipeline', 'Show failed runs', "What's queued?"]
    }

    return ['Create content', 'Show drafts', 'Run pipeline', 'Help']
})
</script>

<template>
    <div v-if="chips.length" class="flex flex-wrap gap-1.5 px-1 pb-1">
        <button
            v-for="chip in chips"
            :key="chip"
            type="button"
            class="inline-flex items-center rounded-full border border-gray-700 bg-gray-800 px-2.5 py-1 text-xs text-gray-300 hover:border-indigo-500 hover:bg-gray-700 hover:text-white transition-colors"
            @click="emit('select', chip)"
        >
            {{ chip }}
        </button>
    </div>
</template>
