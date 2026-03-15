<script setup>
import { ref, watch, computed } from 'vue';

const props = defineProps({
    modelValue: {
        type: Object,
        default: () => ({ search: '', type: '', tags: [], folder_id: null }),
    },
});

const emit = defineEmits(['update:modelValue']);

const localSearch = ref(props.modelValue.search ?? '');
const localType   = ref(props.modelValue.type ?? '');
const localTags   = ref([...(props.modelValue.tags ?? [])]);
const tagInput    = ref('');
const tagSuggestions = ref([]);
const showSuggestions = ref(false);

let debounceTimer = null;

function emitUpdate(patch) {
    emit('update:modelValue', {
        ...props.modelValue,
        ...patch,
    });
}

// Debounced search
watch(localSearch, (val) => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => emitUpdate({ search: val }), 300);
});

watch(localType, (val) => emitUpdate({ type: val }));

// Tag autocomplete — fetch matching tags from API
watch(tagInput, async (val) => {
    if (!val || val.length < 2) {
        tagSuggestions.value = [];
        showSuggestions.value = false;
        return;
    }
    try {
        const res = await fetch(`/api/v1/media/tags?search=${encodeURIComponent(val)}`);
        if (res.ok) {
            const json = await res.json();
            tagSuggestions.value = (json.data ?? []).filter(t => !localTags.value.includes(t));
            showSuggestions.value = tagSuggestions.value.length > 0;
        }
    } catch {
        tagSuggestions.value = [];
    }
});

function addTag(tag) {
    const t = (tag || tagInput.value).trim();
    if (t && !localTags.value.includes(t)) {
        localTags.value = [...localTags.value, t];
        emitUpdate({ tags: localTags.value });
    }
    tagInput.value = '';
    showSuggestions.value = false;
}

function removeTag(tag) {
    localTags.value = localTags.value.filter(t => t !== tag);
    emitUpdate({ tags: localTags.value });
}

function handleTagKeydown(e) {
    if (e.key === 'Enter' || e.key === ',') {
        e.preventDefault();
        addTag();
    } else if (e.key === 'Escape') {
        showSuggestions.value = false;
    } else if (e.key === 'Backspace' && !tagInput.value && localTags.value.length) {
        removeTag(localTags.value[localTags.value.length - 1]);
    }
}

const hasFilters = computed(() =>
    localSearch.value || localType.value || localTags.value.length > 0
);

function clearFilters() {
    localSearch.value = '';
    localType.value = '';
    localTags.value = [];
    tagInput.value = '';
    emitUpdate({ search: '', type: '', tags: [], folder_id: props.modelValue.folder_id });
}
</script>

<template>
    <div class="flex flex-wrap items-start gap-2 p-3 bg-white border-b border-gray-200">
        <!-- Search input -->
        <div class="relative flex-1 min-w-[200px]">
            <span class="absolute inset-y-0 left-3 flex items-center text-gray-400 pointer-events-none">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M21 21l-4.35-4.35M17 11A6 6 0 1 1 5 11a6 6 0 0 1 12 0z"/>
                </svg>
            </span>
            <input
                v-model="localSearch"
                type="text"
                placeholder="Search files…"
                class="w-full pl-9 pr-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
            />
        </div>

        <!-- Type dropdown -->
        <select
            v-model="localType"
            class="py-2 pl-3 pr-8 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 bg-white"
        >
            <option value="">All Types</option>
            <option value="image">Images</option>
            <option value="video">Videos</option>
            <option value="application">Documents</option>
            <option value="other">Other</option>
        </select>

        <!-- Tag input -->
        <div class="relative flex-1 min-w-[180px]">
            <div class="flex flex-wrap items-center gap-1 px-2 py-1 border border-gray-300 rounded-lg focus-within:ring-2 focus-within:ring-indigo-500 bg-white min-h-[38px]">
                <span
                    v-for="tag in localTags"
                    :key="tag"
                    class="inline-flex items-center gap-1 px-2 py-0.5 text-xs font-medium bg-indigo-100 text-indigo-700 rounded-full"
                >
                    {{ tag }}
                    <button
                        type="button"
                        class="hover:text-indigo-900 focus:outline-none"
                        @click="removeTag(tag)"
                    >×</button>
                </span>
                <input
                    v-model="tagInput"
                    type="text"
                    placeholder="Filter by tag…"
                    class="flex-1 min-w-[80px] text-sm focus:outline-none bg-transparent"
                    @keydown="handleTagKeydown"
                    @blur="showSuggestions = false"
                    @focus="tagInput.length >= 2 && (showSuggestions = tagSuggestions.length > 0)"
                />
            </div>

            <!-- Autocomplete dropdown -->
            <ul
                v-if="showSuggestions"
                class="absolute z-20 mt-1 w-full bg-white border border-gray-200 rounded-lg shadow-lg max-h-40 overflow-auto text-sm"
            >
                <li
                    v-for="suggestion in tagSuggestions"
                    :key="suggestion"
                    class="px-3 py-2 cursor-pointer hover:bg-indigo-50 hover:text-indigo-700"
                    @mousedown.prevent="addTag(suggestion)"
                >
                    {{ suggestion }}
                </li>
            </ul>
        </div>

        <!-- Clear filters -->
        <button
            v-if="hasFilters"
            type="button"
            class="px-3 py-2 text-sm text-gray-500 hover:text-gray-800 border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors"
            @click="clearFilters"
        >
            Clear
        </button>
    </div>
</template>
