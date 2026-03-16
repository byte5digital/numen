<script setup lang="ts">
import { ref, onMounted, watch } from 'vue'
import ChatConversationList, { type Conversation } from './ChatConversationList.vue'
import ChatMessageList from './ChatMessageList.vue'
import ChatInputBar from './ChatInputBar.vue'
import type { Message } from './ChatMessage.vue'

const STORAGE_KEY_OPEN = 'numen_chat_open'
const STORAGE_KEY_CONV = 'numen_chat_conversation_id'

const isOpen = ref(localStorage.getItem(STORAGE_KEY_OPEN) === 'true')
const activeConversationId = ref<string | null>(localStorage.getItem(STORAGE_KEY_CONV))

watch(isOpen, (v) => localStorage.setItem(STORAGE_KEY_OPEN, String(v)))
watch(activeConversationId, (v) => {
    if (v) localStorage.setItem(STORAGE_KEY_CONV, v)
    else localStorage.removeItem(STORAGE_KEY_CONV)
})

const conversations = ref<Conversation[]>([])
const messages = ref<Message[]>([])
const conversationsLoading = ref(false)
const messagesLoading = ref(false)
const streaming = ref(false)
const sessionCost = ref(0)

async function fetchConversations() {
    conversationsLoading.value = true
    try {
        const res = await fetch('/api/v1/chat/conversations', {
            headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
        })
        if (res.ok) {
            const data = await res.json()
            conversations.value = data.data ?? data ?? []
        }
    } catch (e) {
        console.error('[Chat] Failed to fetch conversations', e)
    } finally {
        conversationsLoading.value = false
    }
}

async function fetchMessages(conversationId: string) {
    messagesLoading.value = true
    messages.value = []
    try {
        const res = await fetch('/api/v1/chat/conversations/' + conversationId + '/messages', {
            headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
        })
        if (res.ok) {
            const data = await res.json()
            messages.value = (data.data ?? data ?? []).map((m: Record<string, unknown>) => ({
                id: m.id,
                role: m.role,
                content: m.content,
                chunk_type: m.chunk_type ?? 'text',
                action: m.action,
                action_result: m.action_result,
            })) as Message[]
        }
    } catch (e) {
        console.error('[Chat] Failed to fetch messages', e)
    } finally {
        messagesLoading.value = false
    }
}

async function selectConversation(id: string) {
    activeConversationId.value = id
    await fetchMessages(id)
}

async function newChat() {
    try {
        const res = await fetch('/api/v1/chat/conversations', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                Accept: 'application/json',
            },
            body: JSON.stringify({ title: null }),
        })
        if (res.ok) {
            const data = await res.json()
            const conv: Conversation = data.data ?? data
            conversations.value.unshift(conv)
            activeConversationId.value = conv.id
            messages.value = []
        }
    } catch (e) {
        console.error('[Chat] Failed to create conversation', e)
    }
}

async function sendMessage(text: string) {
    if (!activeConversationId.value) {
        await newChat()
        if (!activeConversationId.value) return
    }

    const userMessage: Message = { role: 'user', content: text, chunk_type: 'text' }
    messages.value.push(userMessage)

    const assistantMessage: Message = {
        role: 'assistant',
        content: '',
        chunk_type: 'text',
        streaming: true,
    }
    messages.value.push(assistantMessage)
    const assistantIndex = messages.value.length - 1

    streaming.value = true

    try {
        const res = await fetch('/api/v1/chat/conversations/' + activeConversationId.value + '/messages', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                Accept: 'text/event-stream',
            },
            body: JSON.stringify({ message: text }),
        })

        if (!res.ok || !res.body) {
            messages.value[assistantIndex].content = 'Error: Could not reach the AI.'
            messages.value[assistantIndex].streaming = false
            streaming.value = false
            return
        }

        const reader = res.body.getReader()
        const decoder = new TextDecoder()
        let buffer = ''

        while (true) {
            const { done, value } = await reader.read()
            if (done) break

            buffer += decoder.decode(value, { stream: true })
            const lines = buffer.split('\n')
            buffer = lines.pop() ?? ''

            for (const line of lines) {
                if (!line.startsWith('data:')) continue
                const raw = line.slice(5).trim()
                if (raw === '[DONE]') break

                try {
                    const chunk = JSON.parse(raw)
                    if (chunk.type === 'text' || !chunk.type) {
                        messages.value[assistantIndex].content += chunk.content ?? chunk.delta ?? chunk.text ?? ''
                    } else if (chunk.type === 'confirm') {
                        messages.value[assistantIndex].chunk_type = 'confirm'
                        messages.value[assistantIndex].action = chunk.action
                        messages.value[assistantIndex].content = chunk.content ?? ''
                    } else if (chunk.type === 'action') {
                        messages.value[assistantIndex].chunk_type = 'action'
                        messages.value[assistantIndex].action_result = chunk.action_result
                        messages.value[assistantIndex].content = chunk.content ?? ''
                    } else if (chunk.type === 'cost') {
                        sessionCost.value = chunk.total_cost ?? sessionCost.value
                    }
                } catch {
                    messages.value[assistantIndex].content += raw
                }
            }
        }

        await fetchConversations()
    } catch (e) {
        console.error('[Chat] Streaming error', e)
        messages.value[assistantIndex].content += '\n[Connection error]'
    } finally {
        messages.value[assistantIndex].streaming = false
        streaming.value = false
    }
}

function toggleSidebar() {
    isOpen.value = !isOpen.value
    if (isOpen.value && conversations.value.length === 0) {
        fetchConversations()
    }
}

async function handleConfirmed() {
    if (activeConversationId.value) await fetchMessages(activeConversationId.value)
}

async function handleCancelled() {
    if (activeConversationId.value) await fetchMessages(activeConversationId.value)
}

onMounted(async () => {
    await fetchConversations()
    if (activeConversationId.value) {
        await fetchMessages(activeConversationId.value)
    }
})
</script>

<template>
    <button
        v-show="!isOpen"
        class="fixed bottom-6 right-6 z-[60] flex items-center justify-center w-12 h-12 rounded-full bg-indigo-600 hover:bg-indigo-500 shadow-lg text-white text-xl transition-transform hover:scale-105"
        title="Toggle AI Chat"
        @click="toggleSidebar"
    >
        <span>&#x1F4AC;</span>
    </button>

    <div
        v-show="isOpen"
        class="fixed inset-y-0 right-0 w-96 bg-gray-900 border-l border-gray-800 z-50 flex flex-col shadow-2xl"
    >
        <div class="flex items-center justify-between px-4 py-4 border-b border-gray-800 flex-shrink-0">
            <div class="flex items-center gap-2">
                <span class="text-lg">&#x1F916;</span>
                <div>
                    <h2 class="text-sm font-semibold text-white">Numen AI</h2>
                    <p class="text-xs text-gray-500">Content Assistant</p>
                </div>
            </div>
            <button class="text-gray-500 hover:text-gray-300 transition" @click="isOpen = false">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        <ChatConversationList
            :conversations="conversations"
            :active-id="activeConversationId"
            :loading="conversationsLoading"
            class="flex-shrink-0"
            @select="selectConversation"
            @new-chat="newChat"
        />

        <ChatMessageList
            :conversation-id="activeConversationId"
            :messages="messages"
            :loading="messagesLoading"
            class="flex-1 min-h-0"
            @confirmed="handleConfirmed"
            @cancelled="handleCancelled"
        />

        <ChatInputBar
            :disabled="streaming || !activeConversationId"
            :session-cost="sessionCost"
            class="flex-shrink-0"
            @send="sendMessage"
        />
    </div>

    <div
        v-show="isOpen"
        class="fixed inset-0 z-40 bg-black/20 pointer-events-none"
    />
</template>
