<script setup>
import { ref, computed } from 'vue';
import TermBadge from './TermBadge.vue';

const props = defineProps({
    // Array of vocabularies, each with a `terms` array (flat list)
    vocabularies: { type: Array, default: () => [] },
    // Currently selected term ids
    modelValue: { type: Array, default: () => [] },
    // Whether the picker is read-only
    readonly: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue']);

const search = ref('');

function selectedTermObjects() {
    const allTerms = props.vocabularies.flatMap(v => v.terms ?? []);
    return props.modelValue
        .map(id => allTerms.find(t => t.id === id))
        .filter(Boolean);
}

function isSelected(termId) {
    return props.modelValue.includes(termId);
}

function toggleTerm(termId) {
    if (props.readonly) return;
    const current = [...props.modelValue];
    const idx = current.indexOf(termId);
    if (idx === -1) {
        current.push(termId);
    } else {
        current.splice(idx, 1);
    }
    emit('update:modelValue', current);
}

function removeTerm(term) {
    if (props.readonly) return;
    emit('update:modelValue', props.modelValue.filter(id => id !== term.id));
}

const filteredVocabularies = computed(() => {
    if (!search.value.trim()) return props.vocabularies;

    const q = search.value.toLowerCase();
    return props.vocabularies.map(vocab => ({
        ...vocab,
        terms: (vocab.terms ?? []).filter(t =>
            t.name.toLowerCase().includes(q) || t.slug.includes(q)
        ),
    })).filter(v => v.terms.length > 0);
});
</script>

<template>
    <div class="space-y-3">
        <!-- Selected terms (badge display) -->
        <div v-if="selectedTermObjects().length" class="flex flex-wrap gap-1.5">
            <TermBadge
                v-for="term in selectedTermObjects()"
                :key="term.id"
                :term="term"
                :removable="!readonly"
                @remove="removeTerm"
            />
        </div>
        <p v-else-if="readonly" class="text-xs text-gray-600">No terms assigned.</p>

        <!-- Search + term list -->
        <div v-if="!readonly">
            <input
                v-model="search"
                type="text"
                placeholder="Search terms…"
                class="w-full px-3 py-1.5 bg-gray-800 border border-gray-700 rounded text-white text-xs focus:outline-none focus:border-indigo-500 mb-2"
            />

            <div class="space-y-3 max-h-60 overflow-y-auto pr-1">
                <div v-for="vocab in filteredVocabularies" :key="vocab.id">
                    <p class="text-xs font-semibold text-gray-500 uppercase mb-1">{{ vocab.name }}</p>
                    <div class="space-y-0.5">
                        <label
                            v-for="term in vocab.terms"
                            :key="term.id"
                            class="flex items-center gap-2 px-2 py-1 rounded cursor-pointer hover:bg-gray-800/60"
                            :style="term.depth ? { paddingLeft: `${term.depth * 12 + 8}px` } : {}"
                        >
                            <input
                                type="checkbox"
                                :checked="isSelected(term.id)"
                                @change="toggleTerm(term.id)"
                                class="rounded border-gray-600 bg-gray-800 text-indigo-600"
                            />
                            <span class="text-xs text-gray-300">{{ term.name }}</span>
                            <span v-if="term.content_count > 0" class="text-xs text-gray-600 ml-auto">{{ term.content_count }}</span>
                        </label>
                    </div>
                </div>

                <p v-if="!filteredVocabularies.length" class="text-xs text-gray-600 text-center py-2">
                    No terms match your search.
                </p>
            </div>
        </div>
    </div>
</template>
