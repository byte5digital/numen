<script setup>
import { ref, computed, reactive } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { marked } from 'marked';
import ContentBlockRenderer from '../../ContentBlocks/ContentBlockRenderer.vue';
import ComponentFieldEditor from '../../Blocks/ComponentFieldEditor.vue';

const props = defineProps({
    content:    { type: Object, required: true },
    version:    { type: Object, default: null },
    versions:   { type: Array,  default: () => [] },
    blocks:     { type: Array,  default: () => [] },
    blockTypes: { type: Object, default: () => ({}) },
});

const flash = computed(() => usePage().props.flash ?? {});

const statusColors = {
    published: 'bg-emerald-900/50 text-emerald-400',
    draft:     'bg-gray-800 text-gray-400',
    archived:  'bg-red-900/30 text-red-400',
    in_pipeline: 'bg-indigo-900/50 text-indigo-400',
    review:    'bg-amber-900/50 text-amber-400',
};

const activeTab = ref('content');

// ----- Block editor state -----
const localBlocks = ref(props.blocks.map(b => ({ ...b, data: { ...b.data } })));
const expandedBlock = ref(null);
const activeBlockTab = reactive({}); // blockId -> 'preview'|'fields'|'html'
const showAddBlock = ref(false);
const newBlockType = ref(Object.keys(props.blockTypes)[0] ?? 'paragraph');
const saving = ref(null);
const generatingImage = ref(false);
const updatePrompt = ref('');
const submittingUpdate = ref(false);
const showUpdateBrief = ref(false);

function submitUpdateBrief() {
    if (!updatePrompt.value.trim() || submittingUpdate.value) return;
    submittingUpdate.value = true;
    router.post(
        `/admin/content/${props.content.id}/update-brief`,
        { prompt: updatePrompt.value },
        {
            preserveScroll: true,
            onSuccess: () => {
                updatePrompt.value = '';
                showUpdateBrief.value = false;
            },
            onFinish: () => { submittingUpdate.value = false; },
        }
    );
}

function generateImage() {
    if (generatingImage.value) return;
    generatingImage.value = true;
    router.post(
        `/admin/content/${props.content.id}/generate-image`,
        {},
        {
            preserveScroll: true,
            onFinish: () => { generatingImage.value = false; },
        }
    );
}

function blockTab(blockId) {
    return activeBlockTab[blockId] ?? 'preview';
}
function setBlockTab(blockId, tab) {
    activeBlockTab[blockId] = tab;
}

function toggleBlock(blockId) {
    expandedBlock.value = expandedBlock.value === blockId ? null : blockId;
}

function saveBlock(block) {
    saving.value = block.id;
    router.put(
        `/admin/content/${props.content.id}/blocks/${block.id}`,
        { data: block.data, wysiwyg_override: block.wysiwyg_override ?? null },
        { preserveScroll: true, onFinish: () => { saving.value = null; } }
    );
}

function deleteBlock(blockId) {
    if (!confirm('Delete this block?')) return;
    router.delete(`/admin/content/${props.content.id}/blocks/${blockId}`, { preserveScroll: true });
}

function addBlock() {
    router.post(
        `/admin/content/${props.content.id}/blocks`,
        { type: newBlockType.value, data: {} },
        { preserveScroll: true, onSuccess: () => { showAddBlock.value = false; } }
    );
}

function moveBlock(index, dir) {
    const arr = [...localBlocks.value];
    const swapIdx = index + dir;
    if (swapIdx < 0 || swapIdx >= arr.length) return;
    [arr[index], arr[swapIdx]] = [arr[swapIdx], arr[index]];
    localBlocks.value = arr;
    router.post(
        `/admin/content/${props.content.id}/blocks/reorder`,
        { order: arr.map(b => b.id) },
        { preserveScroll: true }
    );
}

const typeSchema = (type) => props.blockTypes[type] ?? {};

function setStatus(status) {
    if (!confirm(`Set status to "${status}"?`)) return;
    router.patch(`/admin/content/${props.content.id}/status`, { status }, { preserveScroll: true });
}

const scoreColor = (score) =>
    score >= 80 ? 'text-emerald-400' : score >= 60 ? 'text-amber-400' : 'text-red-400';

const renderedBody = computed(() => {
    if (!props.version?.body) return '';
    if (props.version.body_format === 'html') return props.version.body;
    return marked.parse(props.version.body);
});
</script>

<template>
    <div>
        <!-- Header -->
        <div class="flex items-start gap-4 mb-8">
            <Link href="/admin/content" class="text-gray-500 hover:text-white transition text-sm mt-1">← Content</Link>
            <div class="flex-1 min-w-0">
                <div class="flex items-center gap-3 flex-wrap">
                    <h1 class="text-2xl font-bold text-white truncate">
                        {{ version?.title ?? 'Untitled' }}
                    </h1>
                    <span class="px-2 py-1 text-xs rounded-full shrink-0" :class="statusColors[content.status] ?? 'bg-gray-800 text-gray-400'">
                        {{ content.status }}
                    </span>
                </div>
                <p class="text-gray-500 text-sm mt-1 font-mono">{{ content.slug }}</p>
            </div>

            <!-- Actions -->
            <div class="flex items-center gap-2 shrink-0">
                <button @click="generateImage"
                        :disabled="generatingImage"
                        class="text-xs px-3 py-1.5 border border-indigo-700 text-indigo-400 rounded-lg hover:text-indigo-300 hover:border-indigo-500 transition disabled:opacity-50">
                    {{ generatingImage ? '🎨 Generating…' : '🎨 Generate Image' }}
                </button>
                <a v-if="content.status === 'published'"
                   :href="`/blog/${content.slug}`" target="_blank"
                   class="text-xs px-3 py-1.5 border border-gray-700 text-gray-400 rounded-lg hover:text-white hover:border-gray-500 transition">
                    View Live ↗
                </a>
                <button v-if="content.status !== 'published'"
                        @click="setStatus('published')"
                        class="text-xs px-3 py-1.5 bg-emerald-700 text-white rounded-lg hover:bg-emerald-600 transition">
                    Publish
                </button>
                <button v-if="content.status === 'published'"
                        @click="setStatus('draft')"
                        class="text-xs px-3 py-1.5 bg-gray-800 text-gray-300 rounded-lg hover:bg-gray-700 transition">
                    Unpublish
                </button>
                <button v-if="content.status !== 'archived'"
                        @click="setStatus('archived')"
                        class="text-xs px-3 py-1.5 text-red-600 hover:text-red-400 transition">
                    Archive
                </button>
            </div>
        </div>

        <!-- Flash -->
        <div v-if="flash.success"
             class="mb-5 px-4 py-3 bg-emerald-500/10 border border-emerald-500/20 rounded-lg text-emerald-400 text-sm">
            {{ flash.success }}
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            <!-- Main content column -->
            <div class="lg:col-span-2 space-y-5">

                <!-- Tabs -->
                <div class="flex gap-1 bg-gray-900 border border-gray-800 rounded-xl p-1 w-fit">
                    <button v-for="tab in ['content', 'blocks', 'seo', 'metadata']" :key="tab"
                            @click="activeTab = tab"
                            class="px-4 py-1.5 rounded-lg text-sm font-medium transition capitalize"
                            :class="activeTab === tab ? 'bg-gray-800 text-white' : 'text-gray-500 hover:text-gray-300'">
                        {{ tab }}
                        <span v-if="tab === 'blocks' && localBlocks.length"
                              class="ml-1 text-xs px-1.5 py-0.5 bg-indigo-500/20 text-indigo-400 rounded-full">
                            {{ localBlocks.length }}
                        </span>
                    </button>
                </div>

                <!-- Content tab -->
                <div v-if="activeTab === 'content'" class="bg-gray-900 rounded-xl border border-gray-800 p-6">
                    <div v-if="version">
                        <div class="mb-4 pb-4 border-b border-gray-800">
                            <h2 class="text-lg font-semibold text-white">{{ version.title }}</h2>
                            <p v-if="version.excerpt" class="text-gray-400 text-sm mt-2 leading-relaxed italic">{{ version.excerpt }}</p>
                        </div>
                        <div class="prose prose-invert prose-sm max-w-none
                                    prose-headings:text-white prose-headings:font-bold
                                    prose-p:text-gray-300 prose-p:leading-relaxed
                                    prose-code:text-indigo-300 prose-code:bg-gray-800 prose-code:px-1 prose-code:rounded
                                    prose-pre:bg-gray-800 prose-pre:border prose-pre:border-gray-700
                                    prose-strong:text-white prose-a:text-indigo-400
                                    prose-li:text-gray-300"
                             v-html="renderedBody" />
                    </div>
                    <p v-else class="text-gray-600 text-sm">No version content available.</p>
                </div>

                <!-- Blocks tab -->
                <div v-else-if="activeTab === 'blocks'" class="space-y-3">

                    <!-- Block list -->
                    <div v-if="!localBlocks.length" class="bg-gray-900 rounded-xl border border-gray-800 p-8 text-center text-gray-600 text-sm">
                        No blocks yet. Add one below to replace the raw body with a structured block layout.
                    </div>

                    <div v-for="(block, idx) in localBlocks" :key="block.id"
                         class="bg-gray-900 rounded-xl border border-gray-800 overflow-hidden">

                        <!-- Block header -->
                        <div class="flex items-center gap-3 px-4 py-3 cursor-pointer hover:bg-gray-800/50 transition"
                             @click="toggleBlock(block.id)">
                            <span class="text-xs font-mono text-gray-500 w-5 text-center">{{ idx + 1 }}</span>
                            <span class="text-xs px-2 py-0.5 bg-indigo-500/20 text-indigo-300 rounded font-mono">{{ block.type }}</span>
                            <span v-if="block.wysiwyg_override" class="text-xs text-amber-400">HTML override</span>
                            <span class="flex-1" />
                            <button @click.stop="moveBlock(idx, -1)" :disabled="idx === 0"
                                    class="text-gray-600 hover:text-white disabled:opacity-20 transition text-xs px-1">↑</button>
                            <button @click.stop="moveBlock(idx, 1)" :disabled="idx === localBlocks.length - 1"
                                    class="text-gray-600 hover:text-white disabled:opacity-20 transition text-xs px-1">↓</button>
                            <button @click.stop="deleteBlock(block.id)"
                                    class="text-red-700 hover:text-red-400 transition text-xs">✕</button>
                            <span class="text-gray-600 text-xs">{{ expandedBlock === block.id ? '▲' : '▼' }}</span>
                        </div>

                        <!-- Block editor (expanded) -->
                        <div v-if="expandedBlock === block.id" class="border-t border-gray-800 p-4">

                            <!-- Sub-tabs -->
                            <div class="flex gap-1 mb-4">
                                <button v-for="t in ['preview', 'fields', 'html']" :key="t"
                                        @click="setBlockTab(block.id, t)"
                                        class="px-3 py-1 rounded-lg text-xs font-medium transition capitalize"
                                        :class="blockTab(block.id) === t ? 'bg-gray-700 text-white' : 'text-gray-500 hover:text-gray-300'">
                                    {{ t === 'html' ? 'HTML Override' : t }}
                                </button>
                            </div>

                            <!-- Preview -->
                            <div v-if="blockTab(block.id) === 'preview'"
                                 class="rounded-lg border border-gray-800 bg-gray-950 p-4 min-h-[80px]">
                                <ContentBlockRenderer :block="block" />
                            </div>

                            <!-- Fields -->
                            <div v-else-if="blockTab(block.id) === 'fields'">
                                <ComponentFieldEditor
                                    :schema="typeSchema(block.type)"
                                    v-model="block.data"
                                />
                                <button @click="saveBlock(block)"
                                        :disabled="saving === block.id"
                                        class="mt-4 px-4 py-2 text-sm bg-indigo-600 hover:bg-indigo-500 disabled:opacity-50 text-white rounded-lg transition">
                                    {{ saving === block.id ? 'Saving…' : 'Save Block' }}
                                </button>
                            </div>

                            <!-- HTML Override -->
                            <div v-else>
                                <textarea
                                    v-model="block.wysiwyg_override"
                                    rows="8"
                                    placeholder="Paste raw HTML here to override structured rendering…"
                                    class="w-full bg-gray-950 border border-gray-700 rounded-lg p-3 text-sm text-gray-300 font-mono resize-y focus:outline-none focus:border-indigo-500"
                                />
                                <div class="flex items-center gap-3 mt-3">
                                    <button @click="saveBlock(block)"
                                            :disabled="saving === block.id"
                                            class="px-4 py-2 text-sm bg-indigo-600 hover:bg-indigo-500 disabled:opacity-50 text-white rounded-lg transition">
                                        {{ saving === block.id ? 'Saving…' : 'Save Override' }}
                                    </button>
                                    <button v-if="block.wysiwyg_override"
                                            @click="block.wysiwyg_override = null; saveBlock(block)"
                                            class="px-3 py-2 text-sm text-red-500 hover:text-red-400 transition">
                                        Clear Override
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Add block -->
                    <div class="bg-gray-900 rounded-xl border border-gray-800 p-4">
                        <button v-if="!showAddBlock"
                                @click="showAddBlock = true"
                                class="text-sm text-indigo-400 hover:text-indigo-300 transition">
                            + Add Block
                        </button>
                        <div v-else class="flex items-center gap-3">
                            <select v-model="newBlockType"
                                    class="flex-1 bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-indigo-500">
                                <option v-for="(schema, type) in blockTypes" :key="type" :value="type">
                                    {{ type }}
                                </option>
                            </select>
                            <button @click="addBlock"
                                    class="px-4 py-2 text-sm bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg transition">
                                Add
                            </button>
                            <button @click="showAddBlock = false"
                                    class="px-3 py-2 text-sm text-gray-500 hover:text-gray-300 transition">
                                Cancel
                            </button>
                        </div>
                    </div>
                </div>

                <!-- SEO tab -->
                <div v-else-if="activeTab === 'seo'" class="space-y-4">
                    <div v-if="version?.seo_data" class="space-y-4">

                        <!-- Meta Tags -->
                        <div class="bg-gray-900 rounded-xl border border-gray-800 p-5">
                            <h3 class="text-sm font-semibold text-white mb-3">🏷️ Meta Tags</h3>
                            <div class="space-y-3">
                                <div v-if="version.seo_data.seo_title">
                                    <label class="text-xs text-gray-500 uppercase tracking-wide">Title Tag</label>
                                    <p class="text-white text-sm mt-1">{{ version.seo_data.seo_title }}
                                        <span class="text-xs text-gray-600 ml-2">({{ version.seo_data.seo_title?.length }} chars)</span>
                                    </p>
                                </div>
                                <div v-if="version.seo_data.meta_description">
                                    <label class="text-xs text-gray-500 uppercase tracking-wide">Meta Description</label>
                                    <p class="text-gray-300 text-sm mt-1">{{ version.seo_data.meta_description }}
                                        <span class="text-xs text-gray-600 ml-2">({{ version.seo_data.meta_description?.length }} chars)</span>
                                    </p>
                                </div>
                                <div v-if="version.seo_data.canonical_url" class="text-sm">
                                    <label class="text-xs text-gray-500 uppercase tracking-wide">Canonical</label>
                                    <p class="text-indigo-400 font-mono text-xs mt-1">{{ version.seo_data.canonical_url }}</p>
                                </div>
                                <div v-if="version.seo_data.meta_robots" class="text-sm">
                                    <label class="text-xs text-gray-500 uppercase tracking-wide">Robots</label>
                                    <p class="text-gray-300 font-mono text-xs mt-1">{{ version.seo_data.meta_robots }}</p>
                                </div>
                                <div v-if="version.seo_data.keywords?.length">
                                    <label class="text-xs text-gray-500 uppercase tracking-wide">Keywords</label>
                                    <div class="flex flex-wrap gap-2 mt-2">
                                        <span v-for="kw in version.seo_data.keywords" :key="kw"
                                              class="px-2 py-1 bg-indigo-900/40 text-indigo-300 rounded text-xs">{{ kw }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Open Graph -->
                        <div v-if="version.seo_data.og_title" class="bg-gray-900 rounded-xl border border-gray-800 p-5">
                            <h3 class="text-sm font-semibold text-white mb-3">📘 Open Graph</h3>
                            <div class="space-y-2 text-sm">
                                <div><span class="text-gray-500">og:title:</span> <span class="text-gray-300 ml-2">{{ version.seo_data.og_title }}</span></div>
                                <div v-if="version.seo_data.og_description"><span class="text-gray-500">og:description:</span> <span class="text-gray-300 ml-2">{{ version.seo_data.og_description }}</span></div>
                                <div><span class="text-gray-500">og:type:</span> <span class="text-gray-400 font-mono text-xs ml-2">{{ version.seo_data.og_type }}</span></div>
                                <div v-if="version.seo_data.og_locale"><span class="text-gray-500">og:locale:</span> <span class="text-gray-400 font-mono text-xs ml-2">{{ version.seo_data.og_locale }}</span></div>
                            </div>
                        </div>

                        <!-- Twitter Card -->
                        <div v-if="version.seo_data.twitter_title" class="bg-gray-900 rounded-xl border border-gray-800 p-5">
                            <h3 class="text-sm font-semibold text-white mb-3">🐦 Twitter Card</h3>
                            <div class="space-y-2 text-sm">
                                <div><span class="text-gray-500">card:</span> <span class="text-gray-400 font-mono text-xs ml-2">{{ version.seo_data.twitter_card }}</span></div>
                                <div><span class="text-gray-500">title:</span> <span class="text-gray-300 ml-2">{{ version.seo_data.twitter_title }}</span></div>
                                <div v-if="version.seo_data.twitter_description"><span class="text-gray-500">description:</span> <span class="text-gray-300 ml-2">{{ version.seo_data.twitter_description }}</span></div>
                            </div>
                        </div>

                        <!-- JSON-LD -->
                        <div v-if="version.seo_data.json_ld_article" class="bg-gray-900 rounded-xl border border-gray-800 p-5">
                            <h3 class="text-sm font-semibold text-white mb-3">📋 JSON-LD Structured Data</h3>
                            <div class="space-y-3">
                                <div>
                                    <label class="text-xs text-gray-500 uppercase tracking-wide">Article / BlogPosting</label>
                                    <pre class="text-xs text-gray-400 bg-gray-950 rounded-lg p-3 mt-1 overflow-x-auto max-h-64">{{ JSON.stringify(version.seo_data.json_ld_article, null, 2) }}</pre>
                                </div>
                                <div v-if="version.seo_data.json_ld_breadcrumb">
                                    <label class="text-xs text-gray-500 uppercase tracking-wide">BreadcrumbList</label>
                                    <pre class="text-xs text-gray-400 bg-gray-950 rounded-lg p-3 mt-1 overflow-x-auto">{{ JSON.stringify(version.seo_data.json_ld_breadcrumb, null, 2) }}</pre>
                                </div>
                            </div>
                        </div>

                        <!-- Analysis -->
                        <div v-if="version.seo_data.keyword_density || version.seo_data.body_suggestions?.length"
                             class="bg-gray-900 rounded-xl border border-gray-800 p-5">
                            <h3 class="text-sm font-semibold text-white mb-3">📊 SEO Analysis</h3>
                            <div class="space-y-3">
                                <div v-if="version.seo_data.word_count" class="flex justify-between text-sm">
                                    <span class="text-gray-500">Word Count</span>
                                    <span class="text-gray-300">{{ version.seo_data.word_count }}</span>
                                </div>
                                <div v-if="version.seo_data.readability_score" class="flex justify-between text-sm">
                                    <span class="text-gray-500">Readability</span>
                                    <span class="text-gray-300">{{ version.seo_data.readability_score }}</span>
                                </div>
                                <div v-if="version.seo_data.keyword_density && typeof version.seo_data.keyword_density === 'object'">
                                    <label class="text-xs text-gray-500 uppercase tracking-wide">Keyword Density</label>
                                    <div class="grid grid-cols-2 gap-2 mt-2">
                                        <div v-for="(d, kw) in version.seo_data.keyword_density" :key="kw" class="flex justify-between text-xs">
                                            <span class="text-gray-400">{{ kw }}</span>
                                            <span class="text-indigo-400 font-mono">{{ typeof d === 'number' ? (d * 100).toFixed(1) + '%' : d }}</span>
                                        </div>
                                    </div>
                                </div>
                                <div v-if="version.seo_data.body_suggestions?.length">
                                    <label class="text-xs text-gray-500 uppercase tracking-wide">Suggestions</label>
                                    <ul class="mt-2 space-y-1">
                                        <li v-for="(s, i) in version.seo_data.body_suggestions" :key="i" class="text-xs text-gray-400 flex gap-2">
                                            <span class="text-indigo-400">•</span> {{ s }}
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                    <p v-else class="text-gray-600 text-sm bg-gray-900 rounded-xl border border-gray-800 p-6">No SEO data for this version.</p>
                </div>

                <!-- Metadata tab -->
                <div v-else class="bg-gray-900 rounded-xl border border-gray-800 p-6">
                    <div v-if="version?.structured_fields" class="space-y-3">
                        <label class="text-xs text-gray-500 uppercase tracking-wide block">Structured Fields</label>
                        <div v-for="(val, key) in version.structured_fields" :key="key"
                             class="flex justify-between py-2 border-b border-gray-800 text-sm">
                            <span class="text-gray-500">{{ key }}</span>
                            <span class="text-gray-300">{{ val }}</span>
                        </div>
                    </div>
                    <div v-if="version?.ai_metadata" class="mt-4 space-y-3">
                        <label class="text-xs text-gray-500 uppercase tracking-wide block">AI Metadata</label>
                        <pre class="text-xs text-gray-500 bg-gray-950 rounded-lg p-3 overflow-x-auto">{{ JSON.stringify(version.ai_metadata, null, 2) }}</pre>
                    </div>
                    <p v-if="!version?.structured_fields && !version?.ai_metadata" class="text-gray-600 text-sm">
                        No additional metadata.
                    </p>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="space-y-5">

                <!-- Hero Image -->
                <div v-if="content.hero_image_url" class="bg-gray-900 rounded-xl border border-gray-800 p-5">
                    <h3 class="text-sm font-medium text-white mb-3">Hero Image</h3>
                    <img :src="content.hero_image_url"
                         :alt="version?.title"
                         class="w-full rounded-lg shadow-md object-cover" />
                </div>

                <!-- Update Brief -->
                <div class="bg-gray-900 rounded-xl border border-gray-800 p-5">
                    <div class="flex items-center justify-between mb-3">
                        <h3 class="text-sm font-medium text-white">✏️ Update Content</h3>
                        <button @click="showUpdateBrief = !showUpdateBrief"
                                class="text-xs text-gray-500 hover:text-gray-300 transition">
                            {{ showUpdateBrief ? 'Cancel' : 'Open' }}
                        </button>
                    </div>
                    <div v-if="!showUpdateBrief">
                        <p class="text-xs text-gray-500">Create an update brief to revise this content with AI.</p>
                    </div>
                    <div v-else class="space-y-3">
                        <textarea
                            v-model="updatePrompt"
                            rows="4"
                            placeholder="Describe what to change, e.g. 'Make the tone more casual, add a section about pricing, update stats to 2026...'"
                            class="w-full bg-gray-950 border border-gray-700 rounded-lg p-3 text-sm text-gray-300 resize-y focus:outline-none focus:border-indigo-500 placeholder-gray-600"
                        />
                        <div class="flex items-center gap-2">
                            <button @click="submitUpdateBrief"
                                    :disabled="!updatePrompt.trim() || submittingUpdate"
                                    class="flex-1 px-4 py-2 text-sm bg-indigo-600 hover:bg-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed text-white rounded-lg transition font-medium">
                                {{ submittingUpdate ? '⏳ Submitting…' : '🚀 Run Update Pipeline' }}
                            </button>
                        </div>
                        <p class="text-xs text-gray-600">This creates a brief and runs the full pipeline (generate → illustrate → SEO → review → publish).</p>
                    </div>
                </div>

                <!-- Scores -->
                <div class="bg-gray-900 rounded-xl border border-gray-800 p-5">
                    <h3 class="text-sm font-medium text-white mb-4">Scores</h3>
                    <div class="space-y-3">
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-gray-500">Quality</span>
                            <span class="text-lg font-bold" :class="version?.quality_score ? scoreColor(version.quality_score) : 'text-gray-700'">
                                {{ version?.quality_score ?? '—' }}
                                <span v-if="version?.quality_score" class="text-xs text-gray-600">/100</span>
                            </span>
                        </div>
                        <div v-if="version?.quality_score" class="h-1.5 bg-gray-800 rounded-full overflow-hidden">
                            <div class="h-full rounded-full transition-all"
                                 :class="version.quality_score >= 80 ? 'bg-emerald-500' : version.quality_score >= 60 ? 'bg-amber-500' : 'bg-red-500'"
                                 :style="{ width: `${version.quality_score}%` }" />
                        </div>
                        <div class="flex items-center justify-between pt-1">
                            <span class="text-sm text-gray-500">SEO</span>
                            <span class="text-lg font-bold" :class="version?.seo_score ? scoreColor(version.seo_score) : 'text-gray-700'">
                                {{ version?.seo_score ?? '—' }}
                                <span v-if="version?.seo_score" class="text-xs text-gray-600">/100</span>
                            </span>
                        </div>
                        <div v-if="version?.seo_score" class="h-1.5 bg-gray-800 rounded-full overflow-hidden">
                            <div class="h-full rounded-full bg-blue-500"
                                 :style="{ width: `${version.seo_score}%` }" />
                        </div>
                    </div>
                </div>

                <!-- Details -->
                <div class="bg-gray-900 rounded-xl border border-gray-800 p-5 space-y-3">
                    <h3 class="text-sm font-medium text-white mb-1">Details</h3>
                    <div class="space-y-2 text-sm">
                        <div class="flex justify-between">
                            <span class="text-gray-500">Type</span>
                            <span class="text-gray-300">{{ content.type_name ?? content.type }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500">Locale</span>
                            <span class="text-gray-300">{{ content.locale }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500">Author</span>
                            <span :class="version?.author_type === 'ai_agent' ? 'text-indigo-400' : 'text-emerald-400'">
                                {{ version?.author_type === 'ai_agent' ? '🤖 AI' : '👤 Human' }}
                            </span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500">Version</span>
                            <span class="text-gray-300">v{{ version?.version_number ?? '—' }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500">Created</span>
                            <span class="text-gray-400 text-xs">{{ content.created_at }}</span>
                        </div>
                        <div v-if="content.published_at" class="flex justify-between">
                            <span class="text-gray-500">Published</span>
                            <span class="text-gray-400 text-xs">{{ content.published_at }}</span>
                        </div>
                    </div>
                </div>

                <!-- Version history -->
                <div v-if="versions.length > 1" class="bg-gray-900 rounded-xl border border-gray-800 p-5">
                    <h3 class="text-sm font-medium text-white mb-3">Version History</h3>
                    <div class="space-y-2">
                        <div v-for="v in versions" :key="v.id"
                             class="flex items-center justify-between py-2 border-b border-gray-800/50 last:border-0">
                            <div>
                                <span class="text-xs text-gray-400">v{{ v.version_number }}</span>
                                <span v-if="v.is_current"
                                      class="ml-2 text-xs px-1.5 py-0.5 bg-indigo-500/20 text-indigo-400 rounded">current</span>
                                <p class="text-xs text-gray-600 mt-0.5">{{ v.created_at }}</p>
                            </div>
                            <div class="text-right">
                                <span :class="v.author_type === 'ai_agent' ? 'text-indigo-400' : 'text-emerald-400'" class="text-xs">
                                    {{ v.author_type === 'ai_agent' ? '🤖' : '👤' }}
                                </span>
                                <p v-if="v.quality_score" class="text-xs text-gray-600">Q{{ v.quality_score }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import MainLayout from '../../Layouts/MainLayout.vue';
export default { layout: MainLayout };
</script>
