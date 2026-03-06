<script setup>
/**
 * DynamicBlock — fallback renderer for AI-registered custom component types.
 *
 * When an AI agent registers a new component type via POST /api/v1/component-types,
 * it can optionally supply a `vue_template` (raw HTML with {{ field }} interpolations).
 * This component fetches that definition and renders it server-side style.
 *
 * If no definition exists, it falls back to a key→value dump so content
 * is never invisible even for completely unknown types.
 */
import { ref, computed, onMounted } from 'vue';

const props = defineProps({
    type:    { type: String, default: 'unknown' },
    data:    { type: Object, default: () => ({}) },
    wysiwyg: { type: String, default: null },
    customDefinition: { type: Object, default: null },
});

const definition = ref(null);
const loading    = ref(true);

onMounted(async () => {
    // Use pre-loaded definition if passed via prop (avoids extra network request)
    if (props.customDefinition) {
        definition.value = props.customDefinition;
        loading.value = false;
        return;
    }
    try {
        const res = await fetch(`/api/v1/component-types/${props.type}`);
        if (res.ok) definition.value = await res.json();
    } catch (_) { /* offline or unknown — fall through */ }
    loading.value = false;
});

/** Render the vue_template by replacing {{ field }} with actual values */
const renderedTemplate = computed(() => {
    if (!definition.value?.vue_template) return null;
    const d = props.data ?? {};
    return definition.value.vue_template.replace(/\{\{\s*([\w.]+)\s*\}\}/g, (_, key) => {
        return d[key] ?? '';
    });
});

/** Fallback: list all data fields as a definition list */
const dataEntries = computed(() => Object.entries(props.data ?? {}));
</script>

<template>
    <!-- wysiwyg_override takes priority -->
    <div v-if="wysiwyg" v-html="wysiwyg" class="prose prose-invert max-w-none my-6" />

    <!-- AI-registered template -->
    <div v-else-if="renderedTemplate" v-html="renderedTemplate" class="my-6" />

    <!-- Loading skeleton -->
    <div v-else-if="loading" class="my-6 animate-pulse rounded-xl border border-gray-800 bg-gray-900 p-6 h-24" />

    <!-- Generic key→value fallback so nothing disappears -->
    <div v-else class="my-6 rounded-xl border border-gray-700 bg-gray-900/50 p-6">
        <p class="mb-3 text-xs font-mono text-gray-500 uppercase tracking-widest">
            {{ type }}
        </p>
        <dl class="space-y-2">
            <div v-for="[key, val] in dataEntries" :key="key" class="flex gap-3 text-sm">
                <dt class="w-32 shrink-0 font-medium text-gray-400">{{ key }}</dt>
                <dd class="text-gray-200 break-words">
                    {{ typeof val === 'object' ? JSON.stringify(val) : val }}
                </dd>
            </div>
        </dl>
        <p v-if="!dataEntries.length" class="text-sm text-gray-600 italic">No data</p>
    </div>
</template>
