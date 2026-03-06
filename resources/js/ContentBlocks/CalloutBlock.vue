<script setup>
defineProps({
    data:    { type: Object, default: () => ({}) },
    wysiwyg: { type: String, default: null },
});

const variants = {
    info:    { border: 'border-indigo-500/40', bg: 'bg-indigo-500/5',  icon: 'ℹ️',  title: 'text-indigo-500' },
    warning: { border: 'border-amber-500/40', bg: 'bg-amber-500/5',  icon: '⚠️',  title: 'text-amber-400' },
    success: { border: 'border-emerald-500/40', bg: 'bg-emerald-500/5', icon: '✅', title: 'text-emerald-400' },
    error:   { border: 'border-red-500/40',   bg: 'bg-red-500/5',    icon: '🚨', title: 'text-red-400' },
};

const getVariant = (v) => variants[v] ?? variants.info;
</script>

<template>
    <div class="my-6 rounded-xl border p-5"
         :class="[getVariant(data.variant).border, getVariant(data.variant).bg]">
        <div class="flex items-center gap-2 mb-2">
            <span class="text-lg">{{ getVariant(data.variant).icon }}</span>
            <p v-if="data.title" class="font-semibold text-sm" :class="getVariant(data.variant).title">
                {{ data.title }}
            </p>
        </div>
        <p class="text-sm text-gray-300 leading-relaxed">{{ data.body }}</p>
    </div>
</template>
