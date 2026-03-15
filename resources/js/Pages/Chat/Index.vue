<script setup lang="ts">
import { ref, onMounted, watch } from 'vue'
import MainLayout from '../../Layouts/MainLayout.vue'
import ChatConversationList, { type Conversation } from '../../Components/Chat/ChatConversationList.vue'
import ChatMessageList from '../../Components/Chat/ChatMessageList.vue'
import ChatInputBar from '../../Components/Chat/ChatInputBar.vue'
import type { Message } from '../../Components/Chat/ChatMessage.vue'

const STORAGE_KEY_CONV = 'numen_chat_conversation_id'

const activeConversationId = ref<string | null>(localStorage.getItem(STORAGE_KEY_CONV))

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

async function createConversation(): Promise<string | null> {
    try {
        const res = await fetch('/api/v1/chat/conversations', {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                Accept: 'application/json',
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ title: 'New conversation' }),
        })
        if (res.ok) {
            const data = await res.json()
            const conv = data.data ?? data
            await fetchConversations()
            return conv.id ?? conv.ulid ?? null
        }
    } catch (e) {
        console.error('[Chat] Failed to create conversation', e)
    }
    return null
}

async function sendMessage(text: string) {
    if (!text.trim()) return

    let convId = activeConversationId.value
    if (!convId) {
        convId = await createConversation()
        if (!convId) return
        activeConversationId.value = convId
    }

    const userMsg: Message = { id: crypto.randomUUID(), role: 'user', content: text, chunk_type: 'text' }
    messages.value.push(userMsg)

    const assistantMsg: Message = { id: crypto.randomUUID(), role: 'assistant', content: '', chunk_type: 'text' }
    messages.value.push(assistantMsg)
    streaming.value = true

    try {
        const res = await fetch('/api/v1/chat/conversations/' + convId + '/messages', {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                Accept: 'text/event-stream',
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ message: text }),
        })

        if (!res.ok || !res.body) {
            assistantMsg.content = 'Error: could not reach the assistant.'
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
                if (!line.startsWith('data: ')) continue
                const raw = line.slice(6).trim()
                if (!raw || raw === '[DONE]') continue
                try {
                    const evt = JSON.parse(raw)
                    if (evt.type === 'text' && evt.delta) {
                        assistantMsg.content += evt.delta
                    } else if (evt.type === 'cost') {
                        sessionCost.value += evt.cost ?? 0
                    } else if (evt.type === 'action') {
                        assistantMsg.chunk_type = 'action'
                        assistantMsg.action = evt.action
                    } else if (evt.type === 'action_result') {
                        assistantMsg.action_result = evt.result
                    }
                } catch {
                    // ignore parse errors
                }
            }
        }
    } catch (e) {
        assistantMsg.content = 'Stream error. Please try again.'
        console.error('[Chat] Stream error', e)
    } finally {
        streaming.value = false
        await fetchConversations()
    }
}

function selectConversation(id: string) {
    activeConversationId.value = id
    fetchMessages(id)
}

function startNewConversation() {
    activeConversationId.value = null
    messages.value = []
}

onMounted(() => {
    fetchConversations()
    if (activeConversationId.value) {
        fetchMessages(activeConversationId.value)
    }
})
</script>

<template>
    <MainLayout>
        <div class="flex h-[calc(100vh-4rem)] bg-gray-950">
            <!-- Conversation sidebar -->
            <div class="w-72 flex-shrink-0 border-r border-gray-800 flex flex-col">
                <div class="p-4 border-b border-gray-800 flex items-center justify-between">
                    <h2 class="text-sm font-semibold text-white">Conversations</h2>
                    <button
                        class="text-xs text-indigo-400 hover:text-indigo-300 transition-colors"
                        @click="startNewConversation"
                    >
                        + New
                    </button>
                </div>
                <ChatConversationList
                    :conversations="conversations"
                    :loading="conversationsLoading"
                    :active-id="activeConversationId"
                    class="flex-1 overflow-y-auto"
                    @select="selectConversation"
                />
            </div>

            <!-- Main chat area -->
            <div class="flex-1 flex flex-col min-w-0">
                <!-- Header -->
                <div class="px-6 py-4 border-b border-gray-800 flex items-center justify-between">
                    <div>
                        <h1 class="text-lg font-semibold text-white">AI Assistant</h1>
                        <p class="text-xs text-gray-500">Ask me to create, edit, or manage your content</p>
                    </div>
                    <div v-if="sessionCost > 0" class="text-xs text-gray-500">
                        Session: ${{ sessionCost.toFixed(4) }}
                    </div>
                </div>

                <!-- Messages -->
                <ChatMessageList
                    :messages="messages"
                    :loading="messagesLoading"
                    :streaming="streaming"
                    class="flex-1 overflow-y-auto p-6"
                />

                <!-- Input -->
                <div class="border-t border-gray-800 p-4">
                    <ChatInputBar
                        :disabled="streaming"
                        @send="sendMessage"
                    />
                </div>
            </div>
        </div>
    </MainLayout>
</template>
