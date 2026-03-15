<script setup>
const props = defineProps({
    status: { type: String, default: null }, // null | 'pending' | 'processing' | 'completed' | 'failed'
    locale: { type: String, default: null },
});

const config = {
    null:        { classes: 'bg-gray-800/60 text-gray-400 border-gray-700/40',   label: 'Not translated' },
    pending:     { classes: 'bg-yellow-900/40 text-yellow-300 border-yellow-700/40', label: 'Queued' },
    processing:  { classes: 'bg-blue-900/40 text-blue-300 border-blue-700/40',   label: 'Translating…' },
    completed:   { classes: 'bg-green-900/40 text-green-300 border-green-700/40', label: 'Translated' },
    failed:      { classes: 'bg-red-900/40 text-red-300 border-red-700/40',       label: 'Failed' },
};

function badge() {
    return config[props.status] ?? config['null'];
}
</script>

<template>
    <span
        :class="[
            'inline-flex items-center gap-1 px-2 py-0.5 text-xs border rounded-full whitespace-nowrap',
            badge().classes,
        ]"
        :title="locale ? `${locale}: ${badge().label}` : badge().label"
    >
        <span v-if="status === 'completed'">✓</span>
        <span v-else-if="status === 'processing'" class="animate-pulse">⟳</span>
        <span v-else-if="status === 'pending'">⏳</span>
        <span v-else-if="status === 'failed'">✕</span>
        {{ badge().label }}
    </span>
</template>
