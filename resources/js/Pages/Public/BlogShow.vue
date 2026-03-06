<script setup>
import { Link, Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import { marked } from 'marked';
import PublicLayout from '../../Layouts/PublicLayout.vue';
import ContentBlockRenderer from '../../ContentBlocks/ContentBlockRenderer.vue';

const props = defineProps({
    content:        { type: Object,  default: () => ({}) },
    blocks:         { type: Array,   default: () => [] },
    relatedContent: { type: Array,   default: () => [] },
});

const hasBlocks = computed(() => props.blocks?.length > 0);

const renderedBody = computed(() => {
    if (hasBlocks.value || !props.content?.body) return '';
    if (props.content.body_format === 'html') return props.content.body;
    return marked.parse(props.content.body);
});

const seo = computed(() => props.content?.seo ?? {});
const pageTitle = computed(() => seo.value.seo_title || props.content?.title || 'Blog');
const metaDesc = computed(() => seo.value.meta_description || props.content?.excerpt || '');
const ogTitle = computed(() => seo.value.og_title || pageTitle.value);
const ogDesc = computed(() => seo.value.og_description || metaDesc.value);
const ogType = computed(() => seo.value.og_type || 'article');
const ogLocale = computed(() => seo.value.og_locale || 'en_US');
const twitterCard = computed(() => seo.value.twitter_card || 'summary_large_image');
const twitterTitle = computed(() => seo.value.twitter_title || ogTitle.value);
const twitterDesc = computed(() => seo.value.twitter_description || ogDesc.value);
const canonicalUrl = computed(() => seo.value.canonical_url || (props.content?.slug ? `/blog/${props.content.slug}` : null));
const heroImageUrl = computed(() => props.content?.hero_image_url || null);
const keywords = computed(() => (seo.value.keywords || []).join(', '));

// JSON-LD
const jsonLdArticle = computed(() => {
    if (seo.value.json_ld_article) return JSON.stringify(seo.value.json_ld_article);
    // Fallback: generate basic Article JSON-LD
    return JSON.stringify({
        "@context": "https://schema.org",
        "@type": "BlogPosting",
        "headline": props.content?.title,
        "description": props.content?.excerpt,
        "datePublished": props.content?.published_at,
        "dateModified": props.content?.updated_at,
        "author": { "@type": "Organization", "name": "Numen CMS" },
        "publisher": {
            "@type": "Organization",
            "name": "Numen CMS",
        },
        "mainEntityOfPage": { "@type": "WebPage", "@id": canonicalUrl.value },
        "image": heroImageUrl.value || undefined,
    });
});

const jsonLdBreadcrumb = computed(() => {
    if (seo.value.json_ld_breadcrumb) return JSON.stringify(seo.value.json_ld_breadcrumb);
    return JSON.stringify({
        "@context": "https://schema.org",
        "@type": "BreadcrumbList",
        "itemListElement": [
            { "@type": "ListItem", "position": 1, "name": "Home", "item": "/" },
            { "@type": "ListItem", "position": 2, "name": "Blog", "item": "/blog" },
            { "@type": "ListItem", "position": 3, "name": props.content?.title },
        ]
    });
});
</script>

<template>
    <!-- SEO Head -->
    <Head>
        <title>{{ pageTitle }}</title>
        <meta name="description" :content="metaDesc" />
        <meta name="robots" :content="seo.meta_robots || 'index, follow'" />
        <meta v-if="keywords" name="keywords" :content="keywords" />
        <link v-if="canonicalUrl" rel="canonical" :href="canonicalUrl" />

        <!-- Open Graph -->
        <meta property="og:title" :content="ogTitle" />
        <meta property="og:description" :content="ogDesc" />
        <meta property="og:type" :content="ogType" />
        <meta property="og:locale" :content="ogLocale" />
        <meta v-if="canonicalUrl" property="og:url" :content="canonicalUrl" />
        <meta v-if="heroImageUrl" property="og:image" :content="heroImageUrl" />
        <meta property="og:site_name" content="Numen" />

        <!-- Twitter Card -->
        <meta name="twitter:card" :content="twitterCard" />
        <meta name="twitter:title" :content="twitterTitle" />
        <meta name="twitter:description" :content="twitterDesc" />
        <meta v-if="heroImageUrl" name="twitter:image" :content="heroImageUrl" />

        <!-- JSON-LD Article -->
        <script type="application/ld+json" v-html="jsonLdArticle" />
        <!-- JSON-LD Breadcrumb -->
        <script type="application/ld+json" v-html="jsonLdBreadcrumb" />
    </Head>

    <article class="max-w-3xl mx-auto px-6 py-16">
        <!-- Breadcrumb -->
        <div class="flex items-center gap-2 text-sm text-slate-400 mb-8">
            <Link href="/" class="hover:text-indigo-500 transition">Home</Link>
            <span>›</span>
            <Link href="/blog" class="hover:text-indigo-500 transition">Blog</Link>
            <span>›</span>
            <span class="text-slate-600 truncate">{{ content?.title }}</span>
        </div>

        <!-- Hero Image -->
        <div v-if="content?.hero_image_url" class="mb-10 -mx-6 md:-mx-0">
            <img :src="content.hero_image_url"
                 :alt="content?.title"
                 class="w-full rounded-xl shadow-lg object-cover max-h-[480px]"
                 loading="eager" />
        </div>

        <!-- Article Header -->
        <header class="mb-10">
            <div class="flex items-center gap-3 mb-4">
                <span class="px-3 py-1 text-xs bg-indigo-500/10 text-indigo-500 rounded-full font-medium">{{ content?.type }}</span>
                <span class="text-xs text-slate-900/50">{{ content?.published_at }}</span>
            </div>

            <h1 class="text-4xl md:text-5xl text-slate-900 leading-tight mb-4 tracking-tight">
                {{ content?.title }}
            </h1>

            <p v-if="content?.excerpt" class="text-xl text-slate-900/60 leading-relaxed">
                {{ content?.excerpt }}
            </p>

            <!-- AI Meta -->
            <div class="flex flex-wrap items-center gap-4 mt-6 py-4 border-t border-b border-slate-200/50">
                <div class="flex items-center gap-2">
                    <span class="text-sm">🤖</span>
                    <span class="text-xs text-slate-900/60">AI-generated content</span>
                </div>
                <div v-if="content?.seo" class="flex items-center gap-2">
                    <span class="text-sm">🔍</span>
                    <span class="text-xs text-slate-900/60">SEO optimized</span>
                </div>
                <div v-if="content?.meta?.quality_score" class="flex items-center gap-2">
                    <span class="text-sm">✅</span>
                    <span class="text-xs text-slate-900/60">Quality score: {{ content.meta.quality_score }}/100</span>
                </div>
                <div class="flex items-center gap-2">
                    <span class="text-sm">📌</span>
                    <span class="text-xs text-slate-900/60">Version {{ content?.meta?.version || 1 }}</span>
                </div>
            </div>
        </header>

        <!-- Article Body -->
        <div v-if="hasBlocks">
            <ContentBlockRenderer v-for="block in blocks" :key="block.id" :block="block" />
        </div>
        <div v-else
             class="prose prose-lg max-w-none prose-byte
                    prose-headings:text-slate-900 prose-headings:font-bold
                    prose-p:text-slate-900/80 prose-p:leading-relaxed
                    prose-code:text-indigo-500 prose-code:bg-slate-50 prose-code:px-1 prose-code:rounded
                    prose-pre:bg-slate-50 prose-pre:border prose-pre:border-slate-200/50
                    prose-strong:text-slate-900 prose-a:text-indigo-500
                    prose-li:text-slate-900/80"
             v-html="renderedBody" />

        <!-- Tags -->
        <div v-if="content?.taxonomy?.tags?.length" class="flex flex-wrap gap-2 mt-10 pt-6 border-t border-slate-200/50">
            <span v-for="tag in content.taxonomy.tags" :key="tag"
                class="px-3 py-1 text-xs bg-slate-50 text-slate-900/70 rounded-full border border-slate-200/50">
                {{ tag }}
            </span>
        </div>

        <!-- SEO Data (collapsible — expanded view) -->
        <details v-if="content?.seo && Object.keys(content.seo).length > 2" class="mt-8 bg-slate-50 rounded-xl border border-slate-200/50 p-6">
            <summary class="text-sm font-medium text-slate-900/60 cursor-pointer hover:text-slate-900 transition">
                🔍 View Full SEO Metadata
            </summary>
            <div class="mt-4 space-y-4">
                <!-- Meta Tags -->
                <div v-if="seo.seo_title || seo.meta_description" class="space-y-2">
                    <h4 class="text-xs font-semibold text-slate-900/50 uppercase tracking-wider">Meta Tags</h4>
                    <div v-if="seo.seo_title" class="text-sm">
                        <span class="text-slate-900/50">Title:</span>
                        <span class="text-slate-900 ml-2">{{ seo.seo_title }}</span>
                        <span class="text-xs text-slate-900/30 ml-2">({{ seo.seo_title?.length }} chars)</span>
                    </div>
                    <div v-if="seo.meta_description" class="text-sm">
                        <span class="text-slate-900/50">Description:</span>
                        <span class="text-slate-900 ml-2">{{ seo.meta_description }}</span>
                        <span class="text-xs text-slate-900/30 ml-2">({{ seo.meta_description?.length }} chars)</span>
                    </div>
                    <div v-if="seo.meta_robots" class="text-sm">
                        <span class="text-slate-900/50">Robots:</span>
                        <span class="text-slate-900 ml-2 font-mono text-xs">{{ seo.meta_robots }}</span>
                    </div>
                </div>

                <!-- Open Graph -->
                <div v-if="seo.og_title" class="space-y-2">
                    <h4 class="text-xs font-semibold text-slate-900/50 uppercase tracking-wider">Open Graph</h4>
                    <div class="text-sm"><span class="text-slate-900/50">og:title:</span> <span class="text-slate-900 ml-2">{{ seo.og_title }}</span></div>
                    <div v-if="seo.og_description" class="text-sm"><span class="text-slate-900/50">og:description:</span> <span class="text-slate-900 ml-2">{{ seo.og_description }}</span></div>
                    <div class="text-sm"><span class="text-slate-900/50">og:type:</span> <span class="text-slate-900 ml-2 font-mono text-xs">{{ seo.og_type }}</span></div>
                </div>

                <!-- Twitter -->
                <div v-if="seo.twitter_title" class="space-y-2">
                    <h4 class="text-xs font-semibold text-slate-900/50 uppercase tracking-wider">Twitter Card</h4>
                    <div class="text-sm"><span class="text-slate-900/50">Card:</span> <span class="text-slate-900 ml-2 font-mono text-xs">{{ seo.twitter_card }}</span></div>
                    <div class="text-sm"><span class="text-slate-900/50">Title:</span> <span class="text-slate-900 ml-2">{{ seo.twitter_title }}</span></div>
                </div>

                <!-- JSON-LD -->
                <div v-if="seo.json_ld_article" class="space-y-2">
                    <h4 class="text-xs font-semibold text-slate-900/50 uppercase tracking-wider">JSON-LD (Article)</h4>
                    <pre class="text-xs text-slate-900/60 bg-white rounded-lg p-3 overflow-x-auto border border-slate-200/30">{{ JSON.stringify(seo.json_ld_article, null, 2) }}</pre>
                </div>
                <div v-if="seo.json_ld_breadcrumb" class="space-y-2">
                    <h4 class="text-xs font-semibold text-slate-900/50 uppercase tracking-wider">JSON-LD (Breadcrumb)</h4>
                    <pre class="text-xs text-slate-900/60 bg-white rounded-lg p-3 overflow-x-auto border border-slate-200/30">{{ JSON.stringify(seo.json_ld_breadcrumb, null, 2) }}</pre>
                </div>

                <!-- Keywords -->
                <div v-if="seo.keywords?.length" class="space-y-2">
                    <h4 class="text-xs font-semibold text-slate-900/50 uppercase tracking-wider">Keywords</h4>
                    <div class="flex flex-wrap gap-1">
                        <span v-for="kw in seo.keywords" :key="kw"
                              class="px-2 py-0.5 text-xs bg-white text-slate-900/70 rounded border border-slate-200/30">{{ kw }}</span>
                    </div>
                </div>

                <!-- Keyword Density -->
                <div v-if="seo.keyword_density && typeof seo.keyword_density === 'object'" class="space-y-2">
                    <h4 class="text-xs font-semibold text-slate-900/50 uppercase tracking-wider">Keyword Density</h4>
                    <div class="grid grid-cols-2 gap-2">
                        <div v-for="(density, kw) in seo.keyword_density" :key="kw" class="flex justify-between text-sm">
                            <span class="text-slate-900/50">{{ kw }}</span>
                            <span class="text-slate-900 font-mono text-xs">{{ (density * 100).toFixed(1) }}%</span>
                        </div>
                    </div>
                </div>

                <!-- Body Suggestions -->
                <div v-if="seo.body_suggestions?.length" class="space-y-2">
                    <h4 class="text-xs font-semibold text-slate-900/50 uppercase tracking-wider">Improvement Suggestions</h4>
                    <ul class="space-y-1">
                        <li v-for="(s, i) in seo.body_suggestions" :key="i" class="text-sm text-slate-900/70 flex gap-2">
                            <span class="text-indigo-500">•</span> {{ s }}
                        </li>
                    </ul>
                </div>
            </div>
        </details>

        <!-- Related Content -->
        <div v-if="relatedContent?.length" class="mt-16 pt-8 border-t border-slate-200/50">
            <h3 class="text-xl text-slate-900 mb-6 tracking-tight">More from the Pipeline</h3>
            <div class="space-y-4">
                <Link v-for="related in relatedContent" :key="related.slug"
                    :href="`/blog/${related.slug}`"
                    class="block bg-white rounded-lg border border-slate-200/50 p-5 shadow-sm hover:shadow-lg transition group"
                >
                    <h4 class="font-semibold text-slate-900 group-hover:text-indigo-500 transition">{{ related.title }}</h4>
                    <p class="text-sm text-slate-900/50 mt-1 line-clamp-1">{{ related.excerpt }}</p>
                </Link>
            </div>
        </div>

        <!-- Back -->
        <div class="mt-12">
            <Link href="/blog" class="text-sm text-indigo-500 hover:underline font-medium">← Back to Blog</Link>
        </div>
    </article>
</template>

<script>
import PublicLayout from '../../Layouts/PublicLayout.vue';
export default { layout: PublicLayout };
</script>
