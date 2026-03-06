<script setup>
import { Link } from '@inertiajs/vue3';
defineProps({
    data:    { type: Object, required: true },
    wysiwyg: { type: String, default: null },
});

const isExternal = (href) => href?.startsWith('http');
</script>

<template>
    <section v-if="wysiwyg" class="py-20 px-6 bg-gradient-to-r from-indigo-500 to-indigo-900">
        <div class="max-w-3xl mx-auto text-center text-white" v-html="wysiwyg" />
    </section>

    <section v-else class="py-20 px-6 bg-gradient-to-r from-indigo-500 to-indigo-900">
        <div class="max-w-3xl mx-auto text-center">
            <h2 class="text-4xl text-white mb-4 tracking-tight">{{ data.headline }}</h2>
            <p class="text-xl text-white/75 mb-8">{{ data.body }}</p>
            <div class="flex flex-col sm:flex-row items-center justify-center gap-4">
                <template v-if="data.cta_primary_href">
                    <a v-if="isExternal(data.cta_primary_href)"
                       :href="data.cta_primary_href" target="_blank"
                       class="px-8 py-3 bg-white text-slate-900 rounded-full font-medium hover:bg-slate-50 transition text-lg shadow-md">
                        {{ data.cta_primary_label }}
                    </a>
                    <Link v-else :href="data.cta_primary_href"
                          class="px-8 py-3 bg-white text-slate-900 rounded-full font-medium hover:bg-slate-50 transition text-lg shadow-md">
                        {{ data.cta_primary_label }}
                    </Link>
                </template>
                <template v-if="data.cta_secondary_href">
                    <a v-if="isExternal(data.cta_secondary_href)"
                       :href="data.cta_secondary_href" target="_blank"
                       class="px-8 py-3 border-2 border-white text-white rounded-full font-medium hover:bg-white/10 transition text-lg">
                        {{ data.cta_secondary_label }}
                    </a>
                    <Link v-else :href="data.cta_secondary_href"
                          class="px-8 py-3 border-2 border-white text-white rounded-full font-medium hover:bg-white/10 transition text-lg">
                        {{ data.cta_secondary_label }}
                    </Link>
                </template>
            </div>
        </div>
    </section>
</template>
