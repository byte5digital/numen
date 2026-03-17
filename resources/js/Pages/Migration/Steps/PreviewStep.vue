<script setup>
import { ref, onMounted } from 'vue';
import axios from 'axios';

const props = defineProps({
    spaceId: { type: String, required: true },
    session: { type: Object, required: true },
    mappings: { type: [Object, Array], default: null },
});

const emit = defineEmits(['preview-ready']);

const loading = ref(true);
const error = ref(null);
const preview = ref(null);
const activeTab = ref('content');

onMounted(async () => {
    await loadPreview();
});

async function loadPreview() {
    loading.value = true;
    error.value = null;

    try {
        const { data } = await axios.get(
            `/api/v1/spaces/${props.spaceId}/migrations/${props.session.id}/mappings/preview`
        );
        preview.value = data.data ?? data;
    } catch (err) {
        error.value = err.response?.data?.message ?? 'Failed to load preview';
    } finally {
        loading.value = false;
    }
}

function startImport() {
    emit('preview-ready', preview.value);
}

const tabs = [
    { key: 'content', label: 'Content', icon: '📝' },
    { key: 'taxonomy', label: 'Taxonomies', icon: '🏷️' },
    { key: 'summary', label: 'Summary', icon: '📊' },
];
</script>

<template>
    <div class="space-y-6">
        <div>
            <h2 class="text-lg font-semibold text-white mb-1">Preview Migration</h2>
            <p class="text-sm text-gray-500">Review how your content will look after migration.</p>
        </div>

        <!-- Loading -->
        <div v-if="loading" class="flex items-center justify-center py-12">
            <div class="flex items-center gap-3 text-gray-400">
                <span class="w-5 h-5 border-2 border-gray-600 border-t-indigo-400 rounded-full animate-spin" />
                <span>Generating preview…</span>
            </div>
        </div>

        <!-- Error -->
        <div v-if="error" class="p-4 bg-red-900/20 border border-red-800 rounded-lg">
            <p class="text-sm text-red-400">{{ error }}</p>
        </div>

        <!-- Preview Content -->
        <div v-if="!loading && preview">
            <!-- Tabs -->
            <div class="flex gap-1 mb-6 border-b border-gray-800">
                <button
                    v-for="tab in tabs"
                    :key="tab.key"
                    @click="activeTab = tab.key"
                    class="px-4 py-2.5 text-sm font-medium transition-colors border-b-2 -mb-px"
                    :class="activeTab === tab.key
                        ? 'text-indigo-400 border-indigo-400'
                        : 'text-gray-500 border-transparent hover:text-gray-300'"
                >
                    {{ tab.icon }} {{ tab.label }}
                </button>
            </div>

            <!-- Content Preview -->
            <div v-if="activeTab === 'content'" class="space-y-4">
                <div
                    v-for="(item, index) in (preview.items ?? preview.content ?? []).slice(0, 5)"
                    :key="index"
                    class="grid grid-cols-2 gap-4"
                >
                    <div class="p-4 bg-gray-800/30 border border-gray-700 rounded-lg">
                        <span class="text-xs text-gray-500 uppercase tracking-wide">Source</span>
                        <h4 class="text-sm font-medium text-gray-300 mt-1">{{ item.source_title ?? item.source?.title ?? 'Untitled' }}</h4>
                        <p class="text-xs text-gray-500 mt-1 line-clamp-3">{{ item.source_excerpt ?? item.source?.excerpt ?? '' }}</p>
                    </div>
                    <div class="p-4 bg-indigo-900/10 border border-indigo-800/30 rounded-lg">
                        <span class="text-xs text-indigo-400 uppercase tracking-wide">Numen</span>
                        <h4 class="text-sm font-medium text-gray-200 mt-1">{{ item.target_title ?? item.target?.title ?? 'Untitled' }}</h4>
                        <p class="text-xs text-gray-400 mt-1 line-clamp-3">{{ item.target_excerpt ?? item.target?.excerpt ?? '' }}</p>
                        <span class="inline-block mt-2 text-xs px-2 py-0.5 bg-gray-800 text-gray-400 rounded">
                            {{ item.target_type ?? item.target?.content_type ?? '' }}
                        </span>
                    </div>
                </div>

                <p v-if="!(preview.items ?? preview.content ?? []).length" class="text-center text-gray-500 py-8">
                    No content items to preview.
                </p>
            </div>

            <!-- Taxonomy Preview -->
            <div v-if="activeTab === 'taxonomy'" class="space-y-3">
                <div
                    v-for="(tax, index) in (preview.taxonomies ?? [])"
                    :key="index"
                    class="p-3 bg-gray-800/30 border border-gray-700 rounded-lg flex items-center justify-between"
                >
                    <div>
                        <span class="text-sm text-gray-200">{{ tax.source_name ?? tax.name }}</span>
                        <span class="text-gray-600 mx-2">→</span>
                        <span class="text-sm text-indigo-400">{{ tax.target_name ?? tax.mapped_to ?? 'New vocabulary' }}</span>
                    </div>
                    <span class="text-xs text-gray-500">{{ tax.term_count ?? tax.terms_count ?? 0 }} terms</span>
                </div>
                <p v-if="!(preview.taxonomies ?? []).length" class="text-center text-gray-500 py-8">
                    No taxonomy mappings to preview.
                </p>
            </div>

            <!-- Summary -->
            <div v-if="activeTab === 'summary'" class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                <div class="p-4 bg-gray-800/50 border border-gray-700 rounded-lg text-center">
                    <div class="text-2xl font-bold text-white">{{ preview.total_content ?? preview.counts?.content ?? 0 }}</div>
                    <div class="text-xs text-gray-500 mt-1">Content Items</div>
                </div>
                <div class="p-4 bg-gray-800/50 border border-gray-700 rounded-lg text-center">
                    <div class="text-2xl font-bold text-white">{{ preview.total_media ?? preview.counts?.media ?? 0 }}</div>
                    <div class="text-xs text-gray-500 mt-1">Media Files</div>
                </div>
                <div class="p-4 bg-gray-800/50 border border-gray-700 rounded-lg text-center">
                    <div class="text-2xl font-bold text-white">{{ preview.total_users ?? preview.counts?.users ?? 0 }}</div>
                    <div class="text-xs text-gray-500 mt-1">Users</div>
                </div>
                <div class="p-4 bg-gray-800/50 border border-gray-700 rounded-lg text-center">
                    <div class="text-2xl font-bold text-white">{{ preview.total_taxonomies ?? preview.counts?.taxonomies ?? 0 }}</div>
                    <div class="text-xs text-gray-500 mt-1">Taxonomies</div>
                </div>
            </div>

            <!-- Start Import Button -->
            <div class="flex justify-end pt-6">
                <button
                    @click="startImport"
                    class="px-6 py-3 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-500 transition flex items-center gap-2"
                >
                    🚀 Start Import
                </button>
            </div>
        </div>
    </div>
</template>
