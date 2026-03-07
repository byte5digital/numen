<script setup>
import { ref, computed } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import TermTree from '../../Components/Taxonomy/TermTree.vue';
import TermForm from '../../Components/Taxonomy/TermForm.vue';

const props = defineProps({
    vocabulary: { type: Object, required: true },
    tree: { type: Array, default: () => [] },
});

const flash = computed(() => usePage().props.flash ?? {});
const showEditForm = ref(false);
const showAddTerm = ref(false);

const editForm = ref({
    name: props.vocabulary.name,
    slug: props.vocabulary.slug,
    description: props.vocabulary.description ?? '',
    hierarchy: props.vocabulary.hierarchy,
    allow_multiple: props.vocabulary.allow_multiple,
    sort_order: props.vocabulary.sort_order,
});

function submitEdit() {
    router.patch(`/admin/taxonomy/${props.vocabulary.id}`, editForm.value, {
        onSuccess: () => { showEditForm.value = false; },
    });
}

function deleteSelf() {
    if (confirm(`Delete vocabulary "${props.vocabulary.name}" and ALL its terms?`)) {
        router.delete(`/admin/taxonomy/${props.vocabulary.id}`);
    }
}
</script>

<template>
    <div>
        <!-- Header -->
        <div class="flex items-center justify-between mb-8">
            <div>
                <div class="flex items-center gap-2 text-sm text-gray-500 mb-1">
                    <Link href="/admin/taxonomy" class="hover:text-indigo-400">Taxonomy</Link>
                    <span>/</span>
                    <span class="text-gray-300">{{ vocabulary.name }}</span>
                </div>
                <h1 class="text-2xl font-bold text-white">{{ vocabulary.name }}</h1>
                <p class="text-gray-500 mt-1 font-mono text-sm">{{ vocabulary.slug }}</p>
            </div>
            <div class="flex gap-3">
                <button @click="showEditForm = !showEditForm" class="px-4 py-2 bg-gray-700 text-gray-200 rounded-lg text-sm hover:bg-gray-600 transition">
                    Edit
                </button>
                <button @click="showAddTerm = !showAddTerm" class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm hover:bg-indigo-500 transition">
                    + Add Term
                </button>
                <button @click="deleteSelf" class="px-4 py-2 bg-red-700/40 text-red-400 rounded-lg text-sm hover:bg-red-700/60 transition">
                    Delete
                </button>
            </div>
        </div>

        <!-- Flash Messages -->
        <div v-if="flash.success" class="mb-4 px-4 py-3 bg-emerald-900/40 border border-emerald-700 rounded-lg text-emerald-300 text-sm">
            {{ flash.success }}
        </div>

        <!-- Edit Form -->
        <div v-if="showEditForm" class="mb-6 bg-gray-900 border border-gray-700 rounded-xl p-6">
            <h2 class="text-sm font-semibold text-white mb-4">Edit Vocabulary</h2>
            <form @submit.prevent="submitEdit" class="space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs text-gray-400 mb-1">Name</label>
                        <input v-model="editForm.name" type="text" required class="w-full px-3 py-2 bg-gray-800 border border-gray-700 rounded-lg text-white text-sm focus:outline-none focus:border-indigo-500" />
                    </div>
                    <div>
                        <label class="block text-xs text-gray-400 mb-1">Slug</label>
                        <input v-model="editForm.slug" type="text" class="w-full px-3 py-2 bg-gray-800 border border-gray-700 rounded-lg text-white text-sm focus:outline-none focus:border-indigo-500" />
                    </div>
                </div>
                <div>
                    <label class="block text-xs text-gray-400 mb-1">Description</label>
                    <textarea v-model="editForm.description" rows="2" class="w-full px-3 py-2 bg-gray-800 border border-gray-700 rounded-lg text-white text-sm focus:outline-none focus:border-indigo-500"></textarea>
                </div>
                <div class="flex items-center gap-6">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input v-model="editForm.hierarchy" type="checkbox" class="rounded border-gray-600 bg-gray-800 text-indigo-600" />
                        <span class="text-xs text-gray-300">Hierarchical</span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input v-model="editForm.allow_multiple" type="checkbox" class="rounded border-gray-600 bg-gray-800 text-indigo-600" />
                        <span class="text-xs text-gray-300">Allow multiple</span>
                    </label>
                </div>
                <div class="flex gap-3">
                    <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm hover:bg-indigo-500 transition">Save</button>
                    <button type="button" @click="showEditForm = false" class="px-4 py-2 bg-gray-700 text-gray-300 rounded-lg text-sm hover:bg-gray-600 transition">Cancel</button>
                </div>
            </form>
        </div>

        <!-- Add Root Term Form -->
        <div v-if="showAddTerm" class="mb-6">
            <TermForm
                :vocabulary-id="vocabulary.id"
                :parent-id="null"
                @saved="showAddTerm = false"
                @cancel="showAddTerm = false"
            />
        </div>

        <!-- Vocabulary Info -->
        <div class="mb-6 grid grid-cols-3 gap-4">
            <div class="bg-gray-900 rounded-xl border border-gray-800 p-4">
                <p class="text-xs text-gray-500 mb-1">Total Terms</p>
                <p class="text-2xl font-bold text-white">{{ vocabulary.terms_count ?? tree.length }}</p>
            </div>
            <div class="bg-gray-900 rounded-xl border border-gray-800 p-4">
                <p class="text-xs text-gray-500 mb-1">Structure</p>
                <p class="text-sm font-medium text-white">{{ vocabulary.hierarchy ? 'Hierarchical' : 'Flat' }}</p>
            </div>
            <div class="bg-gray-900 rounded-xl border border-gray-800 p-4">
                <p class="text-xs text-gray-500 mb-1">Selection</p>
                <p class="text-sm font-medium text-white">{{ vocabulary.allow_multiple ? 'Multi-select' : 'Single select' }}</p>
            </div>
        </div>

        <!-- Term Tree -->
        <div class="bg-gray-900 rounded-xl border border-gray-800">
            <div class="px-6 py-4 border-b border-gray-800">
                <h2 class="text-sm font-semibold text-white">Terms</h2>
            </div>

            <div v-if="tree.length" class="p-4">
                <TermTree
                    :terms="tree"
                    :vocabulary-id="vocabulary.id"
                    :allow-hierarchy="vocabulary.hierarchy"
                />
            </div>

            <div v-else class="px-6 py-12 text-center">
                <p class="text-gray-600">No terms yet.</p>
                <button @click="showAddTerm = true" class="mt-3 text-sm text-indigo-400 hover:text-indigo-300">
                    Add your first term →
                </button>
            </div>
        </div>
    </div>
</template>
