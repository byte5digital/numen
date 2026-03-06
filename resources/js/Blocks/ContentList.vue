<script setup>
import { Link } from '@inertiajs/vue3';
defineProps({
    data:          { type: Object, required: true },
    wysiwyg:       { type: String, default: null },
    recentContent: { type: Array,  default: () => [] },
});
</script>

<template>
    <section v-if="wysiwyg" class="py-20 px-6 bg-slate-50">
        <div class="max-w-4xl mx-auto" v-html="wysiwyg" />
    </section>

    <section v-else-if="recentContent?.length" class="py-20 px-6 bg-slate-50">
        <div class="max-w-4xl mx-auto">
            <div class="flex items-center justify-between mb-10">
                <div>
                    <h2 class="text-3xl text-slate-900 tracking-tight">{{ data.headline }}</h2>
                    <p class="text-slate-500 mt-1">{{ data.subline }}</p>
                </div>
                <Link v-if="data.view_all_href" :href="data.view_all_href"
                      class="text-sm text-indigo-500 hover:underline font-medium">
                    View all →
                </Link>
            </div>
            <div class="space-y-4">
                <Link v-for="content in recentContent" :key="content.slug"
                      :href="`/blog/${content.slug}`"
                      class="block bg-white rounded-lg border border-slate-200 shadow-sm hover:shadow-lg hover:scale-[1.02] transition-all duration-300 group overflow-hidden">
                    <img v-if="content.hero_image_url"
                         :src="content.hero_image_url"
                         :alt="content.title"
                         class="w-full h-40 object-cover"
                         loading="lazy" />
                    <div :class="content.hero_image_url ? 'p-6' : 'p-6'">
                    <h3 class="text-lg font-semibold text-slate-900 group-hover:text-indigo-500 transition">
                        {{ content.title }}
                    </h3>
                    <p class="text-sm text-slate-500 mt-2 line-clamp-2">{{ content.excerpt }}</p>
                    <div class="flex items-center gap-3 mt-3">
                        <span class="text-xs text-indigo-500 font-medium">{{ content.type }}</span>
                        <span class="text-xs text-slate-300">·</span>
                        <span class="text-xs text-slate-400">AI-generated</span>
                        <span v-if="content.seo_score" class="text-xs text-slate-400">
                            · SEO {{ content.seo_score }}/100
                        </span>
                    </div>
                    </div>
                </Link>
            </div>
        </div>
    </section>
</template>
