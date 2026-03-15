<script setup lang="ts">
import { computed } from 'vue'
import { marked } from 'marked'
import ConfirmActionCard from './ConfirmActionCard.vue'
import ActionResultCard from './ActionResultCard.vue'

export interface Message {
    id?: string
    role: 'user' | 'assistant'
    content: string
    chunk_type?: 'text' | 'confirm' | 'action'
    action?: {
        description: string
        payload?: Record<string, unknown>
    }
    action_result?: {
        icon?: string
        description: string
        link?: string
        linkLabel?: string
    }
    streaming?: boolean
}

const props = defineProps<{
    message: Message
    conversationId: string
}>()

const emit = defineEmits<{
    confirmed: []
    cancelled: []
}>()

const isUser = computed(() => props.message.role === 'user')

const renderedMarkdown = computed(() => {
    if (!props.message.content) return ''
    try {
        return marked.parse(props.message.content, { async: false }) as string
    } catch {
        return props.message.content
    }
})
</script>

<template>
    <div class="flex w-full mb-4" :class="isUser ? 'justify-end' : 'justify-start'">
        <div class="max-w-[80%]">
            <!-- User message -->
            <div
                v-if="isUser"
                class="rounded-lg px-4 py-3 bg-indigo-600 text-white text-sm leading-relaxed"
            >
                {{ message.content }}
            </div>

            <!-- Assistant message -->
            <div v-else class="rounded-lg px-4 py-3 bg-gray-800 text-gray-100 text-sm leading-relaxed">
                <div
                    v-if="message.chunk_type !== 'confirm' && message.chunk_type !== 'action'"
                    class="prose prose-invert prose-sm max-w-none"
                    v-html="renderedMarkdown"
                />

                <!-- Confirm action card -->
                <ConfirmActionCard
                    v-if="message.chunk_type === 'confirm' && message.action"
                    :conversation-id="conversationId"
                    :action="message.action"
                    @confirmed="emit('confirmed')"
                    @cancelled="emit('cancelled')"
                />

                <!-- Action result card -->
                <ActionResultCard
                    v-if="message.chunk_type === 'action' && message.action_result"
                    :action="message.action_result"
                />

                <!-- Streaming cursor -->
                <span
                    v-if="message.streaming"
                    class="inline-block w-1.5 h-4 bg-indigo-400 animate-pulse ml-0.5 align-middle"
                />
            </div>

            <!-- Role label -->
            <p class="mt-1 text-xs text-gray-600" :class="isUser ? 'text-right' : 'text-left'">
                {{ isUser ? 'You' : 'Numen AI' }}
            </p>
        </div>
    </div>
</template>
