<script setup lang="ts">
import { ref } from 'vue'

const props = defineProps<{
    conversationId: string
    action: {
        description: string
        payload?: Record<string, unknown>
    }
}>()

const emit = defineEmits<{
    confirmed: []
    cancelled: []
}>()

const loading = ref(false)

async function confirm() {
    loading.value = true
    try {
        await fetch(`/api/v1/chat/conversations/${props.conversationId}/confirm`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        })
        emit('confirmed')
    } finally {
        loading.value = false
    }
}

async function cancel() {
    loading.value = true
    try {
        await fetch(`/api/v1/chat/conversations/${props.conversationId}/confirm`, {
            method: 'DELETE',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        })
        emit('cancelled')
    } finally {
        loading.value = false
    }
}
</script>

<template>
    <div class="mt-2 rounded-lg border border-yellow-600/40 bg-yellow-900/20 px-4 py-3">
        <p class="text-sm text-yellow-200 mb-3">{{ action.description }}</p>
        <div class="flex gap-2">
            <button
                :disabled="loading"
                class="flex items-center gap-1.5 px-3 py-1.5 rounded-md bg-green-600 hover:bg-green-500 text-white text-xs font-medium transition disabled:opacity-50"
                @click="confirm"
            >
                ✅ Confirm
            </button>
            <button
                :disabled="loading"
                class="flex items-center gap-1.5 px-3 py-1.5 rounded-md bg-gray-700 hover:bg-gray-600 text-white text-xs font-medium transition disabled:opacity-50"
                @click="cancel"
            >
                ❌ Cancel
            </button>
        </div>
    </div>
</template>
