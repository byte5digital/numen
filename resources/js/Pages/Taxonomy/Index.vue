<script setup>
import { ref, computed } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';

const props = defineProps({
    vocabularies: { type: Array, default: () => [] },
    spaceId: { type: String, default: null },
});

const flash = computed(() => usePage().props.flash ?? {});

const showCreateForm = ref(false);

const form = ref({
    space_id: props.spaceId ?? '',
    name: '',
    slug: '',
    description: '',
    hierarchy: true,
    allow_multiple: true,
    sort_order: 0,
});

function submitCreate() {
    router.post('/admin/taxonomy', form.value, {
        onSuccess: () => {
            showCreateForm.value = false;
            form.value = { ...form.value, name: '', slug: '', description: '' };
        },
    });
}

function deleteVocabulary(id, name) {
    if (confirm(`Delete vocabulary "${name}" and ALL its terms? This cannot be undone.`)) {
        router.delete(`/admin/taxonomy/${id}`);
    }
}
</script>

<template>
    <div>
        <div class="flex items-center justify-between mb-8">
            <div>
                <h1 class="text-2xl font-bold text-white">Taxonomy</h1>
                <p class="text-gray-500 mt-1">Organize content with vocabularies and terms</p>
            </div>
            <button
                @click="showCreateForm = !showCreateForm"
                class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-500 transition"
            >
                + New Vocabulary
            </button>
        </div>

        <!-- Flash Messages -->
        <div v-if="flash.success" class="mb-4 px-4 py-3 bg-emerald-900/40 border border-emerald-700 rounded-lg text-emerald-300 text-sm">
            {{ flash.success }}
        </div>
        <div v-if="flash.error" class="mb-4 px-4 py-3 bg-red-900/40 border border-red-700 rounded-lg text-red-300 text-sm">
            {{ flash.error }}
        </div>

        <!-- Create Form -->
        <div v-if="showCreateForm" class="mb-6 bg-gray-900 border border-gray-700 rounded-xl p-6">
            <h2 class="text-sm font-semibold text-white mb-4">Create Vocabulary</h2>
            <form @submit.prevent="submitCreate" class="space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs text-gray-400 mb-1">Name *</label>
                        <input
                            v-model="form.name"
                            type="text"
                            required
                            class="w-full px-3 py-2 bg-gray-800 border border-gray-700 rounded-lg text-white text-sm focus:outline-none focus:border-indigo-500"
                            placeholder="e.g. Categories"
                        />
                    </div>
                    <div>
                        <label class="block text-xs text-gray-400 mb-1">Slug (auto-generated if empty)</label>
                        <input
                            v-model="form.slug"
                            type="text"
                            class="w-full px-3 py-2 bg-gray-800 border border-gray-700 rounded-lg text-white text-sm focus:outline-none focus:border-indigo-500"
                            placeholder="e.g. categories"
                        />
                    </div>
                </div>
                <div>
                    <label class="block text-xs text-gray-400 mb-1">Description</label>
                    <textarea
                        v-model="form.description"
                        rows="2"
                        class="w-full px-3 py-2 bg-gray-800 border border-gray-700 rounded-lg text-white text-sm focus:outline-none focus:border-indigo-500"
                        placeholder="Optional description"
                    ></textarea>
                </div>
                <div class="flex items-center gap-6">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input v-model="form.hierarchy" type="checkbox" class="rounded border-gray-600 bg-gray-800 text-indigo-600" />
                        <span class="text-xs text-gray-300">Hierarchical (nested terms)</span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input v-model="form.allow_multiple" type="checkbox" class="rounded border-gray-600 bg-gray-800 text-indigo-600" />
                        <span class="text-xs text-gray-300">Allow multiple terms per content</span>
                    </label>
                </div>
                <div class="flex gap-3">
                    <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm hover:bg-indigo-500 transition">
                        Create
                    </button>
                    <button type="button" @click="showCreateForm = false" class="px-4 py-2 bg-gray-700 text-gray-300 rounded-lg text-sm hover:bg-gray-600 transition">
                        Cancel
                    </button>
                </div>
            </form>
        </div>

        <!-- Vocabularies List -->
        <div class="bg-gray-900 rounded-xl border border-gray-800 overflow-hidden">
            <table class="w-full">
                <thead class="border-b border-gray-800">
                    <tr>
                        <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">Vocabulary</th>
                        <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">Slug</th>
                        <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">Terms</th>
                        <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">Options</th>
                        <th class="px-6 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-800">
                    <tr v-for="vocab in vocabularies" :key="vocab.id" class="hover:bg-gray-800/50">
                        <td class="px-6 py-4">
                            <Link :href="`/admin/taxonomy/${vocab.id}`" class="text-sm font-medium text-gray-200 hover:text-indigo-400">
                                {{ vocab.name }}
                            </Link>
                            <p v-if="vocab.description" class="text-xs text-gray-600 mt-0.5 truncate max-w-xs">{{ vocab.description }}</p>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-500 font-mono">{{ vocab.slug }}</td>
                        <td class="px-6 py-4 text-sm text-gray-400">{{ vocab.terms_count ?? 0 }}</td>
                        <td class="px-6 py-4">
                            <div class="flex gap-2">
                                <span v-if="vocab.hierarchy" class="px-2 py-0.5 text-xs bg-indigo-900/40 text-indigo-400 rounded-full">Hierarchical</span>
                                <span v-if="vocab.allow_multiple" class="px-2 py-0.5 text-xs bg-gray-800 text-gray-400 rounded-full">Multi-select</span>
                            </div>
                        </td>
                        <td class="px-6 py-4 text-right">
                            <Link :href="`/admin/taxonomy/${vocab.id}`" class="text-xs text-indigo-500 hover:text-indigo-300 mr-3">Manage</Link>
                            <button @click="deleteVocabulary(vocab.id, vocab.name)" class="text-xs text-red-500 hover:text-red-400">Delete</button>
                        </td>
                    </tr>
                </tbody>
            </table>

            <div v-if="!vocabularies?.length" class="px-6 py-12 text-center">
                <p class="text-gray-600">No vocabularies yet. Create one to start organizing your content.</p>
                <button @click="showCreateForm = true" class="mt-3 text-sm text-indigo-400 hover:text-indigo-300">
                    Create your first vocabulary →
                </button>
            </div>
        </div>
    </div>
</template>
