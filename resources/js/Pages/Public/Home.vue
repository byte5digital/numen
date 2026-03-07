<script setup>
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    page: { type: Object, default: null },
});

const features = [
    {
        icon: '📝',
        title: 'Brief-Driven',
        desc: 'Describe what you need. The pipeline handles the rest — generation, illustration, SEO, quality review, publishing.',
    },
    {
        icon: '🎨',
        title: 'AI Image Generation',
        desc: 'AI-powered hero images via OpenAI, Replicate, Together AI, or fal.ai. The Visual Director picks the right model for your brand.',
    },
    {
        icon: '🔌',
        title: 'Headless API',
        desc: 'Clean REST API for any frontend. Block-based content model. Deliver to websites, apps, or custom integrations.',
    },
    {
        icon: '🔄',
        title: 'Provider Agnostic',
        desc: 'Swap between Anthropic, OpenAI, Together AI, Replicate, and fal.ai — for both text and images — without touching code. Auto-fallback on rate limits.',
    },
    {
        icon: '📊',
        title: 'Full Provenance',
        desc: 'Every AI call logged — model, tokens, cost, stage. Image generation costs tracked per asset.',
    },
    {
        icon: '⚙️',
        title: 'Pipeline as Config',
        desc: 'Stages defined in DB, not code. Add review gates, human checkpoints, or custom agents without deploying.',
    },
];

const stack = [
    { label: 'Backend', value: 'PHP 8.4 · Laravel 12' },
    { label: 'Frontend', value: 'Inertia.js · Vue 3' },
    { label: 'Auth', value: 'Laravel Sanctum' },
    { label: 'AI', value: 'Anthropic · OpenAI · Together · Replicate · fal.ai' },
    { label: 'Database', value: 'SQLite / MySQL' },
    { label: 'License', value: 'MIT' },
];
</script>

<template>
    <Head>
        <title>Numen — AI-First Headless CMS</title>
        <meta name="description" content="Content, animated by AI. Submit a brief, get published content. Open-source headless CMS with a three-stage AI pipeline." />
        <meta property="og:title" content="Numen — AI-First Headless CMS" />
        <meta property="og:description" content="You write briefs. AI writes content. Open-source, provider-agnostic, pipeline-driven." />
        <meta property="og:type" content="website" />
        <meta name="twitter:card" content="summary_large_image" />
    </Head>

    <!-- Hero -->
    <section class="relative overflow-hidden">
        <div class="absolute inset-0 bg-gradient-to-br from-slate-50 via-white to-indigo-50/50"></div>
        <div class="absolute inset-0 bg-[radial-gradient(ellipse_80%_60%_at_50%_-20%,rgba(99,102,241,0.08),transparent)]"></div>

        <div class="relative max-w-5xl mx-auto px-6 pt-28 pb-24 text-center">
            <div class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-indigo-50 border border-indigo-100 text-sm text-indigo-600 font-medium mb-8">
                <span>✨</span>
                <span>Open Source · MIT License</span>
            </div>

            <h1 class="text-5xl sm:text-6xl lg:text-7xl font-extrabold text-slate-900 tracking-tight leading-[1.1] mb-6">
                Content,<br />
                <span class="bg-gradient-to-r from-indigo-500 to-violet-500 bg-clip-text text-transparent">animated by AI.</span>
            </h1>

            <p class="text-lg sm:text-xl text-slate-500 max-w-2xl mx-auto leading-relaxed mb-10">
                You write briefs. A pipeline of AI agents handles generation, SEO optimization,
                and quality review — then publishes automatically.
            </p>

            <div class="flex flex-col sm:flex-row items-center justify-center gap-4">
                <a href="https://github.com/byte5digital/numen"
                   target="_blank"
                   class="inline-flex items-center gap-2.5 px-7 py-3.5 bg-slate-900 text-white rounded-full font-semibold text-base hover:bg-slate-800 transition shadow-lg shadow-slate-900/20 hover:shadow-xl hover:shadow-slate-900/25">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M12 0C5.37 0 0 5.37 0 12c0 5.31 3.435 9.795 8.205 11.385.6.105.825-.255.825-.57 0-.285-.015-1.23-.015-2.235-3.015.555-3.795-.735-4.035-1.41-.135-.345-.72-1.41-1.23-1.695-.42-.225-1.02-.78-.015-.795.945-.015 1.62.87 1.845 1.23 1.08 1.815 2.805 1.305 3.495.99.105-.78.42-1.305.765-1.605-2.67-.3-5.46-1.335-5.46-5.925 0-1.305.465-2.385 1.23-3.225-.12-.3-.54-1.53.12-3.18 0 0 1.005-.315 3.3 1.23.96-.27 1.98-.405 3-.405s2.04.135 3 .405c2.295-1.56 3.3-1.23 3.3-1.23.66 1.65.24 2.88.12 3.18.765.84 1.23 1.905 1.23 3.225 0 4.605-2.805 5.625-5.475 5.925.435.375.81 1.095.81 2.22 0 1.605-.015 2.895-.015 3.3 0 .315.225.69.825.57A12.02 12.02 0 0024 12c0-6.63-5.37-12-12-12z"/></svg>
                    View on GitHub
                </a>
                <Link href="/blog"
                      class="inline-flex items-center gap-2 px-7 py-3.5 bg-white text-slate-700 rounded-full font-semibold text-base border border-slate-200 hover:border-slate-300 hover:bg-slate-50 transition shadow-sm">
                    Read the Blog
                    <span class="text-slate-400">→</span>
                </Link>
            </div>
        </div>
    </section>

    <!-- Pipeline Visualization -->
    <section class="py-20 px-6 bg-white border-t border-slate-100">
        <div class="max-w-4xl mx-auto">
            <h2 class="text-center text-3xl sm:text-4xl font-bold text-slate-900 tracking-tight mb-4">How it works</h2>
            <p class="text-center text-slate-500 mb-14 max-w-xl mx-auto">Traditional CMS: open editor → write → publish. Numen: submit a brief → pipeline runs → content appears.</p>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
                <!-- Stage 1: Generate -->
                <div class="bg-gradient-to-b from-indigo-50 to-white border border-indigo-100 rounded-2xl p-5 text-center">
                    <div class="w-10 h-10 rounded-xl bg-indigo-100 flex items-center justify-center mx-auto mb-3">
                        <span class="text-lg">✍️</span>
                    </div>
                    <h3 class="font-bold text-slate-900 text-sm mb-1">Content Creator</h3>
                    <p class="text-xs text-indigo-500 font-medium mb-1">claude-sonnet-4-6</p>
                    <p class="text-xs text-slate-500">Writes the article</p>
                </div>

                <!-- Stage 2: Illustrate -->
                <div class="bg-gradient-to-b from-pink-50 to-white border border-pink-100 rounded-2xl p-5 text-center">
                    <div class="w-10 h-10 rounded-xl bg-pink-100 flex items-center justify-center mx-auto mb-3">
                        <span class="text-lg">🎨</span>
                    </div>
                    <h3 class="font-bold text-slate-900 text-sm mb-1">AI Illustrator</h3>
                    <p class="text-xs text-pink-500 font-medium mb-1">multi-provider</p>
                    <p class="text-xs text-slate-500">Generates hero image</p>
                </div>

                <!-- Stage 3: SEO -->
                <div class="bg-gradient-to-b from-emerald-50 to-white border border-emerald-100 rounded-2xl p-5 text-center">
                    <div class="w-10 h-10 rounded-xl bg-emerald-100 flex items-center justify-center mx-auto mb-3">
                        <span class="text-lg">🔍</span>
                    </div>
                    <h3 class="font-bold text-slate-900 text-sm mb-1">SEO Expert</h3>
                    <p class="text-xs text-emerald-500 font-medium mb-1">claude-haiku-4-5</p>
                    <p class="text-xs text-slate-500">Optimizes metadata</p>
                </div>

                <!-- Stage 4: Review -->
                <div class="bg-gradient-to-b from-amber-50 to-white border border-amber-100 rounded-2xl p-5 text-center">
                    <div class="w-10 h-10 rounded-xl bg-amber-100 flex items-center justify-center mx-auto mb-3">
                        <span class="text-lg">⚖️</span>
                    </div>
                    <h3 class="font-bold text-slate-900 text-sm mb-1">Editorial Director</h3>
                    <p class="text-xs text-amber-500 font-medium mb-1">claude-opus-4-6</p>
                    <p class="text-xs text-slate-500">Quality gate (≥ 80)</p>
                </div>

                <!-- Stage 5: Publish -->
                <div class="bg-gradient-to-b from-violet-50 to-white border border-violet-100 rounded-2xl p-5 text-center">
                    <div class="w-10 h-10 rounded-xl bg-violet-100 flex items-center justify-center mx-auto mb-3">
                        <span class="text-lg">🚀</span>
                    </div>
                    <h3 class="font-bold text-slate-900 text-sm mb-1">Auto-Publish</h3>
                    <p class="text-xs text-violet-500 font-medium mb-1">event-driven</p>
                    <p class="text-xs text-slate-500">Goes live via API</p>
                </div>
            </div>

            <p class="text-center text-sm text-slate-400 mt-6">
                Every stage is a queued job. Add <code class="text-slate-500">human_gate</code> stages anywhere to pause for manual review.
            </p>
        </div>
    </section>

    <!-- Features Grid -->
    <section class="py-20 px-6 bg-slate-50/50">
        <div class="max-w-5xl mx-auto">
            <h2 class="text-center text-3xl sm:text-4xl font-bold text-slate-900 tracking-tight mb-4">Built different</h2>
            <p class="text-center text-slate-500 mb-14 max-w-xl mx-auto">Not another page builder. A pipeline engine that happens to produce content.</p>

            <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
                <div v-for="feature in features" :key="feature.title"
                     class="bg-white rounded-2xl border border-slate-200/80 p-6 hover:shadow-lg hover:border-slate-300 transition-all duration-300">
                    <span class="text-2xl mb-3 block">{{ feature.icon }}</span>
                    <h3 class="font-bold text-slate-900 mb-2">{{ feature.title }}</h3>
                    <p class="text-sm text-slate-500 leading-relaxed">{{ feature.desc }}</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Code Preview -->
    <section class="py-20 px-6 bg-white border-t border-slate-100">
        <div class="max-w-4xl mx-auto">
            <h2 class="text-center text-3xl sm:text-4xl font-bold text-slate-900 tracking-tight mb-4">Quick Start</h2>
            <p class="text-center text-slate-500 mb-10 max-w-xl mx-auto">Up and running in under two minutes.</p>

            <div class="bg-slate-900 rounded-2xl p-6 sm:p-8 overflow-x-auto shadow-2xl shadow-slate-900/20">
                <pre class="text-sm leading-relaxed"><code class="text-slate-300"><span class="text-slate-500"># Clone & install</span>
<span class="text-emerald-400">$</span> git clone https://github.com/byte5digital/numen.git
<span class="text-emerald-400">$</span> cd numen
<span class="text-emerald-400">$</span> composer install
<span class="text-emerald-400">$</span> npm install

<span class="text-slate-500"># Configure</span>
<span class="text-emerald-400">$</span> cp .env.example .env
<span class="text-emerald-400">$</span> php artisan key:generate

<span class="text-slate-500"># Database & seed</span>
<span class="text-emerald-400">$</span> touch database/database.sqlite
<span class="text-emerald-400">$</span> php artisan migrate
<span class="text-emerald-400">$</span> php artisan db:seed --class=DemoSeeder

<span class="text-slate-500"># Start (three terminals)</span>
<span class="text-emerald-400">$</span> php artisan serve          <span class="text-slate-500"># → http://localhost:8000</span>
<span class="text-emerald-400">$</span> php artisan queue:work     <span class="text-slate-500"># processes pipeline jobs</span>
<span class="text-emerald-400">$</span> npm run dev                <span class="text-slate-500"># Vite hot-reload</span></code></pre>
            </div>

            <p class="text-center text-sm text-slate-400 mt-4">
                Requires PHP 8.4, Composer, Node.js 18+. Redis optional — works with <code class="text-slate-500">QUEUE_CONNECTION=database</code> too.
            </p>
        </div>
    </section>

    <!-- Stack -->
    <section class="py-20 px-6 bg-slate-50/50">
        <div class="max-w-3xl mx-auto text-center">
            <h2 class="text-3xl sm:text-4xl font-bold text-slate-900 tracking-tight mb-12">The Stack</h2>
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
                <div v-for="item in stack" :key="item.label"
                     class="bg-white rounded-xl border border-slate-200 p-4">
                    <p class="text-xs text-slate-400 uppercase tracking-wider font-medium mb-1">{{ item.label }}</p>
                    <p class="text-sm font-semibold text-slate-700">{{ item.value }}</p>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA -->
    <section class="py-24 px-6 bg-gradient-to-b from-white to-slate-50 border-t border-slate-100">
        <div class="max-w-3xl mx-auto text-center">
            <h2 class="text-3xl sm:text-4xl font-bold text-slate-900 tracking-tight mb-4">
                Ready to automate your content?
            </h2>
            <p class="text-slate-500 mb-8 max-w-lg mx-auto">
                Numen is open source and free. Clone it, configure your AI provider, submit a brief, and watch the pipeline work.
            </p>
            <div class="flex flex-col sm:flex-row items-center justify-center gap-4">
                <a href="https://github.com/byte5digital/numen"
                   target="_blank"
                   class="inline-flex items-center gap-2.5 px-7 py-3.5 bg-indigo-500 text-white rounded-full font-semibold text-base hover:bg-indigo-600 transition shadow-lg shadow-indigo-500/25">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M12 0C5.37 0 0 5.37 0 12c0 5.31 3.435 9.795 8.205 11.385.6.105.825-.255.825-.57 0-.285-.015-1.23-.015-2.235-3.015.555-3.795-.735-4.035-1.41-.135-.345-.72-1.41-1.23-1.695-.42-.225-1.02-.78-.015-.795.945-.015 1.62.87 1.845 1.23 1.08 1.815 2.805 1.305 3.495.99.105-.78.42-1.305.765-1.605-2.67-.3-5.46-1.335-5.46-5.925 0-1.305.465-2.385 1.23-3.225-.12-.3-.54-1.53.12-3.18 0 0 1.005-.315 3.3 1.23.96-.27 1.98-.405 3-.405s2.04.135 3 .405c2.295-1.56 3.3-1.23 3.3-1.23.66 1.65.24 2.88.12 3.18.765.84 1.23 1.905 1.23 3.225 0 4.605-2.805 5.625-5.475 5.925.435.375.81 1.095.81 2.22 0 1.605-.015 2.895-.015 3.3 0 .315.225.69.825.57A12.02 12.02 0 0024 12c0-6.63-5.37-12-12-12z"/></svg>
                    Get Started on GitHub
                </a>
                <a href="/api/documentation"
                   class="inline-flex items-center gap-2 px-7 py-3.5 text-slate-600 font-semibold text-base hover:text-indigo-500 transition">
                    API Docs
                    <span>→</span>
                </a>
            </div>
        </div>
    </section>
</template>

<script>
import PublicLayout from '../../Layouts/PublicLayout.vue';
export default { layout: PublicLayout };
</script>
