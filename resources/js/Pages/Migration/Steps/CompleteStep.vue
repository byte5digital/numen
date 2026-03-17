<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    session: { type: Object, default: null },
    result: { type: Object, default: null },
});

const emit = defineEmits(['start-new']);

const stats = computed(() => {
    const r = props.result ?? {};
    return {
        content: r.content?.completed ?? r.counts?.content ?? 0,
        media: r.media?.completed ?? r.counts?.media ?? 0,
        users: r.users?.completed ?? r.counts?.users ?? 0,
        taxonomies: r.taxonomies?.completed ?? r.counts?.taxonomies ?? 0,
    };
});

const failedItems = computed(() => {
    return props.result?.errors ?? [];
});
</script>

<template>
    <div class="space-y-6">
        <div class="text-center py-6">
            <div class="text-5xl mb-4">🎉</div>
            <h2 class="text-2xl font-bold text-white mb-2">Migration Complete!</h2>
            <p class="text-gray-400">Your content has been successfully imported to Numen.</p>
        </div>

        <!-- Stats Grid -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 max-w-2xl mx-auto">
            <div class="p-4 bg-emerald-900/10 border border-emerald-800/30 rounded-lg text-center">
                <div class="text-2xl font-bold text-emerald-400">{{ stats.content }}</div>
                <div class="text-xs text-gray-500 mt-1">📝 Content Items</div>
            </div>
            <div class="p-4 bg-blue-900/10 border border-blue-800/30 rounded-lg text-center">
                <div class="text-2xl font-bold text-blue-400">{{ stats.media }}</div>
                <div class="text-xs text-gray-500 mt-1">🖼️ Media Files</div>
            </div>
            <div class="p-4 bg-purple-900/10 border border-purple-800/30 rounded-lg text-center">
                <div class="text-2xl font-bold text-purple-400">{{ stats.users }}</div>
                <div class="text-xs text-gray-500 mt-1">👥 Users</div>
            </div>
            <div class="p-4 bg-amber-900/10 border border-amber-800/30 rounded-lg text-center">
                <div class="text-2xl font-bold text-amber-400">{{ stats.taxonomies }}</div>
                <div class="text-xs text-gray-500 mt-1">🏷️ Taxonomies</div>
            </div>
        </div>

        <!-- Failed Items -->
        <div v-if="failedItems.length > 0" class="space-y-3">
            <h3 class="text-sm font-medium text-red-400">⚠️ Failed Items ({{ failedItems.length }})</h3>
            <div class="max-h-48 overflow-y-auto space-y-1">
                <div
                    v-for="(err, index) in failedItems"
                    :key="index"
                    class="p-3 bg-red-900/10 border border-red-900/30 rounded-lg flex items-center justify-between"
                >
                    <div>
                        <span class="text-sm text-gray-300">{{ err.item ?? err.title ?? `Item ${index + 1}` }}</span>
                        <p class="text-xs text-red-400 mt-0.5">{{ err.message ?? err.error ?? 'Unknown error' }}</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Actions -->
        <div class="flex items-center justify-center gap-4 pt-6">
            <Link
                href="/admin/content"
                class="px-5 py-2.5 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-500 transition"
            >
                📝 View Imported Content
            </Link>
            <button
                @click="emit('start-new')"
                class="px-5 py-2.5 bg-gray-700 text-white rounded-lg text-sm font-medium hover:bg-gray-600 transition"
            >
                🔄 Start New Migration
            </button>
        </div>
    </div>
</template>
