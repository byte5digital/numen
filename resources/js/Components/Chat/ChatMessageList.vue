<script setup lang="ts">
import { ref, watch, nextTick, onMounted } from 'vue'
import ChatMessage, { type Message } from './ChatMessage.vue'

const props = defineProps<{
    conversationId: string | null
    messages: Message[]
    loading?: boolean
}>()

const emit = defineEmits<{
    confirmed: []
    cancelled: []
}>()

const listRef = ref<HTMLDivElement>()

async function scrollToBottom() {
    await nextTick()
    if (listRef.value) {
        listRef.value.scrollTop = listRef.value.scrollHeight
    }
}

watch(() => props.messages.length, scrollToBottom)
watch(() => props.messages[props.messages.length - 1]?.content, scrollToBottom)

onMounted(scrollToBottom)
</script>

<template>
    <div
        ref="listRef"
        class="flex-1 overflow-y-auto px-4 py-4 space-y-1 scrollbar-thin scrollbar-thumb-gray-700 scrollbar-track-transparent"
    >
        <!-- Empty state -->
        <div
            v-if="!loading && messages.length === 0"
            class="flex flex-col items-center justify-center h-full text-center py-16"
        >
            <span class="text-4xl mb-3">💬</span>
            <p class="text-gray-400 text-sm">Start a conversation</p>
            <p class="text-gray-600 text-xs mt-1">Ask me to create, update, or manage content</p>
        </div>

        <!-- Loading state -->
        <div v-if="loading" class="flex items-center justify-center py-8">
            <div class="flex gap-1">
                <div class="w-2 h-2 rounded-full bg-indigo-400 animate-bounce" style="animation-delay: 0ms" />
                <div class="w-2 h-2 rounded-full bg-indigo-400 animate-bounce" style="animation-delay: 150ms" />
                <div class="w-2 h-2 rounded-full bg-indigo-400 animate-bounce" style="animation-delay: 300ms" />
            </div>
        </div>

        <!-- Messages -->
        <ChatMessage
            v-for="(msg, i) in messages"
            :key="msg.id ?? i"
            :message="msg"
            :conversation-id="conversationId ?? ''"
            @confirmed="emit('confirmed')"
            @cancelled="emit('cancelled')"
        />
    </div>
</template>
