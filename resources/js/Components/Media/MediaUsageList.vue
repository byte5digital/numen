<script setup>
import { ref, watch, onMounted } from 'vue';
import axios from 'axios';

const props = defineProps({
    assetId: {
        type: String,
        required: true,
    },
});

const loading = ref(false);
const items = ref([]);
const error = ref(null);

async function fetchUsage() {
    if (!props.assetId) return;
    loading.value = true;
    error.value = null;
    try {
        const res = await axios.get(`/v1/media/${props.assetId}/usage`);
        items.value = res.data?.data ?? [];
    } catch (e) {
        error.value = e?.response?.data?.message ?? 'Failed to load usage data.';
        items.value = [];
    } finally {
        loading.value = false;
    }
}

const STATUS_CLASSES = {
    published: 'bg-green-500/20 text-green-400 border border-green-500/30',
    draft: 'bg-gray-500/20 text-gray-400 border border-gray-500/30',
    archived: 'bg-yellow-500/20 text-yellow-400 border border-yellow-500/30',
    review: 'bg-blue-500/20 text-blue-400 border border-blue-500/30',
};

function statusClass(status) {
    return STATUS_CLASSES[status] ?? STATUS_CLASSES.draft;
}

onMounted(fetchUsage);
watch(() => props.assetId, fetchUsage);
</script>

<template>
    <div>
        <!-- Loading -->
        <div v-if="loading" class="flex items-center gap-2 text-sm text-gray-400 py-4">
            <svg class="animate-spin w-4 h-4 text-indigo-400" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
            </svg>
            Checking usage…
        </div>

        <!-- Error -->
        <div v-else-if="error" class="text-sm text-red-400 py-2">
            {{ error }}
        </div>

        <!-- Empty -->
        <div v-else-if="items.length === 0" class="text-sm text-gray-500 py-3 flex items-center gap-2">
            <svg class="w-4 h-4 text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
            Not used in any content items.
        </div>

        <!-- Usage list -->
        <ul v-else class="divide-y divide-gray-800">
            <li
                v-for="item in items"
                :key="item.id"
                class="py-2.5 flex items-center justify-between gap-3"
            >
                <div class="min-w-0">
                    <a
                        v-if="item.edit_url"
                        :href="item.edit_url"
                        class="text-sm font-medium text-indigo-400 hover:text-indigo-300 truncate block"
                        target="_blank"
                    >
                        {{ item.title || 'Untitled' }}
                    </a>
                    <span v-else class="text-sm font-medium text-gray-200 truncate block">
                        {{ item.title || 'Untitled' }}
                    </span>
                    <span class="text-xs text-gray-500">{{ item.type }}</span>
                </div>
                <span
                    class="flex-shrink-0 text-xs px-2 py-0.5 rounded-full"
                    :class="statusClass(item.status)"
                >
                    {{ item.status }}
                </span>
            </li>
        </ul>

        <p v-if="!loading && items.length > 0" class="mt-2 text-xs text-gray-500">
            Used in {{ items.length }} content item{{ items.length !== 1 ? 's' : '' }}.
        </p>
    </div>
</template>
