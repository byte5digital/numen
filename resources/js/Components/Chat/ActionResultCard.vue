<script setup lang="ts">
defineProps<{
    action: {
        icon?: string
        description: string
        link?: string
        linkLabel?: string
    }
}>()
</script>

<template>
    <div class="mt-2 rounded-lg border border-green-700/40 bg-green-900/20 px-4 py-3 flex items-center gap-3">
        <span class="text-xl">{{ action.icon ?? '✅' }}</span>
        <span class="flex-1 text-sm text-green-300">{{ action.description }}</span>
        <a
            v-if="action.link"
            :href="action.link"
            class="text-xs text-indigo-400 hover:text-indigo-300 underline whitespace-nowrap"
        >
            {{ action.linkLabel ?? 'View →' }}
        </a>
    </div>
</template>
