<script setup>
import { ref, computed, onMounted } from 'vue';
import { usePage } from '@inertiajs/vue3';
import ConnectStep from './Steps/ConnectStep.vue';
import MapStep from './Steps/MapStep.vue';
import PreviewStep from './Steps/PreviewStep.vue';
import ImportStep from './Steps/ImportStep.vue';
import CompleteStep from './Steps/CompleteStep.vue';

const props = defineProps({
    session: { type: Object, default: null },
});

const page = usePage();
const currentSpace = computed(() => page.props.currentSpace);
const spaceId = computed(() => currentSpace.value?.id ?? '');

const steps = [
    { key: 'connect', label: 'Connect', icon: '🔌' },
    { key: 'map', label: 'Map', icon: '🗺️' },
    { key: 'preview', label: 'Preview', icon: '👁️' },
    { key: 'import', label: 'Import', icon: '📥' },
    { key: 'complete', label: 'Complete', icon: '✅' },
];

const currentStep = ref(0);
const session = ref(props.session ?? null);
const schema = ref(null);
const mappings = ref(null);
const previewData = ref(null);
const importResult = ref(null);

// Resume from session state if provided
onMounted(() => {
    if (props.session) {
        session.value = props.session;
        const status = props.session.status;
        if (status === 'completed') currentStep.value = 4;
        else if (status === 'importing' || status === 'paused') currentStep.value = 3;
        else if (status === 'mapped') currentStep.value = 2;
        else if (status === 'schema_fetched') currentStep.value = 1;
        else currentStep.value = 0;
    }
});

function goToStep(index) {
    if (index <= currentStep.value) {
        currentStep.value = index;
    }
}

function onConnected(data) {
    session.value = data.session;
    schema.value = data.schema;
    currentStep.value = 1;
}

function onMapped(data) {
    mappings.value = data.mappings;
    currentStep.value = 2;
}

function onPreviewReady(data) {
    previewData.value = data;
    currentStep.value = 3;
}

function onImportComplete(data) {
    importResult.value = data;
    currentStep.value = 4;
}

function onStartNew() {
    session.value = null;
    schema.value = null;
    mappings.value = null;
    previewData.value = null;
    importResult.value = null;
    currentStep.value = 0;
}

const stepComponents = [ConnectStep, MapStep, PreviewStep, ImportStep, CompleteStep];
</script>

<template>
    <div>
        <!-- Header -->
        <div class="mb-8">
            <h1 class="text-2xl font-bold text-white">Migration Wizard</h1>
            <p class="text-gray-500 mt-1">Import content from another CMS into Numen</p>
        </div>

        <!-- Step Indicator -->
        <div class="mb-8">
            <div class="flex items-center justify-between max-w-3xl mx-auto">
                <template v-for="(step, index) in steps" :key="step.key">
                    <button
                        @click="goToStep(index)"
                        :disabled="index > currentStep"
                        class="flex flex-col items-center gap-2 group"
                        :class="index <= currentStep ? 'cursor-pointer' : 'cursor-not-allowed'"
                    >
                        <div
                            class="w-10 h-10 rounded-full flex items-center justify-center text-lg transition-all duration-200"
                            :class="{
                                'bg-indigo-600 text-white ring-2 ring-indigo-400 ring-offset-2 ring-offset-gray-950': index === currentStep,
                                'bg-emerald-600/20 text-emerald-400': index < currentStep,
                                'bg-gray-800 text-gray-500': index > currentStep,
                            }"
                        >
                            <span v-if="index < currentStep">✓</span>
                            <span v-else>{{ step.icon }}</span>
                        </div>
                        <span
                            class="text-xs font-medium transition-colors"
                            :class="{
                                'text-indigo-400': index === currentStep,
                                'text-emerald-400': index < currentStep,
                                'text-gray-600': index > currentStep,
                            }"
                        >{{ step.label }}</span>
                    </button>

                    <!-- Connector line -->
                    <div
                        v-if="index < steps.length - 1"
                        class="flex-1 h-0.5 mx-2 -mt-6 transition-colors"
                        :class="index < currentStep ? 'bg-emerald-600/40' : 'bg-gray-800'"
                    />
                </template>
            </div>
        </div>

        <!-- Step Content -->
        <div class="bg-gray-900 rounded-xl border border-gray-800 p-6">
            <ConnectStep
                v-if="currentStep === 0"
                :space-id="spaceId"
                @connected="onConnected"
            />
            <MapStep
                v-if="currentStep === 1"
                :space-id="spaceId"
                :session="session"
                :schema="schema"
                @mapped="onMapped"
            />
            <PreviewStep
                v-if="currentStep === 2"
                :space-id="spaceId"
                :session="session"
                :mappings="mappings"
                @preview-ready="onPreviewReady"
            />
            <ImportStep
                v-if="currentStep === 3"
                :space-id="spaceId"
                :session="session"
                @import-complete="onImportComplete"
            />
            <CompleteStep
                v-if="currentStep === 4"
                :session="session"
                :result="importResult"
                @start-new="onStartNew"
            />
        </div>
    </div>
</template>
