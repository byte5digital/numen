<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({
    vocabularyId: { type: String, required: true },
    parentId: { type: String, default: null },
    term: { type: Object, default: null }, // if editing
});

const emit = defineEmits(['saved', 'cancel']);

const isEditing = !!props.term;

const form = ref({
    name: props.term?.name ?? '',
    slug: props.term?.slug ?? '',
    description: props.term?.description ?? '',
    parent_id: props.term?.parent_id ?? props.parentId ?? null,
    sort_order: props.term?.sort_order ?? 0,
});

function submit() {
    if (isEditing) {
        router.patch(`/admin/taxonomy/terms/${props.term.id}`, form.value, {
            preserveScroll: true,
            onSuccess: () => emit('saved'),
        });
    } else {
        router.post(`/admin/taxonomy/${props.vocabularyId}/terms`, form.value, {
            preserveScroll: true,
            onSuccess: () => emit('saved'),
        });
    }
}
</script>

<template>
    <div class="bg-gray-800 border border-gray-700 rounded-lg p-4">
        <h3 class="text-xs font-semibold text-gray-300 mb-3">{{ isEditing ? 'Edit Term' : 'New Term' }}</h3>
        <form @submit.prevent="submit" class="space-y-3">
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Name *</label>
                    <input
                        v-model="form.name"
                        type="text"
                        required
                        class="w-full px-3 py-1.5 bg-gray-900 border border-gray-700 rounded text-white text-sm focus:outline-none focus:border-indigo-500"
                        placeholder="Term name"
                    />
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Slug</label>
                    <input
                        v-model="form.slug"
                        type="text"
                        class="w-full px-3 py-1.5 bg-gray-900 border border-gray-700 rounded text-white text-sm focus:outline-none focus:border-indigo-500"
                        placeholder="auto-generated"
                    />
                </div>
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">Description</label>
                <input
                    v-model="form.description"
                    type="text"
                    class="w-full px-3 py-1.5 bg-gray-900 border border-gray-700 rounded text-white text-sm focus:outline-none focus:border-indigo-500"
                    placeholder="Optional"
                />
            </div>
            <div class="flex gap-2">
                <button type="submit" class="px-3 py-1.5 bg-indigo-600 text-white rounded text-xs hover:bg-indigo-500 transition">
                    {{ isEditing ? 'Save' : 'Create' }}
                </button>
                <button type="button" @click="emit('cancel')" class="px-3 py-1.5 bg-gray-700 text-gray-300 rounded text-xs hover:bg-gray-600 transition">
                    Cancel
                </button>
            </div>
        </form>
    </div>
</template>
