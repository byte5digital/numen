<script setup>
import { Link, Head } from '@inertiajs/vue3';
import PublicLayout from '../../Layouts/PublicLayout.vue';

defineProps({
    contents: Object,
    contentTypes: Array,
    currentType: String,
});
</script>

<template>
    <Head>
        <title>Blog — Numen</title>
        <meta name="description" content="AI-generated articles about content management, AI architecture, and modern web development — powered by Numen." />
        <meta property="og:title" content="Blog — Numen" />
        <meta property="og:description" content="AI-generated articles about content management, AI architecture, and modern web development." />
        <meta property="og:type" content="website" />
        <meta name="twitter:card" content="summary" />
    </Head>
    <div class="max-w-4xl mx-auto px-6 py-16">
        <div class="mb-12">
            <h1 class="text-4xl font-bold text-slate-900 tracking-tight">Blog</h1>
            <p class="text-slate-500 mt-2">AI-generated content — written, SEO-optimized, and quality-reviewed by our agent pipeline</p>
        </div>

        <!-- Type Filter -->
        <div class="flex items-center gap-2 mb-8 overflow-x-auto pb-2">
            <Link href="/blog"
                class="px-4 py-1.5 rounded-full text-sm font-medium transition whitespace-nowrap"
                :class="!currentType ? 'bg-indigo-500 text-white shadow-md' : 'bg-white text-slate-600 hover:text-indigo-500 border border-slate-200'"
            >All</Link>
            <Link v-for="type in contentTypes" :key="type.slug"
                :href="`/blog?type=${type.slug}`"
                class="px-4 py-1.5 rounded-full text-sm font-medium transition whitespace-nowrap"
                :class="currentType === type.slug ? 'bg-indigo-500 text-white shadow-md' : 'bg-white text-slate-600 hover:text-indigo-500 border border-slate-200'"
            >{{ type.name }}</Link>
        </div>

        <!-- Articles -->
        <div class="space-y-6">
            <Link v-for="article in contents?.data" :key="article.slug"
                :href="`/blog/${article.slug}`"
                class="block bg-white rounded-lg border border-slate-200 shadow-sm hover:shadow-lg hover:scale-[1.01] transition-all duration-300 group overflow-hidden"
                :class="article.hero_image_url ? '' : 'p-8'"
            >
                <!-- Thumbnail -->
                <img v-if="article.hero_image_url"
                     :src="article.hero_image_url"
                     :alt="article.title"
                     class="w-full h-48 object-cover"
                     loading="lazy" />

                <div :class="article.hero_image_url ? 'p-6' : ''">
                <div class="flex items-center gap-3 mb-3">
                    <span class="px-2.5 py-0.5 text-xs bg-indigo-50 text-indigo-600 rounded-full font-medium">{{ article.type }}</span>
                    <span class="text-xs text-slate-300">·</span>
                    <span class="text-xs text-slate-400">🤖 AI-generated</span>
                    <span v-if="article.published_at" class="text-xs text-slate-400">· {{ article.published_at }}</span>
                </div>

                <h2 class="text-xl font-bold text-slate-900 group-hover:text-indigo-500 transition mb-2">
                    {{ article.title }}
                </h2>

                <p class="text-slate-500 text-sm leading-relaxed line-clamp-3">
                    {{ article.excerpt }}
                </p>

                <div class="flex items-center gap-4 mt-4">
                    <div v-if="article.quality_score" class="flex items-center gap-2">
                        <span class="text-xs text-slate-400">Quality:</span>
                        <div class="w-16 h-1.5 bg-slate-100 rounded-full overflow-hidden">
                            <div class="h-full rounded-full" :class="article.quality_score >= 80 ? 'bg-emerald-500' : 'bg-amber-500'" :style="{ width: `${article.quality_score}%` }"></div>
                        </div>
                        <span class="text-xs text-slate-400">{{ article.quality_score }}</span>
                    </div>
                    <div v-if="article.seo_score" class="flex items-center gap-2">
                        <span class="text-xs text-slate-400">SEO:</span>
                        <span class="text-xs text-slate-500">{{ article.seo_score }}/100</span>
                    </div>
                    <span class="text-xs text-indigo-500 ml-auto group-hover:underline font-medium">Read article →</span>
                </div>
                </div>
            </Link>
        </div>

        <!-- Empty State -->
        <div v-if="!contents?.data?.length" class="text-center py-20">
            <p class="text-3xl mb-3">📝</p>
            <p class="text-slate-400">No articles published yet. The pipeline is warming up.</p>
        </div>

        <!-- Pagination -->
        <div v-if="contents?.meta?.last_page > 1" class="flex items-center justify-center gap-2 mt-12">
            <Link v-for="link in contents.meta.links" :key="link.label"
                :href="link.url || '#'"
                class="px-3 py-1.5 rounded-lg text-sm transition"
                :class="link.active ? 'bg-indigo-500 text-white shadow-md' : link.url ? 'bg-white text-slate-600 hover:text-indigo-500 border border-slate-200' : 'text-slate-300 cursor-not-allowed'"
                v-html="link.label"
            />
        </div>
    </div>
</template>

<script>
import PublicLayout from '../../Layouts/PublicLayout.vue';
export default { layout: PublicLayout };
</script>
