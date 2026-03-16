<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3'
import { watch } from 'vue'
import MainLayout from '../../../Layouts/MainLayout.vue'

const form = useForm({
    name: '',
    slug: '',
    description: '',
    default_locale: 'en',
})

const locales = [
    { value: 'en', label: 'English (en)' },
    { value: 'de', label: 'German (de)' },
    { value: 'fr', label: 'French (fr)' },
    { value: 'es', label: 'Spanish (es)' },
    { value: 'it', label: 'Italian (it)' },
    { value: 'pt', label: 'Portuguese (pt)' },
    { value: 'nl', label: 'Dutch (nl)' },
    { value: 'pl', label: 'Polish (pl)' },
    { value: 'ja', label: 'Japanese (ja)' },
    { value: 'zh', label: 'Chinese (zh)' },
]

// Auto-slugify name
watch(() => form.name, (value) => {
    form.slug = value
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '')
})

function submit() {
    form.post('/admin/spaces')
}
</script>

<template>
    <MainLayout>
        <Head title="Create Space" />

        <div class="max-w-2xl mx-auto">
            <div class="mb-6">
                <a href="/admin/spaces" class="text-sm text-gray-400 hover:text-white transition-colors">← Back to Spaces</a>
            </div>

            <h1 class="text-2xl font-bold text-white mb-6">Create Space</h1>

            <form @submit.prevent="submit" class="bg-gray-900 rounded-xl border border-gray-800 p-6 space-y-5">
                <!-- Name -->
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-1.5">Name</label>
                    <input
                        v-model="form.name"
                        type="text"
                        placeholder="My Space"
                        class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2.5 text-white placeholder-gray-500 focus:outline-none focus:border-indigo-500 transition-colors"
                        :class="{ 'border-red-500': form.errors.name }"
                    />
                    <p v-if="form.errors.name" class="mt-1 text-xs text-red-400">{{ form.errors.name }}</p>
                </div>

                <!-- Slug -->
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-1.5">Slug</label>
                    <input
                        v-model="form.slug"
                        type="text"
                        placeholder="my-space"
                        class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2.5 text-white placeholder-gray-500 focus:outline-none focus:border-indigo-500 transition-colors font-mono text-sm"
                        :class="{ 'border-red-500': form.errors.slug }"
                    />
                    <p v-if="form.errors.slug" class="mt-1 text-xs text-red-400">{{ form.errors.slug }}</p>
                    <p class="mt-1 text-xs text-gray-500">Auto-generated from name. Only letters, numbers, and dashes.</p>
                </div>

                <!-- Description -->
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-1.5">Description <span class="text-gray-500">(optional)</span></label>
                    <textarea
                        v-model="form.description"
                        rows="3"
                        placeholder="Brief description of this space..."
                        class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2.5 text-white placeholder-gray-500 focus:outline-none focus:border-indigo-500 transition-colors resize-none"
                        :class="{ 'border-red-500': form.errors.description }"
                    />
                    <p v-if="form.errors.description" class="mt-1 text-xs text-red-400">{{ form.errors.description }}</p>
                </div>

                <!-- Default Locale -->
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-1.5">Default Locale</label>
                    <select
                        v-model="form.default_locale"
                        class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2.5 text-white focus:outline-none focus:border-indigo-500 transition-colors"
                        :class="{ 'border-red-500': form.errors.default_locale }"
                    >
                        <option v-for="locale in locales" :key="locale.value" :value="locale.value">
                            {{ locale.label }}
                        </option>
                    </select>
                    <p v-if="form.errors.default_locale" class="mt-1 text-xs text-red-400">{{ form.errors.default_locale }}</p>
                </div>

                <div class="flex justify-end pt-2">
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white text-sm font-medium rounded-lg transition-colors"
                    >
                        {{ form.processing ? 'Creating...' : 'Create Space' }}
                    </button>
                </div>
            </form>
        </div>
    </MainLayout>
</template>
