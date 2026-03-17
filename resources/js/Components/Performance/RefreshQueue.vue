<script setup>
import { ref, computed, onMounted } from 'vue';

const props = defineProps({
    spaceId: { type: String, required: true },
});

const suggestions = ref([]);
const loading = ref(false);
const filterPriority = ref('all');

const priorities = ['all', 'high', 'medium', 'low'];

const priorityColors = {
    high: 'bg-red-500/20 text-red-300',
    medium: 'bg-amber-500/20 text-amber-300',
    low: 'bg-blue-500/20 text-blue-300',
};

const filtered = computed(() => {
    if (filterPriority.value === 'all') return suggestions.value;
    return suggestions.value.filter(s => s.priority === filterPriority.value);
});

async function fetchSuggestions() {
    loading.value = true;
    try {
        const res = await fetch(`/api/v1/spaces/${props.spaceId}/refresh-suggestions`, { credentials: 'include' });
        if (res.ok) {
            const json = await res.json();
            suggestions.value = json.data ?? json ?? [];
        }
    } catch (e) {
        console.error('Failed to fetch refresh suggestions', e);
    } finally {
        loading.value = false;
    }
}

function getCsrf() {
    return document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? '';
}

async function accept(id) {
    try {
        await fetch(`/api/v1/spaces/${props.spaceId}/refresh-suggestions/${id}/accept`, {
            method: 'POST',
            credentials: 'include',
            headers: { 'Content-Type': 'application/json', 'X-XSRF-TOKEN': getCsrf() },
        });
        suggestions.value = suggestions.value.filter(s => s.id !== id);
    } catch (e) {
        console.error('Failed to accept suggestion', e);
    }
}

async function dismiss(id) {
    try {
        await fetch(`/api/v1/spaces/${props.spaceId}/refresh-suggestions/${id}/dismiss`, {
            method: 'POST',
            credentials: 'include',
            headers: { 'Content-Type': 'application/json', 'X-XSRF-TOKEN': getCsrf() },
        });
        suggestions.value = suggestions.value.filter(s => s.id !== id);
    } catch (e) {
        console.error('Failed to dismiss suggestion', e);
    }
}

onMounted(fetchSuggestions);
</script>

<template>
    <div class="bg-gray-900 rounded-xl border border-gray-800 p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-sm font-semibold text-white">Content Refresh Queue</h3>
            <div class="flex items-center gap-1">
                <button
                    v-for="p in priorities"
                    :key="p"
                    @click="filterPriority = p"
                    class="px-2 py-1 text-xs rounded-md transition capitalize"
                    :class="filterPriority === p
                        ? 'bg-gray-700 text-white'
                        : 'text-gray-500 hover:text-gray-300'"
                >
                    {{ p }}
                </button>
            </div>
        </div>

        <div v-if="loading" class="py-8 text-center text-gray-500 text-sm">Loading suggestions…</div>

        <div v-else-if="!filtered.length" class="py-8 text-center text-gray-600 text-sm">
            No refresh suggestions{{ filterPriority !== 'all' ? ` with ${filterPriority} priority` : '' }}.
        </div>

        <div v-else class="space-y-3">
            <div v-for="item in filtered" :key="item.id"
                 class="p-4 bg-gray-950 rounded-lg border border-gray-800">
                <div class="flex items-start justify-between">
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 mb-1">
                            <span class="text-sm font-medium text-white truncate">
                                {{ item.content_title ?? item.title ?? `Content #${item.content_id}` }}
                            </span>
                            <span class="px-2 py-0.5 text-xs rounded-full shrink-0"
                                  :class="priorityColors[item.priority] ?? 'bg-gray-700 text-gray-400'">
                                {{ item.priority }}
                            </span>
                        </div>
                        <p class="text-xs text-gray-400 mb-2">{{ item.reason ?? 'Content may benefit from a refresh' }}</p>
                        <div class="flex items-center gap-4 text-xs text-gray-500">
                            <span v-if="item.score != null">Score: {{ item.score }}</span>
                            <span v-if="item.days_since_update != null">{{ item.days_since_update }}d since update</span>
                            <span v-if="item.decline_percentage != null">↓ {{ item.decline_percentage }}% decline</span>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 ml-3 shrink-0">
                        <button @click="accept(item.id)"
                                class="px-3 py-1.5 text-xs font-medium bg-emerald-500/20 text-emerald-300 hover:bg-emerald-500/30 rounded-lg transition">
                            Accept
                        </button>
                        <button @click="dismiss(item.id)"
                                class="px-3 py-1.5 text-xs text-gray-500 hover:text-gray-300 transition">
                            Dismiss
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
