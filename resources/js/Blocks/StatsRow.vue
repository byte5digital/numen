<script setup>
defineProps({
    data: { type: Object, required: true },
    wysiwyg: { type: String, default: null },
});
</script>

<template>
    <section v-if="wysiwyg" class="py-12 px-6 bg-slate-50">
        <div class="max-w-4xl mx-auto" v-html="wysiwyg" />
    </section>

    <section v-else class="py-12 px-6 bg-slate-50">
        <div class="max-w-4xl mx-auto grid grid-cols-2 md:grid-cols-4 gap-8 text-center">
            <div v-for="stat in data.stats" :key="stat.label">
                <p class="text-3xl font-bold text-indigo-500 tracking-tight">
                    {{ stat.prefix || '' }}{{ stat.value || '0' }}
                </p>
                <p class="text-sm text-slate-500 mt-1">{{ stat.label }}</p>
            </div>
        </div>
    </section>
</template>
