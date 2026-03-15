<script setup>
import { ref } from 'vue';

const props = defineProps({
    item:   { type: Object, required: true },
    format: { type: Object, default: () => ({}) },
});

const emit = defineEmits(['retry']);

const copied = ref(false);

const formatMeta = {
    twitter_thread:      { icon: '🐦' },
    linkedin_post:       { icon: '💼' },
    newsletter:          { icon: '📧' },
    instagram_caption:   { icon: '📸' },
    podcast_script:      { icon: '🎙️' },
    product_description: { icon: '🛍️' },
    faq:                 { icon: '❓' },
    youtube_description: { icon: '▶️' },
};

function icon(key) {
    return formatMeta[key]?.icon ?? '📄';
}

function statusClass(status) {
    return {
        pending:    'bg-gray-800 text-gray-400',
        processing: 'bg-amber-900/50 text-amber-400',
        completed:  'bg-emerald-900/50 text-emerald-400',
        failed:     'bg-red-900/50 text-red-400',
    }[status] ?? 'bg-gray-800 text-gray-400';
}

function statusLabel(status) {
    return {
        pending:    '⏳ Pending',
        processing: '⚙️ Processing',
        completed:  '✓ Completed',
        failed:     '✗ Failed',
    }[status] ?? status;
}

async function copyOutput() {
    const text = props.item.output_text ?? '';
    try {
        await navigator.clipboard.writeText(text);
        copied.value = true;
        setTimeout(() => { copied.value = false; }, 2000);
    } catch {
        // fallback: select textarea
    }
}

function isTwitterThread(item) {
    return item.format_key === 'twitter_thread' && Array.isArray(item.output_parts) && item.output_parts.length;
}
</script>

<template>
    <div class="rounded-xl border border-gray-800 bg-gray-950/50 overflow-hidden">
        <!-- Header -->
        <div class="flex items-center justify-between px-4 py-3 border-b border-gray-800">
            <div class="flex items-center gap-2">
                <span class="text-lg leading-none">{{ icon(item.format_key) }}</span>
                <span class="text-sm font-medium text-white">{{ format.label ?? item.format_key }}</span>
            </div>
            <span class="text-xs px-2 py-0.5 rounded-full" :class="statusClass(item.status)">
                {{ statusLabel(item.status) }}
            </span>
        </div>

        <!-- Staleness warning -->
        <div v-if="item.is_stale" class="flex items-center gap-2 px-4 py-2 bg-amber-900/20 border-b border-amber-800/30 text-amber-400 text-xs">
            <span>⚠️</span>
            <span>Source updated — consider re-repurposing</span>
        </div>

        <!-- Completed: twitter thread -->
        <div v-if="item.status === 'completed' && isTwitterThread(item)" class="p-4 space-y-2">
            <div
                v-for="(tweet, idx) in item.output_parts"
                :key="idx"
                class="flex gap-2.5"
            >
                <span class="shrink-0 text-xs font-mono text-gray-600 w-5 text-right mt-0.5">{{ idx + 1 }}.</span>
                <p class="text-sm text-gray-200 leading-relaxed">{{ tweet }}</p>
            </div>
            <!-- Copy all -->
            <div class="pt-2 border-t border-gray-800">
                <button @click="copyOutput" class="text-xs text-indigo-400 hover:text-indigo-300 transition">
                    {{ copied ? '✓ Copied' : '📋 Copy all tweets' }}
                </button>
            </div>
        </div>

        <!-- Completed: other formats -->
        <div v-else-if="item.status === 'completed'" class="p-4">
            <p class="text-sm text-gray-200 whitespace-pre-wrap leading-relaxed">{{ item.output_text }}</p>
            <div class="mt-3 border-t border-gray-800 pt-3">
                <button @click="copyOutput" class="text-xs text-indigo-400 hover:text-indigo-300 transition">
                    {{ copied ? '✓ Copied' : '📋 Copy to clipboard' }}
                </button>
            </div>
        </div>

        <!-- Pending / Processing -->
        <div v-else-if="item.status === 'pending' || item.status === 'processing'" class="px-4 py-5 flex items-center gap-2 text-gray-500 text-xs">
            <svg class="animate-spin h-3.5 w-3.5 text-indigo-400" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path>
            </svg>
            <span>{{ item.status === 'processing' ? 'AI is repurposing…' : 'Queued, waiting to start…' }}</span>
        </div>

        <!-- Failed -->
        <div v-else-if="item.status === 'failed'" class="px-4 py-4 space-y-2">
            <p class="text-xs text-red-400">{{ item.error_message ?? 'An error occurred during repurposing.' }}</p>
            <button
                @click="emit('retry', item)"
                class="text-xs px-3 py-1.5 border border-red-700/50 text-red-400 rounded-lg hover:bg-red-900/30 transition"
            >
                ↩ Retry
            </button>
        </div>
    </div>
</template>
