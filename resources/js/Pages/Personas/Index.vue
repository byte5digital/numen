<script setup>
import { ref, reactive, computed } from 'vue';
import { router, usePage } from '@inertiajs/vue3';

const props = defineProps({
    personas:             { type: Array,  default: () => [] },
    availableModels:      { type: Object, default: () => ({}) },
    availableImageModels: { type: Object, default: () => ({}) },
});

const flash   = computed(() => usePage().props.flash ?? {});
const editing = ref(null);   // persona id being edited
const saving  = ref(false);

const form = reactive({
    model_config: {
        // LLM fields
        model:             '',
        provider:          '',
        fallback_model:    '',
        fallback_provider: '',
        temperature:       0.7,
        max_tokens:        4096,
        // Illustrator / image-generation fields
        prompt_model:       '',
        prompt_provider:    '',
        generator_model:    '',
        generator_provider: '',
        size:               '',
        style:              '',
        quality:            '',
    },
});

const editingPersona = computed(() => props.personas.find(p => p.id === editing.value));
const isIllustrator  = computed(() => editingPersona.value?.role === 'illustrator');

const roleIcon = {
    creator:    '✍',
    optimizer:  '🔍',
    reviewer:   '📋',
    illustrator: '🎨',
    developer:  '💻',
};

function openEdit(persona) {
    editing.value = persona.id;
    const mc = persona.model_config ?? {};
    // LLM fields
    form.model_config.model             = mc.model             ?? '';
    form.model_config.provider          = mc.provider          ?? '';
    form.model_config.fallback_model    = mc.fallback_model    ?? '';
    form.model_config.fallback_provider = mc.fallback_provider ?? '';
    form.model_config.temperature       = mc.temperature       ?? 0.7;
    form.model_config.max_tokens        = mc.max_tokens        ?? 4096;
    // Illustrator fields
    form.model_config.prompt_model       = mc.prompt_model       ?? '';
    form.model_config.prompt_provider    = mc.prompt_provider    ?? '';
    form.model_config.generator_model    = mc.generator_model    ?? '';
    form.model_config.generator_provider = mc.generator_provider ?? '';
    form.model_config.size               = mc.size               ?? '';
    form.model_config.style              = mc.style              ?? '';
    form.model_config.quality            = mc.quality            ?? '';
}

function closeEdit() {
    editing.value = null;
}

function save() {
    saving.value = true;

    let payload;

    if (isIllustrator.value) {
        payload = {
            model_config: {
                prompt_model:       form.model_config.prompt_model,
                prompt_provider:    form.model_config.prompt_provider,
                generator_model:    form.model_config.generator_model,
                generator_provider: form.model_config.generator_provider,
                size:               form.model_config.size,
                style:              form.model_config.style,
                quality:            form.model_config.quality,
            },
        };
    } else {
        payload = {
            model_config: {
                model:             form.model_config.model,
                provider:          form.model_config.provider,
                fallback_model:    form.model_config.fallback_model,
                fallback_provider: form.model_config.fallback_provider,
                temperature:       form.model_config.temperature,
                max_tokens:        form.model_config.max_tokens,
            },
        };
    }

    router.patch(`/admin/personas/${editing.value}`, payload, {
        preserveScroll: true,
        onSuccess: () => { editing.value = null; },
        onFinish:  () => { saving.value = false; },
    });
}
</script>

<template>
    <div>
        <div class="mb-8">
            <h1 class="text-2xl font-bold text-white">AI Personas</h1>
            <p class="text-gray-500 mt-1">The AI agents powering your content pipeline</p>
        </div>

        <!-- Flash -->
        <div v-if="flash.success"
             class="mb-5 px-4 py-3 bg-emerald-500/10 border border-emerald-500/20 rounded-lg text-emerald-400 text-sm">
            {{ flash.success }}
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <div v-for="persona in personas" :key="persona.id"
                 class="bg-gray-900 rounded-xl border border-gray-800 p-6">

                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center gap-3">
                        <span class="text-2xl">{{ roleIcon[persona.role] ?? '🤖' }}</span>
                        <div>
                            <h3 class="font-semibold text-white">{{ persona.name }}</h3>
                            <p class="text-xs text-gray-500 capitalize">{{ persona.role }}</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full"
                              :class="persona.is_active ? 'bg-emerald-400' : 'bg-gray-600'"></span>
                        <button @click="openEdit(persona)"
                                class="text-xs text-gray-500 hover:text-indigo-400 px-2 py-1 rounded border border-transparent hover:border-indigo-500/30 transition">
                            Edit
                        </button>
                    </div>
                </div>

                <!-- Illustrator card: image generation config -->
                <template v-if="persona.role === 'illustrator'">
                    <div class="space-y-3">
                        <div>
                            <p class="text-xs text-gray-500 mb-1">Prompt Model</p>
                            <p class="text-sm text-indigo-400 font-mono">
                                {{ persona.model_config?.prompt_provider ? persona.model_config.prompt_provider + ':' : '' }}{{ persona.model_config?.prompt_model || '—' }}
                            </p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500 mb-1">Image Generator</p>
                            <p class="text-sm text-purple-400 font-mono">
                                {{ persona.model_config?.generator_provider ? persona.model_config.generator_provider + ':' : '' }}{{ persona.model_config?.generator_model || '—' }}
                            </p>
                        </div>
                        <div v-if="persona.model_config?.size || persona.model_config?.style || persona.model_config?.quality">
                            <p class="text-xs text-gray-500 mb-1">Settings</p>
                            <p class="text-xs text-gray-400">
                                {{ [persona.model_config?.size, persona.model_config?.style, persona.model_config?.quality].filter(Boolean).join(' · ') }}
                            </p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500 mb-1">Capabilities</p>
                            <div class="flex flex-wrap gap-1">
                                <span v-for="cap in persona.capabilities" :key="cap"
                                      class="px-2 py-0.5 text-xs bg-gray-800 text-gray-400 rounded">
                                    {{ cap }}
                                </span>
                            </div>
                        </div>
                    </div>
                </template>

                <!-- Default card: LLM config -->
                <template v-else>
                    <div class="space-y-3">
                        <div>
                            <p class="text-xs text-gray-500 mb-1">Primary Model</p>
                            <p class="text-sm text-indigo-400 font-mono">
                                {{ persona.model_config?.provider ? persona.model_config.provider + ':' : '' }}{{ persona.model_config?.model }}
                            </p>
                        </div>
                        <div v-if="persona.model_config?.fallback_model">
                            <p class="text-xs text-gray-500 mb-1">Fallback Model</p>
                            <p class="text-sm text-amber-400 font-mono">
                                {{ persona.model_config?.fallback_provider ? persona.model_config.fallback_provider + ':' : '' }}{{ persona.model_config.fallback_model }}
                            </p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500 mb-1">Temperature</p>
                            <div class="flex items-center gap-2">
                                <div class="flex-1 h-1.5 bg-gray-800 rounded-full overflow-hidden">
                                    <div class="h-full bg-indigo-500 rounded-full"
                                         :style="{ width: `${(persona.model_config?.temperature ?? 0.7) * 100}%` }"></div>
                                </div>
                                <span class="text-xs text-gray-400">{{ persona.model_config?.temperature }}</span>
                            </div>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500 mb-1">Capabilities</p>
                            <div class="flex flex-wrap gap-1">
                                <span v-for="cap in persona.capabilities" :key="cap"
                                      class="px-2 py-0.5 text-xs bg-gray-800 text-gray-400 rounded">
                                    {{ cap }}
                                </span>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        <!-- Edit Modal -->
        <div v-if="editing"
             class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4"
             @click.self="closeEdit">
            <div class="bg-gray-900 rounded-2xl border border-gray-700 p-6 w-full max-w-lg shadow-2xl">
                <h2 class="text-base font-semibold text-white mb-5">Edit Persona Model Config</h2>

                <!-- Illustrator: image generation config -->
                <template v-if="isIllustrator">
                    <div class="space-y-4">

                        <!-- Prompt Model (LLM that writes the prompt) -->
                        <div>
                            <p class="text-xs text-gray-500 mb-3">
                                Prompt Model — the LLM used to write the image generation prompt.
                            </p>
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-xs text-gray-400 mb-1.5">Prompt Provider</label>
                                    <select v-model="form.model_config.prompt_provider"
                                            class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-indigo-500">
                                        <option value="">Auto-detect</option>
                                        <option value="anthropic">Anthropic</option>
                                        <option value="openai">OpenAI</option>
                                        <option value="azure">Azure</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-400 mb-1.5">Prompt Model</label>
                                    <select v-model="form.model_config.prompt_model"
                                            class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-indigo-500">
                                        <option value="">Select model</option>
                                        <optgroup v-for="(models, provider) in availableModels" :key="provider"
                                                  :label="provider.charAt(0).toUpperCase() + provider.slice(1)">
                                            <option v-for="m in models" :key="m" :value="m">{{ m }}</option>
                                        </optgroup>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Image Generator -->
                        <div class="pt-3 border-t border-gray-800">
                            <p class="text-xs text-gray-500 mb-3">
                                Image Generator — the image API used to generate the actual image.
                            </p>
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-xs text-gray-400 mb-1.5">Generator Provider</label>
                                    <select v-model="form.model_config.generator_provider"
                                            class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-indigo-500">
                                        <option value="">Select provider</option>
                                        <option value="openai">OpenAI</option>
                                        <option value="together">Together AI</option>
                                        <option value="fal">fal.ai</option>
                                        <option value="replicate">Replicate</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-400 mb-1.5">Generator Model</label>
                                    <select v-model="form.model_config.generator_model"
                                            class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-indigo-500">
                                        <option value="">Select model</option>
                                        <template v-if="form.model_config.generator_provider && availableImageModels[form.model_config.generator_provider]">
                                            <option v-for="m in availableImageModels[form.model_config.generator_provider]"
                                                    :key="m" :value="m">{{ m }}</option>
                                        </template>
                                        <template v-else>
                                            <optgroup v-for="(models, provider) in availableImageModels" :key="provider"
                                                      :label="provider.charAt(0).toUpperCase() + provider.slice(1)">
                                                <option v-for="m in models" :key="m" :value="m">{{ m }}</option>
                                            </optgroup>
                                        </template>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Size / Style / Quality -->
                        <div class="grid grid-cols-3 gap-3 pt-3 border-t border-gray-800">
                            <div>
                                <label class="block text-xs text-gray-400 mb-1.5">Size</label>
                                <select v-model="form.model_config.size"
                                        class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-indigo-500">
                                    <option value="">Default</option>
                                    <option value="1024x1024">1024×1024</option>
                                    <option value="1024x1536">1024×1536</option>
                                    <option value="1536x1024">1536×1024</option>
                                    <option value="1792x1024">1792×1024</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs text-gray-400 mb-1.5">Style</label>
                                <select v-model="form.model_config.style"
                                        class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-indigo-500">
                                    <option value="">Default</option>
                                    <option value="vivid">Vivid</option>
                                    <option value="natural">Natural</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs text-gray-400 mb-1.5">Quality</label>
                                <select v-model="form.model_config.quality"
                                        class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-indigo-500">
                                    <option value="">Default</option>
                                    <option value="standard">Standard</option>
                                    <option value="hd">HD</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </template>

                <!-- Default: LLM model config -->
                <template v-else>
                    <div class="space-y-4">

                        <!-- Primary model -->
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs text-gray-400 mb-1.5">Primary Provider</label>
                                <select v-model="form.model_config.provider"
                                        class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-indigo-500">
                                    <option value="">Auto-detect</option>
                                    <option value="anthropic">Anthropic</option>
                                    <option value="openai">OpenAI</option>
                                    <option value="azure">Azure</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs text-gray-400 mb-1.5">Primary Model</label>
                                <select v-model="form.model_config.model"
                                        class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-indigo-500">
                                    <optgroup v-for="(models, provider) in availableModels" :key="provider"
                                              :label="provider.charAt(0).toUpperCase() + provider.slice(1)">
                                        <option v-for="m in models" :key="m" :value="m">{{ m }}</option>
                                    </optgroup>
                                </select>
                            </div>
                        </div>

                        <!-- Fallback model -->
                        <div class="pt-3 border-t border-gray-800">
                            <p class="text-xs text-gray-500 mb-3">
                                Fallback model — used when the primary model's provider is unavailable or rate-limited.
                            </p>
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-xs text-gray-400 mb-1.5">Fallback Provider</label>
                                    <select v-model="form.model_config.fallback_provider"
                                            class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-indigo-500">
                                        <option value="">None</option>
                                        <option value="anthropic">Anthropic</option>
                                        <option value="openai">OpenAI</option>
                                        <option value="azure">Azure</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-400 mb-1.5">Fallback Model</label>
                                    <select v-model="form.model_config.fallback_model"
                                            class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-indigo-500">
                                        <option value="">None</option>
                                        <optgroup v-for="(models, provider) in availableModels" :key="provider"
                                                  :label="provider.charAt(0).toUpperCase() + provider.slice(1)">
                                            <option v-for="m in models" :key="m" :value="m">{{ m }}</option>
                                        </optgroup>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Temperature + max tokens -->
                        <div class="grid grid-cols-2 gap-3 pt-3 border-t border-gray-800">
                            <div>
                                <label class="block text-xs text-gray-400 mb-1.5">
                                    Temperature
                                    <span class="text-gray-600 ml-1">{{ form.model_config.temperature }}</span>
                                </label>
                                <input type="range" min="0" max="2" step="0.05"
                                       v-model.number="form.model_config.temperature"
                                       class="w-full accent-indigo-500" />
                            </div>
                            <div>
                                <label class="block text-xs text-gray-400 mb-1.5">Max Tokens</label>
                                <input type="number" min="256" max="32768" step="256"
                                       v-model.number="form.model_config.max_tokens"
                                       class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-indigo-500" />
                            </div>
                        </div>
                    </div>
                </template>

                <div class="flex justify-end gap-3 mt-6">
                    <button @click="closeEdit"
                            class="px-4 py-2 text-sm text-gray-400 hover:text-white transition">
                        Cancel
                    </button>
                    <button @click="save" :disabled="saving"
                            class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 disabled:opacity-50 text-white text-sm font-medium rounded-lg transition">
                        {{ saving ? 'Saving…' : 'Save Changes' }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
