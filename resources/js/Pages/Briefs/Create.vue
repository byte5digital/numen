<script setup>
import { useForm } from '@inertiajs/vue3';

const props = defineProps({
    contentTypes: Array,
    personas: Array,
    spaceId: String,
});

const form = useForm({
    space_id: props.spaceId,
    title: '',
    description: '',
    content_type_slug: 'blog_post',
    target_keywords: '',
    target_locale: 'en',
    persona_id: '',
    priority: 'normal',
});

function submit() {
    form.transform((data) => ({
        ...data,
        target_keywords: data.target_keywords
            ? data.target_keywords.split(',').map(k => k.trim())
            : [],
    })).post('/admin/briefs');
}
</script>

<template>
    <div class="max-w-2xl">
        <div class="mb-8">
            <h1 class="text-2xl font-bold text-white">Create Content Brief</h1>
            <p class="text-gray-500 mt-1">Submit a brief and let AI handle the rest</p>
        </div>

        <form @submit.prevent="submit" class="space-y-6">
            <div class="bg-gray-900 rounded-xl border border-gray-800 p-6 space-y-5">
                <!-- Title -->
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-1">Title / Topic *</label>
                    <input
                        v-model="form.title"
                        type="text"
                        class="w-full bg-gray-800 border border-gray-700 rounded-lg px-4 py-2.5 text-gray-200 placeholder-gray-600 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500"
                        placeholder="e.g., Why AI-First CMS Changes Everything"
                        required
                    />
                    <p v-if="form.errors.title" class="mt-1 text-xs text-red-400">{{ form.errors.title }}</p>
                </div>

                <!-- Description -->
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-1">Description</label>
                    <textarea
                        v-model="form.description"
                        rows="3"
                        class="w-full bg-gray-800 border border-gray-700 rounded-lg px-4 py-2.5 text-gray-200 placeholder-gray-600 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500"
                        placeholder="Brief description of what the content should cover..."
                    ></textarea>
                </div>

                <!-- Content Type -->
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-1">Content Type</label>
                    <select
                        v-model="form.content_type_slug"
                        class="w-full bg-gray-800 border border-gray-700 rounded-lg px-4 py-2.5 text-gray-200 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500"
                    >
                        <option v-for="type in contentTypes" :key="type.slug" :value="type.slug">
                            {{ type.name }}
                        </option>
                    </select>
                </div>

                <!-- Keywords -->
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-1">Target Keywords</label>
                    <input
                        v-model="form.target_keywords"
                        type="text"
                        class="w-full bg-gray-800 border border-gray-700 rounded-lg px-4 py-2.5 text-gray-200 placeholder-gray-600 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500"
                        placeholder="ai cms, headless cms, ai content (comma-separated)"
                    />
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <!-- Locale -->
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-1">Locale</label>
                        <select
                            v-model="form.target_locale"
                            class="w-full bg-gray-800 border border-gray-700 rounded-lg px-4 py-2.5 text-gray-200 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500"
                        >
                            <option value="en">English</option>
                            <option value="de">Deutsch</option>
                            <option value="fr">Français</option>
                            <option value="es">Español</option>
                        </select>
                    </div>

                    <!-- Priority -->
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-1">Priority</label>
                        <select
                            v-model="form.priority"
                            class="w-full bg-gray-800 border border-gray-700 rounded-lg px-4 py-2.5 text-gray-200 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500"
                        >
                            <option value="low">Low</option>
                            <option value="normal">Normal</option>
                            <option value="high">High</option>
                            <option value="urgent">Urgent</option>
                        </select>
                    </div>
                </div>

                <!-- Persona -->
                <div v-if="personas?.length">
                    <label class="block text-sm font-medium text-gray-300 mb-1">Persona (optional)</label>
                    <select
                        v-model="form.persona_id"
                        class="w-full bg-gray-800 border border-gray-700 rounded-lg px-4 py-2.5 text-gray-200 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500"
                    >
                        <option value="">Auto-select based on pipeline</option>
                        <option v-for="p in personas" :key="p.id" :value="p.id">
                            {{ p.name }} ({{ p.role }})
                        </option>
                    </select>
                </div>
            </div>

            <div class="flex items-center gap-4">
                <button
                    type="submit"
                    class="px-6 py-2.5 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-500 transition disabled:opacity-50"
                    :disabled="form.processing"
                >
                    {{ form.processing ? 'Starting Pipeline...' : '⚡ Create Brief & Start Pipeline' }}
                </button>
                <span v-if="form.processing" class="text-xs text-gray-500">AI is warming up...</span>
            </div>
        </form>
    </div>
</template>
