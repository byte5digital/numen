<script setup>
import { computed } from 'vue';

const props = defineProps({
    assets:     { type: Array,   default: () => [] },
    selectable: { type: Boolean, default: false },
    selected:   { type: Array,   default: () => [] },
});

const emit = defineEmits(['select', 'deselect', 'open']);

function isSelected(asset) {
    return props.selected.some(s => s.id === asset.id);
}

function formatBytes(bytes) {
    if (!bytes) return '—';
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / 1048576).toFixed(1) + ' MB';
}

function fileIcon(asset) {
    const mime = asset.mime_type || '';
    if (mime.startsWith('image/')) return '🖼️';
    if (mime.startsWith('video/')) return '🎬';
    if (mime.includes('pdf'))      return '📄';
    if (mime.includes('audio'))    return '🎵';
    return '📁';
}

function thumbnailUrl(asset) {
    // Use a variant thumbnail if available, else direct URL for images
    if (asset.variants?.thumbnail) return asset.variants.thumbnail;
    if (asset.mime_type?.startsWith('image/') && asset.url) return asset.url;
    return null;
}

function toggleSelect(asset) {
    if (isSelected(asset)) {
        emit('deselect', asset);
    } else {
        emit('select', asset);
    }
}

function handleCardClick(asset) {
    if (props.selectable) {
        toggleSelect(asset);
    } else {
        emit('open', asset);
    }
}
</script>

<template>
    <div class="grid grid-cols-3 sm:grid-cols-4 lg:grid-cols-6 gap-3">
        <div
            v-for="asset in assets"
            :key="asset.id"
            class="group relative bg-gray-900 rounded-lg border border-gray-800 overflow-hidden cursor-pointer hover:border-indigo-500 transition-colors"
            :class="{ 'border-indigo-500 ring-2 ring-indigo-500/40': selectable && isSelected(asset) }"
            @click="handleCardClick(asset)"
        >
            <!-- Checkbox (selectable mode) -->
            <div
                v-if="selectable"
                class="absolute top-2 left-2 z-10"
                @click.stop="toggleSelect(asset)"
            >
                <div
                    class="w-5 h-5 rounded border-2 flex items-center justify-center transition-colors"
                    :class="isSelected(asset)
                        ? 'bg-indigo-600 border-indigo-600'
                        : 'bg-gray-900/80 border-gray-600 group-hover:border-gray-400'"
                >
                    <svg v-if="isSelected(asset)" class="w-3 h-3 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                    </svg>
                </div>
            </div>

            <!-- Thumbnail / Icon -->
            <div class="aspect-square bg-gray-800 flex items-center justify-center overflow-hidden">
                <img
                    v-if="thumbnailUrl(asset)"
                    :src="thumbnailUrl(asset)"
                    :alt="asset.filename"
                    class="w-full h-full object-cover"
                    loading="lazy"
                />
                <span v-else class="text-3xl select-none">{{ fileIcon(asset) }}</span>
            </div>

            <!-- Info -->
            <div class="px-2 py-2">
                <p class="text-xs text-gray-300 truncate font-medium leading-tight" :title="asset.filename">
                    {{ asset.filename }}
                </p>
                <span class="inline-block mt-1 text-xs text-gray-500 bg-gray-800 rounded px-1.5 py-0.5">
                    {{ formatBytes(asset.file_size) }}
                </span>
            </div>

            <!-- Open overlay (non-select mode) -->
            <div
                v-if="!selectable"
                class="absolute inset-0 bg-black/0 group-hover:bg-black/20 transition-colors flex items-center justify-center opacity-0 group-hover:opacity-100"
            >
                <span class="bg-gray-900/80 rounded-full px-3 py-1 text-xs text-white">View</span>
            </div>
        </div>

        <!-- Empty state -->
        <div v-if="assets.length === 0" class="col-span-full flex flex-col items-center justify-center py-16 text-gray-600">
            <span class="text-4xl mb-3">🖼️</span>
            <p class="text-sm">No assets found</p>
        </div>
    </div>
</template>
