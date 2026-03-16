<script setup>
import { reactive, ref } from 'vue';
import axios from 'axios';

const props = defineProps({
    spaceId: { type: String, required: true },
    config: { type: Object, default: null },
});

const emit = defineEmits(['saved']);

const ALL_DIMENSIONS = ['readability', 'seo', 'brand_consistency', 'factual_accuracy', 'engagement_prediction'];
const DIM_LABELS = {
    readability: 'Readability',
    seo: 'SEO',
    brand_consistency: 'Brand Consistency',
    factual_accuracy: 'Factual Accuracy',
    engagement_prediction: 'Engagement Prediction',
};

const form = reactive({
    enabled_dimensions: props.config?.enabled_dimensions ?? [...ALL_DIMENSIONS],
    auto_score_on_publish: props.config?.auto_score_on_publish ?? true,
    pipeline_gate_enabled: props.config?.pipeline_gate_enabled ?? false,
    pipeline_gate_min_score: props.config?.pipeline_gate_min_score ?? 70,
    dimension_weights: props.config?.dimension_weights ?? {
        readability: 0.25,
        seo: 0.25,
        brand_consistency: 0.20,
        factual_accuracy: 0.15,
        engagement_prediction: 0.15,
    },
    thresholds: props.config?.thresholds ?? {
        poor: 40,
        fair: 60,
        good: 75,
        excellent: 90,
    },
});

const saving = ref(false);
const savedMsg = ref(null);
const errorMsg = ref(null);

function toggleDimension(dim) {
    const idx = form.enabled_dimensions.indexOf(dim);
    if (idx === -1) {
        form.enabled_dimensions.push(dim);
    } else {
        form.enabled_dimensions.splice(idx, 1);
    }
}

async function save() {
    saving.value = true;
    savedMsg.value = null;
    errorMsg.value = null;
    try {
        const res = await axios.put('/api/v1/quality/config', {
            space_id: props.spaceId,
            ...form,
        });
        savedMsg.value = 'Configuration saved.';
        emit('saved', res.data.data);
        setTimeout(() => (savedMsg.value = null), 3000);
    } catch (e) {
        errorMsg.value = e.response?.data?.message ?? 'Save failed.';
    } finally {
        saving.value = false;
    }
}
</script>

<template>
    <div class="rounded-lg border border-gray-700 bg-gray-800 p-5">
        <h2 class="mb-4 text-base font-semibold text-white">Quality Scoring Configuration</h2>

        <!-- Enabled dimensions -->
        <div class="mb-5">
            <label class="mb-2 block text-xs font-medium text-gray-400">Enabled Dimensions</label>
            <div class="flex flex-wrap gap-2">
                <button
                    v-for="dim in ALL_DIMENSIONS"
                    :key="dim"
                    type="button"
                    class="rounded-full border px-3 py-1 text-xs transition-colors"
                    :class="form.enabled_dimensions.includes(dim)
                        ? 'border-indigo-500 bg-indigo-600/20 text-indigo-300'
                        : 'border-gray-600 bg-gray-700 text-gray-400 hover:border-gray-500'"
                    @click="toggleDimension(dim)"
                >
                    {{ DIM_LABELS[dim] }}
                </button>
            </div>
        </div>

        <!-- Dimension weights -->
        <div class="mb-5">
            <label class="mb-2 block text-xs font-medium text-gray-400">Dimension Weights</label>
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <div v-for="dim in ALL_DIMENSIONS" :key="dim" class="flex items-center gap-3">
                    <span class="w-36 shrink-0 text-xs text-gray-300">{{ DIM_LABELS[dim] }}</span>
                    <input
                        v-model.number="form.dimension_weights[dim]"
                        type="number"
                        min="0"
                        max="1"
                        step="0.05"
                        class="w-20 rounded border border-gray-600 bg-gray-700 px-2 py-1 text-xs text-white"
                    />
                    <span class="text-xs text-gray-500">{{ Math.round((form.dimension_weights[dim] ?? 0) * 100) }}%</span>
                </div>
            </div>
        </div>

        <!-- Thresholds -->
        <div class="mb-5">
            <label class="mb-2 block text-xs font-medium text-gray-400">Score Thresholds</label>
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                <div v-for="tier in ['poor', 'fair', 'good', 'excellent']" :key="tier" class="flex flex-col gap-1">
                    <label class="text-xs capitalize text-gray-400">{{ tier }}</label>
                    <input
                        v-model.number="form.thresholds[tier]"
                        type="number"
                        min="0"
                        max="100"
                        step="5"
                        class="rounded border border-gray-600 bg-gray-700 px-2 py-1 text-xs text-white"
                    />
                </div>
            </div>
        </div>

        <!-- Auto-score toggle -->
        <div class="mb-4 flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-200">Auto-score on publish</p>
                <p class="text-xs text-gray-500">Automatically score content when published</p>
            </div>
            <button
                type="button"
                class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none"
                :class="form.auto_score_on_publish ? 'bg-indigo-600' : 'bg-gray-600'"
                @click="form.auto_score_on_publish = !form.auto_score_on_publish"
            >
                <span
                    class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"
                    :class="form.auto_score_on_publish ? 'translate-x-5' : 'translate-x-0'"
                />
            </button>
        </div>

        <!-- Pipeline gate -->
        <div class="mb-4 rounded-lg border border-gray-700 p-4">
            <div class="mb-3 flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-200">Pipeline quality gate</p>
                    <p class="text-xs text-gray-500">Block pipeline stages if score is too low</p>
                </div>
                <button
                    type="button"
                    class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out"
                    :class="form.pipeline_gate_enabled ? 'bg-indigo-600' : 'bg-gray-600'"
                    @click="form.pipeline_gate_enabled = !form.pipeline_gate_enabled"
                >
                    <span
                        class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"
                        :class="form.pipeline_gate_enabled ? 'translate-x-5' : 'translate-x-0'"
                    />
                </button>
            </div>

            <div v-if="form.pipeline_gate_enabled" class="flex items-center gap-3">
                <label class="text-xs text-gray-400">Minimum score:</label>
                <input
                    v-model.number="form.pipeline_gate_min_score"
                    type="number"
                    min="0"
                    max="100"
                    step="5"
                    class="w-20 rounded border border-gray-600 bg-gray-700 px-2 py-1 text-xs text-white"
                />
                <span class="text-xs text-gray-500">/ 100</span>
            </div>
        </div>

        <!-- Save -->
        <div class="flex items-center gap-4">
            <button
                type="button"
                class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50"
                :disabled="saving"
                @click="save"
            >
                {{ saving ? 'Saving…' : 'Save Configuration' }}
            </button>
            <span v-if="savedMsg" class="text-xs text-emerald-400">{{ savedMsg }}</span>
            <span v-if="errorMsg" class="text-xs text-red-400">{{ errorMsg }}</span>
        </div>
    </div>
</template>
