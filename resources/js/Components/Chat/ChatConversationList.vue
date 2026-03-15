<script setup lang="ts">
import { computed } from 'vue'

export interface Conversation {
    id: string
    title: string | null
    last_active_at: string | null
    message_count?: number
}

const props = defineProps<{
    conversations: Conversation[]
    activeId: string | null
    loading?: boolean
}>()

const emit = defineEmits<{
    select: [id: string]
    newChat: []
}>()

function formatDate(dateStr: string | null) {
    if (!dateStr) return ''
    const d = new Date(dateStr)
    const now = new Date()
    const diffMs = now.getTime() - d.getTime()
    const diffDays = Math.floor(diffMs / 86400000)
    if (diffDays === 0) return 'Today'
    if (diffDays === 1) return 'Yesterday'
    if (diffDays < 7) return `${diffDays}d ago`
    return d.toLocaleDateString(undefined, { month: 'short', day: 'numeric' })
}

const sortedConversations = computed(() =>
    [...props.conversations].sort((a, b) => {
        const at = a.last_active_at ? new Date(a.last_active_at).getTime() : 0
        const bt = b.last_active_at ? new Date(b.last_active_at).getTime() : 0
        return bt - at
    })
)
</script>

<template>
    <div class="flex flex-col border-b border-gray-800">
        <!-- Header -->
        <div class="flex items-center justify-between px-4 py-3">
            <span class="text-xs font-semibold uppercase tracking-wider text-gray-500">Conversations</span>
            <button
                class="flex items-center gap-1 rounded-md px-2 py-1 text-xs text-indigo-400 hover:bg-indigo-500/10 transition"
                @click="emit('newChat')"
            >
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                New Chat
            </button>
        </div>

        <!-- Loading -->
        <div v-if="loading" class="px-4 py-2 text-xs text-gray-600">Loading…</div>

        <!-- Empty -->
        <div v-else-if="sortedConversations.length === 0" class="px-4 py-2 text-xs text-gray-600">
            No conversations yet
        </div>

        <!-- List -->
        <div v-else class="max-h-48 overflow-y-auto">
            <button
                v-for="conv in sortedConversations"
                :key="conv.id"
                class="w-full flex items-start gap-2 px-4 py-2.5 text-left transition hover:bg-gray-800/60"
                :class="conv.id === activeId ? 'bg-indigo-500/10 border-l-2 border-indigo-500' : 'border-l-2 border-transparent'"
                @click="emit('select', conv.id)"
            >
                <span class="mt-0.5 text-sm flex-shrink-0">💬</span>
                <div class="min-w-0 flex-1">
                    <p class="truncate text-xs font-medium" :class="conv.id === activeId ? 'text-indigo-300' : 'text-gray-300'">
                        {{ conv.title ?? 'New conversation' }}
                    </p>
                    <p class="text-xs text-gray-600 mt-0.5">{{ formatDate(conv.last_active_at) }}</p>
                </div>
            </button>
        </div>
    </div>
</template>
