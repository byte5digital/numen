<script setup lang="ts">
import { ref, watch, nextTick } from 'vue'
import { usePage } from '@inertiajs/vue3'
import SuggestionChips from '@/Components/Chat/SuggestionChips.vue'

const props = defineProps<{
    disabled?: boolean
    sessionCost?: number
    spaceId?: string
}>()

const emit = defineEmits<{
    send: [message: string]
}>()

const page = usePage()
const text = ref('')
const textareaRef = ref<HTMLTextAreaElement>()

const currentPage = ref<string>(
    (page.props?.ziggy as { location?: string } | undefined)?.location ??
    (typeof window !== 'undefined' ? window.location.pathname : ''),
)

function autoResize() {
    nextTick(() => {
        const el = textareaRef.value
        if (!el) return
        el.style.height = 'auto'
        const lineHeight = 24
        const minHeight = lineHeight
        const maxHeight = lineHeight * 5
        const scrollHeight = el.scrollHeight
        el.style.height = Math.min(Math.max(scrollHeight, minHeight), maxHeight) + 'px'
    })
}

watch(text, autoResize)

function onKeydown(e: KeyboardEvent) {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault()
        send()
    }
}

function send() {
    const msg = text.value.trim()
    if (!msg || props.disabled) return
    emit('send', msg)
    text.value = ''
    nextTick(() => {
        const el = textareaRef.value
        if (el) el.style.height = '24px'
    })
}

function onChipSelect(chip: string) {
    text.value = chip
    nextTick(() => send())
}

const formatCost = (cost: number) => `$${cost.toFixed(4)}`
</script>

<template>
    <div class="border-t border-gray-800 px-4 py-3 bg-gray-900">
        <!-- Cost indicator -->
        <div v-if="sessionCost !== undefined && sessionCost > 0" class="mb-2 text-right">
            <span class="text-xs text-gray-600">Session cost: {{ formatCost(sessionCost) }}</span>
        </div>

        <!-- Suggestion chips -->
        <SuggestionChips
            :current-page="currentPage"
            :space-id="spaceId"
            class="mb-2"
            @select="onChipSelect"
        />

        <div class="flex items-end gap-2">
            <textarea
                ref="textareaRef"
                v-model="text"
                :disabled="disabled"
                rows="1"
                placeholder="Ask Numen to create, update, or manage content…"
                class="flex-1 resize-none rounded-lg border border-gray-700 bg-gray-800 px-3 py-2 text-sm text-gray-100 placeholder-gray-500 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 disabled:opacity-50 min-h-[36px] max-h-[120px] leading-6"
                style="height: 36px"
                @keydown="onKeydown"
            />
            <button
                :disabled="disabled || !text.trim()"
                class="flex-shrink-0 rounded-lg bg-indigo-600 px-3 py-2 text-sm text-white hover:bg-indigo-500 disabled:opacity-40 transition h-9 flex items-center gap-1.5"
                @click="send"
            >
                <span v-if="disabled" class="text-xs">●</span>
                <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                </svg>
            </button>
        </div>
        <p class="mt-1.5 text-xs text-gray-600">Enter to send · Shift+Enter for new line</p>
    </div>
</template>
