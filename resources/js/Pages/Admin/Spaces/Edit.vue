<script setup lang="ts">
import { Head, useForm, router } from '@inertiajs/vue3'
import { ref } from 'vue'
import MainLayout from '../../../Layouts/MainLayout.vue'

interface Space {
    id: string
    name: string
    slug: string
    description: string | null
    default_locale: string
    settings: Record<string, unknown> | null
    api_config: Record<string, unknown> | null
}

const props = defineProps<{
    space: Space
}>()

const activeTab = ref<'general' | 'ai_providers' | 'settings' | 'danger'>('general')

const form = useForm({
    name: props.space.name,
    slug: props.space.slug,
    description: props.space.description ?? '',
    default_locale: props.space.default_locale,
    settings: props.space.settings ?? {},
    api_config: {
        openai_api_key: (props.space.api_config?.openai_api_key as string) ?? '',
        anthropic_api_key: (props.space.api_config?.anthropic_api_key as string) ?? '',
        azure_openai_key: (props.space.api_config?.azure_openai_key as string) ?? '',
        azure_openai_endpoint: (props.space.api_config?.azure_openai_endpoint as string) ?? '',
        together_api_key: (props.space.api_config?.together_api_key as string) ?? '',
        fal_api_key: (props.space.api_config?.fal_api_key as string) ?? '',
        replicate_api_key: (props.space.api_config?.replicate_api_key as string) ?? '',
    },
})

const deleteConfirmText = ref('')
const deleteError = ref('')

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

function submit() {
    form.put(`/admin/spaces/${props.space.id}`)
}

function confirmDelete() {
    if (deleteConfirmText.value !== props.space.name) {
        deleteError.value = 'Space name does not match.'
        return
    }
    router.delete(`/admin/spaces/${props.space.id}`)
}
</script>

<template>
    <MainLayout>
        <Head :title="`Edit Space: ${space.name}`" />
        <div class="max-w-3xl mx-auto">
            <div class="mb-6">
                <a href="/admin/spaces" class="text-sm text-gray-400 hover:text-white transition-colors">Back to Spaces</a>
            </div>
            <h1 class="text-2xl font-bold text-white mb-6">Edit Space: {{ space.name }}</h1>
            <div class="flex gap-1 mb-6 border-b border-gray-800">
                <button v-for="tab in [{key:'general',label:'General'},{key:'ai_providers',label:'AI Providers'},{key:'settings',label:'Settings'},{key:'danger',label:'Danger Zone'}]"
                    :key="tab.key" @click="(activeTab as string) = tab.key"
                    class="px-4 py-2.5 text-sm font-medium transition-colors -mb-px border-b-2"
                    :class="activeTab === tab.key ? 'text-indigo-400 border-indigo-400' : 'text-gray-400 border-transparent hover:text-white'">
                    {{ tab.label }}
                </button>
            </div>
            <form @submit.prevent="submit" class="bg-gray-900 rounded-xl border border-gray-800 p-6">
                <div v-if="activeTab === 'general'" class="space-y-5">
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-1.5">Name</label>
                        <input v-model="form.name" type="text" class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2.5 text-white focus:outline-none focus:border-indigo-500" :class="{ 'border-red-500': form.errors.name }" />
                        <p v-if="form.errors.name" class="mt-1 text-xs text-red-400">{{ form.errors.name }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-1.5">Slug</label>
                        <input v-model="form.slug" type="text" class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2.5 text-white font-mono text-sm focus:outline-none focus:border-indigo-500" :class="{ 'border-red-500': form.errors.slug }" />
                        <p v-if="form.errors.slug" class="mt-1 text-xs text-red-400">{{ form.errors.slug }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-1.5">Description</label>
                        <textarea v-model="form.description" rows="3" class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2.5 text-white focus:outline-none focus:border-indigo-500 resize-none" />
                    </div>
                    <div class="flex justify-end pt-2">
                        <button type="submit" :disabled="form.processing" class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white text-sm font-medium rounded-lg">
                            {{ form.processing ? 'Saving...' : 'Save Changes' }}
                        </button>
                    </div>
                </div>
                <div v-if="activeTab === 'ai_providers'" class="space-y-5">
                    <p class="text-sm text-gray-400 mb-4">Configure AI provider API keys for this space.</p>
                    <div v-for="field in [{key:'openai_api_key',label:'OpenAI API Key'},{key:'anthropic_api_key',label:'Anthropic API Key'},{key:'azure_openai_key',label:'Azure OpenAI Key'},{key:'azure_openai_endpoint',label:'Azure Endpoint'},{key:'together_api_key',label:'Together AI Key'},{key:'fal_api_key',label:'fal.ai API Key'},{key:'replicate_api_key',label:'Replicate API Key'}]" :key="field.key">
                        <label class="block text-sm font-medium text-gray-300 mb-1.5">{{ field.label }}</label>
                        <input v-model="(form.api_config as Record<string,string>)[field.key]" type="password" autocomplete="new-password" class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2.5 text-white font-mono text-sm focus:outline-none focus:border-indigo-500" />
                    </div>
                    <div class="flex justify-end pt-2">
                        <button type="submit" :disabled="form.processing" class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white text-sm font-medium rounded-lg">
                            {{ form.processing ? 'Saving...' : 'Save API Keys' }}
                        </button>
                    </div>
                </div>
                <div v-if="activeTab === 'settings'" class="space-y-5">
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-1.5">Default Locale</label>
                        <select v-model="form.default_locale" class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2.5 text-white focus:outline-none focus:border-indigo-500">
                            <option v-for="locale in locales" :key="locale.value" :value="locale.value">{{ locale.label }}</option>
                        </select>
                    </div>
                    <div class="flex justify-end pt-2">
                        <button type="submit" :disabled="form.processing" class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white text-sm font-medium rounded-lg">
                            {{ form.processing ? 'Saving...' : 'Save Settings' }}
                        </button>
                    </div>
                </div>
            </form>
            <div v-if="activeTab === 'danger'" class="bg-gray-900 rounded-xl border border-red-900/50 p-6 mt-4">
                <h3 class="text-lg font-semibold text-red-400 mb-2">Danger Zone</h3>
                <p class="text-sm text-gray-400 mb-4">
                    Permanently delete this space. Type <strong class="text-white">{{ space.name }}</strong> to confirm.
                </p>
                <div class="space-y-3">
                    <input v-model="deleteConfirmText" type="text" :placeholder="`Type to confirm`" class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2.5 text-white focus:outline-none focus:border-red-500" :class="{ 'border-red-500': deleteError }" />
                    <p v-if="deleteError" class="text-xs text-red-400">{{ deleteError }}</p>
                    <button @click="confirmDelete" class="px-5 py-2.5 bg-red-600 hover:bg-red-700 text-white text-sm font-medium rounded-lg">Delete Space</button>
                </div>
            </div>
        </div>
    </MainLayout>
</template>
