<script setup>
import { ref, watch } from 'vue';

const props = defineProps({
    vocabularyId: { type: String, required: true },
    selectedTermIds: { type: Array, default: () => [] },
    vocabularyName: { type: String, default: '' },
    allowMultiple: { type: Boolean, default: true },
});

const emit = defineEmits(['add', 'remove']);

const query = ref('');
const results = ref([]);
const loading = ref(false);
const showDropdown = ref(false);
let debounceTimer = null;

watch(query, (val) => {
    clearTimeout(debounceTimer);
    if (!val.trim()) {
        results.value = [];
        showDropdown.value = false;
        return;
    }
    loading.value = true;
    debounceTimer = setTimeout(async () => {
        try {
            const res = await fetch(
                `/admin/taxonomy/${props.vocabularyId}/terms/search?q=${encodeURIComponent(val)}`,
                { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } }
            );
            const json = await res.json();
            results.value = (json.data ?? []).filter(
                (t) => !props.selectedTermIds.includes(t.id)
            );
            showDropdown.value = true;
        } catch {
            results.value = [];
        } finally {
            loading.value = false;
        }
    }, 300);
});

function selectTerm(term) {
    emit('add', term.id);
    query.value = '';
    results.value = [];
    showDropdown.value = false;
}

function closeDropdown() {
    setTimeout(() => { showDropdown.value = false; }, 150);
}
</script>

<template>
    <div class="relative">
        <input
            v-model="query"
            type="text"
            :placeholder="`Search ${vocabularyName} terms…`"
            class="w-full px-3 py-1.5 bg-gray-800 border border-gray-700 rounded-lg text-sm text-white placeholder-gray-500 focus:outline-none focus:border-indigo-500"
            @blur="closeDropdown"
            @focus="query.trim() && (showDropdown = true)"
        />
        <div
            v-if="showDropdown && results.length"
            class="absolute z-50 mt-1 w-full bg-gray-800 border border-gray-700 rounded-lg shadow-lg overflow-hidden"
        >
            <button
                v-for="term in results"
                :key="term.id"
                type="button"
                class="w-full text-left px-3 py-2 text-sm text-gray-200 hover:bg-indigo-600/30 hover:text-white transition flex items-center gap-2"
                @mousedown.prevent="selectTerm(term)"
            >
                <span v-if="term.depth > 0" class="text-gray-500 text-xs">{{ '—'.repeat(term.depth) }}</span>
                {{ term.name }}
            </button>
        </div>
        <div
            v-else-if="showDropdown && !loading && query.trim()"
            class="absolute z-50 mt-1 w-full bg-gray-800 border border-gray-700 rounded-lg shadow-lg px-3 py-2 text-sm text-gray-500"
        >
            No matching terms found.
        </div>
    </div>
</template>
