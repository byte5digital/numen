<script setup>
import { ref, computed, watch, onMounted, onBeforeUnmount } from 'vue';
import axios from 'axios';

const props = defineProps({
    modelValue: {
        type: Array,
        default: () => [],
    },
});

const emit = defineEmits(['update:modelValue']);

const inputValue = ref('');
const suggestions = ref([]);
const showDropdown = ref(false);
const loadingSuggestions = ref(false);
const inputRef = ref(null);
const wrapperRef = ref(null);

const MAX_TAGS = 20;

const canAddMore = computed(() => props.modelValue.length < MAX_TAGS);

function addTag(tag) {
    const trimmed = tag.trim().toLowerCase();
    if (!trimmed) return;
    if (props.modelValue.includes(trimmed)) return;
    if (!canAddMore.value) return;

    emit('update:modelValue', [...props.modelValue, trimmed]);
    inputValue.value = '';
    suggestions.value = [];
    showDropdown.value = false;
}

function removeTag(tag) {
    emit('update:modelValue', props.modelValue.filter(t => t !== tag));
}

function onKeydown(e) {
    if (e.key === 'Enter' || e.key === ',') {
        e.preventDefault();
        addTag(inputValue.value);
    } else if (e.key === 'Backspace' && inputValue.value === '' && props.modelValue.length > 0) {
        removeTag(props.modelValue[props.modelValue.length - 1]);
    } else if (e.key === 'Escape') {
        showDropdown.value = false;
    }
}

let suggestTimeout = null;

async function fetchSuggestions(query) {
    if (!query || query.length < 1) {
        suggestions.value = [];
        showDropdown.value = false;
        return;
    }

    loadingSuggestions.value = true;
    try {
        // Fetch tags from existing assets — using media index endpoint
        const res = await axios.get('/v1/media', {
            params: { search: query, per_page: 5 },
        });
        const allTags = new Set();
        (res.data?.data || []).forEach(asset => {
            (asset.tags || []).forEach(t => {
                if (t.toLowerCase().includes(query.toLowerCase()) && !props.modelValue.includes(t)) {
                    allTags.add(t);
                }
            });
        });
        suggestions.value = Array.from(allTags).slice(0, 8);
        showDropdown.value = suggestions.value.length > 0;
    } catch {
        suggestions.value = [];
        showDropdown.value = false;
    } finally {
        loadingSuggestions.value = false;
    }
}

watch(inputValue, (val) => {
    clearTimeout(suggestTimeout);
    suggestTimeout = setTimeout(() => fetchSuggestions(val), 300);
});

function onClickOutside(e) {
    if (wrapperRef.value && !wrapperRef.value.contains(e.target)) {
        showDropdown.value = false;
    }
}

onMounted(() => document.addEventListener('mousedown', onClickOutside));
onBeforeUnmount(() => document.removeEventListener('mousedown', onClickOutside));
</script>

<template>
    <div ref="wrapperRef" class="relative">
        <div
            class="flex flex-wrap gap-1.5 min-h-[38px] p-1.5 bg-gray-800 border border-gray-700 rounded-lg focus-within:ring-1 focus-within:ring-indigo-500 focus-within:border-indigo-500 cursor-text"
            @click="inputRef?.focus()"
        >
            <!-- Tag chips -->
            <span
                v-for="tag in modelValue"
                :key="tag"
                class="inline-flex items-center gap-1 px-2 py-0.5 bg-indigo-600/30 text-indigo-300 border border-indigo-500/40 rounded text-xs font-medium"
            >
                {{ tag }}
                <button
                    type="button"
                    class="ml-0.5 text-indigo-400 hover:text-white transition"
                    @click.stop="removeTag(tag)"
                    :aria-label="`Remove tag ${tag}`"
                >
                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </span>

            <!-- Input -->
            <input
                v-if="canAddMore"
                ref="inputRef"
                v-model="inputValue"
                type="text"
                class="flex-1 min-w-[80px] bg-transparent text-sm text-white placeholder-gray-500 outline-none py-0.5 px-1"
                placeholder="Add tag…"
                @keydown="onKeydown"
                @focus="inputValue && fetchSuggestions(inputValue)"
            />
            <span v-else class="text-xs text-gray-500 self-center px-1">Max {{ MAX_TAGS }} tags</span>
        </div>

        <!-- Autocomplete dropdown -->
        <div
            v-if="showDropdown && suggestions.length > 0"
            class="absolute z-20 top-full mt-1 left-0 right-0 bg-gray-800 border border-gray-700 rounded-lg shadow-xl overflow-hidden"
        >
            <button
                v-for="sug in suggestions"
                :key="sug"
                type="button"
                class="w-full text-left px-3 py-2 text-sm text-gray-200 hover:bg-gray-700 transition"
                @mousedown.prevent="addTag(sug)"
            >
                {{ sug }}
            </button>
        </div>

        <p class="mt-1 text-xs text-gray-500">Press Enter or comma to add. {{ modelValue.length }}/{{ MAX_TAGS }} tags.</p>
    </div>
</template>
