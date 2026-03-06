<script setup>
defineProps({
    data: { type: Object, required: true },
    wysiwyg: { type: String, default: null },
});
</script>

<template>
    <section v-if="wysiwyg" class="py-20 px-6 bg-white">
        <div class="max-w-5xl mx-auto" v-html="wysiwyg" />
    </section>

    <section v-else class="py-20 px-6 bg-white">
        <div class="max-w-5xl mx-auto">
            <h2 class="text-3xl text-center text-slate-900 mb-12 tracking-tight">{{ data.headline }}</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <div v-for="f in data.features" :key="f.title"
                     class="bg-white rounded-lg border border-slate-200 p-6 shadow-sm hover:shadow-lg hover:scale-105 transition-all duration-300">
                    <span class="text-3xl mb-3 block">{{ f.icon }}</span>
                    <h3 class="text-lg font-semibold text-slate-900 mb-2">{{ f.title }}</h3>
                    <p class="text-sm text-slate-500 leading-relaxed">{{ f.description }}</p>
                </div>
            </div>
        </div>
    </section>
</template>
