<script setup>
import { ref, computed } from 'vue';

const props = defineProps({
    selectedAssets: { type: Array, default: () => [] },
    folders:        { type: Array, default: () => [] },
});

const emit = defineEmits(['move', 'delete', 'clear']);

const moveFolderId = ref('');

const count = computed(() => props.selectedAssets.length);

function handleMove() {
    if (!moveFolderId.value && moveFolderId.value !== 0) return;
    emit('move', moveFolderId.value === '' ? null : Number(moveFolderId.value));
    moveFolderId.value = '';
}

function handleDelete() {
    const confirmed = window.confirm(
        `Delete ${count.value} selected file${count.value === 1 ? '' : 's'}? This cannot be undone.`
    );
    if (confirmed) emit('delete');
}
</script>

<template>
    <div
        v-if="count > 0"
        class="flex items-center gap-3 px-4 py-2 bg-indigo-50 border-b border-indigo-200 text-sm"
    >
        <!-- Selected count -->
        <span class="font-medium text-indigo-700">
            {{ count }} selected
        </span>

        <span class="text-indigo-300">|</span>

        <!-- Move to folder -->
        <div class="flex items-center gap-2">
            <label class="text-gray-600 whitespace-nowrap">Move to:</label>
            <select
                v-model="moveFolderId"
                class="py-1 pl-2 pr-7 text-sm border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-indigo-500 bg-white"
            >
                <option value="">— select folder —</option>
                <option value="root">Root (no folder)</option>
                <option
                    v-for="folder in folders"
                    :key="folder.id"
                    :value="folder.id"
                >
                    {{ folder.name }}
                </option>
            </select>
            <button
                type="button"
                :disabled="moveFolderId === ''"
                class="px-3 py-1 text-sm rounded bg-indigo-600 text-white hover:bg-indigo-700 disabled:opacity-40 disabled:cursor-not-allowed transition-colors"
                @click="handleMove"
            >
                Move
            </button>
        </div>

        <span class="text-indigo-300">|</span>

        <!-- Delete -->
        <button
            type="button"
            class="flex items-center gap-1 px-3 py-1 text-sm rounded bg-red-50 text-red-600 border border-red-200 hover:bg-red-100 hover:text-red-700 transition-colors"
            @click="handleDelete"
        >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
            </svg>
            Delete
        </button>

        <!-- Clear selection -->
        <button
            type="button"
            class="ml-auto text-xs text-gray-500 hover:text-gray-800 underline"
            @click="emit('clear')"
        >
            Clear selection
        </button>
    </div>
</template>
