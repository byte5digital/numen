<script setup>
defineProps({
    data:    { type: Object, required: true },
    wysiwyg: { type: String, default: null },
});
</script>

<template>
    <section v-if="wysiwyg" class="py-20 px-6 bg-slate-50">
        <div class="max-w-4xl mx-auto text-center" v-html="wysiwyg" />
    </section>

    <section v-else class="py-20 px-6 bg-slate-50">
        <div class="max-w-4xl mx-auto text-center">
            <h2 class="text-3xl text-slate-900 mb-4 tracking-tight">{{ data.headline }}</h2>
            <p class="text-slate-500 mb-10">{{ data.subline }}</p>
            <div class="flex flex-wrap items-center justify-center gap-8">
                <div v-for="item in data.items" :key="item.label"
                     class="flex flex-col items-center gap-2 bg-white rounded-lg p-4 shadow-sm hover:shadow-md transition">
                    <span class="text-2xl">{{ item.icon }}</span>
                    <span class="text-sm text-slate-900 font-medium">{{ item.label }}</span>
                </div>
            </div>
        </div>
    </section>
</template>
