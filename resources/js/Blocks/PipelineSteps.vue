<script setup>
defineProps({
    data: { type: Object, required: true },
    wysiwyg: { type: String, default: null },
});
</script>

<template>
    <section v-if="wysiwyg" class="py-20 px-6 bg-slate-900">
        <div class="max-w-5xl mx-auto text-white" v-html="wysiwyg" />
    </section>

    <section v-else class="py-20 px-6 bg-slate-900">
        <div class="max-w-5xl mx-auto">
            <h2 class="text-3xl text-center text-white mb-4 tracking-tight">{{ data.headline }}</h2>
            <p class="text-center text-white/60 mb-12 max-w-xl mx-auto">{{ data.subline }}</p>
            <div class="flex flex-col md:flex-row items-center justify-center gap-4">
                <template v-for="(step, i) in data.steps" :key="step.name">
                    <div class="flex flex-col items-center px-6 py-4 bg-white/10 rounded-lg border border-white/20 min-w-[140px] backdrop-blur-sm">
                        <div class="w-10 h-10 rounded-full flex items-center justify-center text-white font-bold text-lg mb-2 bg-indigo-500">
                            {{ i + 1 }}
                        </div>
                        <p class="font-semibold text-white">{{ step.name }}</p>
                        <p class="text-xs text-white/50">{{ step.description }}</p>
                    </div>
                    <svg v-if="i < data.steps.length - 1" class="w-8 h-8 text-white/30 hidden md:block flex-shrink-0"
                         fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                    </svg>
                </template>
            </div>
        </div>
    </section>
</template>
