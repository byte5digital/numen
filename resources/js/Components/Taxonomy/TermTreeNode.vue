<script setup>
import { ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import TermForm from './TermForm.vue';

const props = defineProps({
    term: { type: Object, required: true },
    vocabularyId: { type: String, required: true },
    allowHierarchy: { type: Boolean, default: true },
    depth: { type: Number, default: 0 },
});

const emit = defineEmits(['moved']);

const expanded = ref(true);
const showEditForm = ref(false);
const showAddChild = ref(false);

function deleteTerm() {
    if (confirm(`Delete term "${props.term.name}"? Children will be moved up.`)) {
        router.delete(`/admin/taxonomy/terms/${props.term.id}`, {
            preserveScroll: true,
        });
    }
}
</script>

<template>
    <div>
        <!-- Term Row -->
        <div
            class="flex items-center gap-2 px-3 py-2 rounded-lg hover:bg-gray-800/50 group"
            :style="{ paddingLeft: `${depth * 20 + 12}px` }"
        >
            <!-- Expand toggle (only if has children) -->
            <button
                v-if="term.children?.length"
                @click="expanded = !expanded"
                class="text-gray-600 hover:text-gray-300 text-xs w-4"
            >
                {{ expanded ? '▾' : '▸' }}
            </button>
            <span v-else class="w-4"></span>

            <!-- Term name (clickable link) -->
            <Link
                :href="`/admin/taxonomy/terms/${term.id}`"
                class="flex-1 text-sm text-gray-200 hover:text-indigo-300 transition"
            >{{ term.name }}</Link>

            <!-- Meta -->
            <span class="text-xs text-gray-600 font-mono">{{ term.slug }}</span>
            <span
                v-if="term.content_count > 0"
                class="px-1.5 py-0.5 text-xs bg-indigo-900/30 text-indigo-400 rounded-full"
            >{{ term.content_count }}</span>

            <!-- Actions (visible on hover) -->
            <div class="hidden group-hover:flex items-center gap-2">
                <button
                    v-if="allowHierarchy"
                    @click="showAddChild = !showAddChild"
                    class="text-xs text-indigo-500 hover:text-indigo-300"
                    title="Add child term"
                >
                    + Child
                </button>
                <button
                    @click="showEditForm = !showEditForm"
                    class="text-xs text-gray-400 hover:text-gray-200"
                >
                    Edit
                </button>
                <button
                    @click="deleteTerm"
                    class="text-xs text-red-500 hover:text-red-400"
                >
                    Delete
                </button>
            </div>
        </div>

        <!-- Edit Form -->
        <div v-if="showEditForm" class="mx-4 mb-2" :style="{ marginLeft: `${depth * 20 + 16}px` }">
            <TermForm
                :vocabulary-id="vocabularyId"
                :term="term"
                @saved="showEditForm = false"
                @cancel="showEditForm = false"
            />
        </div>

        <!-- Add Child Form -->
        <div v-if="showAddChild" class="mx-4 mb-2" :style="{ marginLeft: `${(depth + 1) * 20 + 16}px` }">
            <TermForm
                :vocabulary-id="vocabularyId"
                :parent-id="term.id"
                @saved="showAddChild = false"
                @cancel="showAddChild = false"
            />
        </div>

        <!-- Children (recursive) -->
        <div v-if="expanded && term.children?.length">
            <TermTreeNode
                v-for="child in term.children"
                :key="child.id"
                :term="child"
                :vocabulary-id="vocabularyId"
                :allow-hierarchy="allowHierarchy"
                :depth="depth + 1"
            />
        </div>
    </div>
</template>
