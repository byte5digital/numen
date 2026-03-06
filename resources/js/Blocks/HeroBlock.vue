<script setup>
import { Link } from '@inertiajs/vue3';
defineProps({
    data: { type: Object, required: true },
    wysiwyg: { type: String, default: null },
});
</script>

<template>
    <section v-if="wysiwyg" class="pt-20 pb-20 px-6 bg-white">
        <div class="max-w-4xl mx-auto" v-html="wysiwyg" />
    </section>

    <section v-else class="pt-20 pb-20 px-6 bg-gradient-to-br from-white via-[#F2F2F2] to-white relative overflow-hidden">
        <!-- Background image -->
        <div v-if="data.image_url" class="absolute inset-0">
            <img :src="data.image_url" class="w-full h-full object-cover opacity-15" alt="" />
            <div class="absolute inset-0 bg-gradient-to-br from-white/80 via-[#F2F2F2]/90 to-white/80" />
        </div>
        <div class="max-w-4xl mx-auto text-center relative z-10">
            <div v-if="data.badge" class="inline-block px-4 py-1.5 bg-indigo-50 border border-indigo-500/20 rounded-full text-indigo-500 text-sm font-medium mb-6">
                {{ data.badge }}
            </div>
            <h1 class="text-5xl md:text-7xl tracking-tight mb-6 text-slate-900" style="white-space: pre-line;">
                <span v-if="data.headline_gradient_word">
                    {{ data.headline.split(data.headline_gradient_word)[0] }}<br>
                    <span class="text-indigo-500">{{ data.headline_gradient_word }}</span>
                </span>
                <template v-else>
                    {{ data.headline.split('\n')[0] }}<br>
                    <span class="text-indigo-500">{{ data.headline.split('\n')[1] }}</span>
                </template>
            </h1>
            <p class="text-xl text-slate-500 max-w-2xl mx-auto mb-10 leading-relaxed">{{ data.subline }}</p>
            <div class="flex flex-col sm:flex-row items-center justify-center gap-4">
                <Link v-if="data.cta_primary_href" :href="data.cta_primary_href" class="px-8 py-3 bg-indigo-500 text-white rounded-full font-medium hover:bg-indigo-600 transition text-lg shadow-md">
                    {{ data.cta_primary_label }}
                </Link>
                <a v-if="data.cta_secondary_href" :href="data.cta_secondary_href" class="px-8 py-3 border-2 border-slate-200 text-slate-900 rounded-full font-medium hover:border-indigo-500 hover:text-indigo-500 transition text-lg">
                    {{ data.cta_secondary_label }}
                </a>
            </div>
        </div>
    </section>
</template>
