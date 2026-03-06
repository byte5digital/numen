<script setup>
import { ref, reactive, computed } from 'vue';
import { router, Link, usePage } from '@inertiajs/vue3';
import ComponentFieldEditor from '../../Blocks/ComponentFieldEditor.vue';
import ComponentRenderer from '../../Blocks/ComponentRenderer.vue';

const props = defineProps({
    page:           { type: Object, required: true },
    componentTypes: { type: Array,  default: () => [] },
    typeSchemas:    { type: Object, default: () => ({}) },
});

const flash = computed(() => usePage().props.flash ?? {});

// ── Per-component state ────────────────────────────────────────────────────
const expandedId   = ref(null);
const activeTab    = reactive({});
const forms        = reactive({});
const htmlOverride = reactive({});
const saving       = reactive({});
const generating   = reactive({});
const briefText    = reactive({});
const showGenerate = reactive({});

props.page.components.forEach((c) => {
    activeTab[c.id]    = 'preview';   // default to preview tab
    forms[c.id]        = JSON.parse(JSON.stringify(c.data ?? {}));
    htmlOverride[c.id] = c.wysiwyg_override ?? '';
    briefText[c.id]    = '';
    showGenerate[c.id] = false;
});

// Build a synthetic component object for ComponentRenderer (reactive to edits)
const previewComponent = (c) => ({
    type:             c.type,
    data:             forms[c.id],
    wysiwyg_override: htmlOverride[c.id] || null,
    recent_content:   [],   // live data not available in admin preview
});

// ── Helpers ────────────────────────────────────────────────────────────────
const typeIcon = {
    hero: '🦸', stats_row: '📊', feature_grid: '🔲',
    pipeline_steps: '⚡', content_list: '📝',
    cta_block: '🚀', tech_stack: '🛠️', rich_text: '📄',
};

const tabs = ['preview', 'fields', 'json', 'html'];
const tabLabel = { preview: 'Preview', fields: 'Fields', json: 'JSON', html: 'HTML Override' };

const toggle = (id) => { expandedId.value = expandedId.value === id ? null : id; };
const toJson  = (id) => JSON.stringify(forms[id] ?? {}, null, 2);

function onJsonInput(id, raw) {
    try { forms[id] = JSON.parse(raw); } catch { /* ignore mid-type */ }
}

// ── Save ───────────────────────────────────────────────────────────────────
function save(component) {
    saving[component.id] = true;
    router.put(
        `/admin/pages/${props.page.id}/components/${component.id}`,
        { data: forms[component.id], wysiwyg_override: htmlOverride[component.id] || null, locked: component.locked },
        { preserveScroll: true, onFinish: () => { saving[component.id] = false; } }
    );
}

// ── Lock toggle ────────────────────────────────────────────────────────────
function toggleLock(component) {
    router.put(
        `/admin/pages/${props.page.id}/components/${component.id}`,
        { data: forms[component.id], wysiwyg_override: htmlOverride[component.id] || null, locked: !component.locked },
        { preserveScroll: true }
    );
}

// ── Delete ─────────────────────────────────────────────────────────────────
function deleteComponent(component) {
    if (!confirm(`Delete "${component.type}" block?`)) return;
    router.delete(`/admin/pages/${props.page.id}/components/${component.id}`, { preserveScroll: true });
}

// ── Add block ──────────────────────────────────────────────────────────────
const newType = ref(props.componentTypes[0] ?? 'rich_text');
function addComponent() {
    router.post(`/admin/pages/${props.page.id}/components`, { type: newType.value }, { preserveScroll: true });
}

// ── Reorder ────────────────────────────────────────────────────────────────
function moveComponent(index, direction) {
    const comps = [...props.page.components];
    const target = index + direction;
    if (target < 0 || target >= comps.length) return;
    [comps[index], comps[target]] = [comps[target], comps[index]];
    const order = comps.map((c) => c.id);
    router.post(`/admin/pages/${props.page.id}/components/reorder`, { order }, { preserveScroll: true });
}

// ── AI Generate ────────────────────────────────────────────────────────────
const briefHints = {
    hero:           'Write a compelling hero for Numen: badge text, two-line headline, subline (max 2 sentences), CTA buttons to /blog and /api/v1/content.',
    stats_row:      'Write 4 key stats for an AI-first CMS product with labels, values, and hex colors.',
    feature_grid:   'Write 6 feature cards: icon emoji, short title, one-sentence description each.',
    pipeline_steps: 'Describe the 5 pipeline steps: Brief → Generate → SEO → Review → Publish with step names and descriptions.',
    content_list:   'Write a headline and subline for the "Latest from the Pipeline" content list section.',
    cta_block:      'Write a CTA block: strong headline, body copy (1-2 sentences), primary button to /login, secondary to byte5.de/kontakt.',
    tech_stack:     'List the tech stack with icon emojis: Laravel 12, Vue 3 + Inertia, Tailwind 4, Claude (Anthropic), bytyBot.',
    rich_text:      'Write HTML content for this section.',
};

function openGenerate(component) {
    showGenerate[component.id] = !showGenerate[component.id];
    if (!briefText[component.id]) briefText[component.id] = briefHints[component.type] ?? '';
}

function generate(component) {
    if (!briefText[component.id]?.trim()) return;
    generating[component.id] = true;
    router.post(
        `/admin/pages/${props.page.id}/components/${component.id}/generate`,
        { brief_description: briefText[component.id] },
        {
            preserveScroll: true,
            onFinish: () => {
                generating[component.id]   = false;
                showGenerate[component.id] = false;
                briefText[component.id]    = '';
            },
        }
    );
}
</script>

<template>
    <div>
        <!-- Header -->
        <div class="flex items-center gap-4 mb-8">
            <Link href="/admin/pages" class="text-gray-500 hover:text-white transition text-sm">← Pages</Link>
            <div class="flex-1">
                <h1 class="text-2xl font-bold text-white">{{ page.title }}</h1>
                <p class="text-gray-500 text-sm mt-0.5">
                    <span class="font-mono text-gray-400">/{{ page.slug }}</span>
                    <span class="mx-2 text-gray-700">·</span>
                    <span :class="page.status === 'published' ? 'text-emerald-400' : 'text-amber-400'">{{ page.status }}</span>
                    <span class="mx-2 text-gray-700">·</span>{{ page.components.length }} blocks
                </p>
            </div>
            <a :href="`/${page.slug === 'home' ? '' : page.slug}`" target="_blank"
               class="text-xs text-gray-500 hover:text-white border border-gray-700 rounded-lg px-3 py-1.5 transition">
                Live site ↗
            </a>
        </div>

        <!-- Flash -->
        <div v-if="flash.success"
             class="mb-5 px-4 py-3 bg-emerald-500/10 border border-emerald-500/20 rounded-lg text-emerald-400 text-sm">
            {{ flash.success }}
        </div>

        <!-- Blocks -->
        <div class="space-y-4 mb-8">
            <div v-for="component in page.components" :key="component.id"
                 class="rounded-xl border overflow-hidden"
                 :class="component.locked ? 'border-amber-900/40 bg-gray-900' : 'border-gray-800 bg-gray-900'">

                <!-- Block header -->
                <div class="flex items-center px-5 py-3 gap-3 border-b border-gray-800">
                    <button @click="toggle(component.id)" class="flex-1 flex items-center gap-3 text-left">
                        <span class="text-lg shrink-0">{{ typeIcon[component.type] ?? '🔲' }}</span>
                        <div>
                            <span class="text-white font-medium text-sm">{{ component.type }}</span>
                            <span v-if="htmlOverride[component.id]"
                                  class="ml-2 text-xs px-1.5 py-0.5 bg-indigo-500/20 text-indigo-400 rounded">HTML</span>
                            <span v-if="component.ai_generated"
                                  class="ml-2 text-xs px-1.5 py-0.5 bg-emerald-500/20 text-emerald-400 rounded">AI</span>
                            <span v-if="component.locked"
                                  class="ml-2 text-xs px-1.5 py-0.5 bg-amber-500/20 text-amber-400 rounded">Locked</span>
                        </div>
                        <svg class="w-4 h-4 text-gray-600 transition-transform"
                             :class="expandedId === component.id ? 'rotate-180' : ''"
                             fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>
                    <div class="flex items-center gap-2 shrink-0">
                        <button @click="moveComponent(page.components.indexOf(component), -1)"
                                :disabled="page.components.indexOf(component) === 0"
                                class="text-xs px-1.5 py-1 rounded-lg text-gray-500 hover:text-white hover:bg-gray-800 transition disabled:opacity-20 disabled:cursor-not-allowed"
                                title="Move up">↑</button>
                        <button @click="moveComponent(page.components.indexOf(component), 1)"
                                :disabled="page.components.indexOf(component) === page.components.length - 1"
                                class="text-xs px-1.5 py-1 rounded-lg text-gray-500 hover:text-white hover:bg-gray-800 transition disabled:opacity-20 disabled:cursor-not-allowed"
                                title="Move down">↓</button>
                        <button @click="openGenerate(component)" :disabled="component.locked"
                                class="text-xs px-2.5 py-1.5 rounded-lg transition"
                                :class="component.locked ? 'text-gray-700 cursor-not-allowed'
                                    : showGenerate[component.id] ? 'bg-indigo-600/30 text-indigo-300'
                                    : 'text-indigo-400 bg-indigo-500/10 hover:bg-indigo-500/20'">
                            🤖 AI
                        </button>
                        <button @click="toggleLock(component)"
                                class="text-xs px-2.5 py-1.5 rounded-lg transition"
                                :class="component.locked
                                    ? 'text-amber-400 bg-amber-500/10 hover:bg-amber-500/20'
                                    : 'text-gray-500 hover:text-gray-300 hover:bg-gray-800'">
                            {{ component.locked ? '🔒' : '🔓' }}
                        </button>
                        <span class="text-xs text-gray-700 w-5 text-right">#{{ component.sort_order }}</span>
                    </div>
                </div>

                <!-- AI generate panel -->
                <div v-if="showGenerate[component.id]"
                     class="border-b border-indigo-900/30 bg-indigo-950/20 px-5 py-4">
                    <p class="text-xs text-indigo-400 font-medium mb-2">
                        Describe what AI should write for the <strong>{{ component.type }}</strong> block
                    </p>
                    <textarea v-model="briefText[component.id]" rows="3"
                              class="w-full bg-gray-900 border border-indigo-800/40 rounded-lg px-3 py-2 text-sm text-gray-200 resize-none focus:outline-none focus:border-indigo-500 mb-3" />
                    <div class="flex items-center justify-between">
                        <p class="text-xs text-gray-600">
                            Runs async — review in
                            <Link href="/admin/briefs" class="text-indigo-500 hover:underline">Briefs</Link>,
                            then paste into Fields or HTML.
                        </p>
                        <button @click="generate(component)"
                                :disabled="!briefText[component.id]?.trim() || generating[component.id]"
                                class="ml-4 shrink-0 px-4 py-1.5 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-500 transition disabled:opacity-40">
                            {{ generating[component.id] ? 'Starting…' : 'Run →' }}
                        </button>
                    </div>
                </div>

                <!-- Editor panel -->
                <div v-if="expandedId === component.id">

                    <!-- Tabs -->
                    <div class="flex gap-1 px-5 pt-4 pb-0">
                        <button v-for="tab in tabs" :key="tab"
                                @click="activeTab[component.id] = tab"
                                class="px-4 py-2 rounded-t-lg text-sm font-medium transition border-b-2"
                                :class="activeTab[component.id] === tab
                                    ? 'bg-gray-800 text-white border-indigo-500'
                                    : 'text-gray-500 hover:text-gray-300 border-transparent hover:bg-gray-800/50'">
                            {{ tabLabel[tab] }}
                        </button>
                    </div>

                    <!-- Preview tab — renders actual Vue block components -->
                    <div v-if="activeTab[component.id] === 'preview'"
                         class="bg-gray-950 border-t border-gray-800">
                        <div class="relative">
                            <!-- Preview badge -->
                            <div class="absolute top-3 right-3 z-10 px-2 py-1 bg-gray-900/80 border border-gray-700 rounded text-xs text-gray-500 backdrop-blur-sm">
                                Live preview · updates as you edit Fields
                            </div>
                            <!-- Actual block rendered with current form data -->
                            <ComponentRenderer :component="previewComponent(component)" />
                            <!-- Placeholder when data is empty -->
                            <div v-if="!Object.keys(forms[component.id] ?? {}).length && !htmlOverride[component.id]"
                                 class="absolute inset-0 flex items-center justify-center bg-gray-950/80">
                                <p class="text-gray-600 text-sm">
                                    No data yet — switch to <button @click="activeTab[component.id] = 'fields'"
                                    class="text-indigo-400 hover:underline">Fields</button> to start editing.
                                </p>
                            </div>
                        </div>
                        <!-- Save row inside preview -->
                        <div class="flex items-center justify-between px-5 py-3 border-t border-gray-800">
                            <button @click="deleteComponent(component)"
                                    class="text-xs text-red-600 hover:text-red-400 transition">Delete block</button>
                            <button @click="save(component)" :disabled="saving[component.id]"
                                    class="px-5 py-2 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-500 transition disabled:opacity-50">
                                {{ saving[component.id] ? 'Saving…' : 'Save block' }}
                            </button>
                        </div>
                    </div>

                    <!-- Fields tab -->
                    <div v-else-if="activeTab[component.id] === 'fields'" class="px-5 py-5 bg-gray-900">
                        <ComponentFieldEditor
                            :schema="typeSchemas[component.type] ?? {}"
                            :model-value="forms[component.id]"
                            @update:model-value="(v) => { forms[component.id] = v; }"
                        />
                        <div class="flex items-center justify-between mt-5 pt-4 border-t border-gray-800">
                            <button @click="deleteComponent(component)"
                                    class="text-xs text-red-600 hover:text-red-400 transition">Delete block</button>
                            <div class="flex items-center gap-3">
                                <button @click="activeTab[component.id] = 'preview'"
                                        class="text-xs text-indigo-400 hover:text-indigo-300 transition">
                                    Preview →
                                </button>
                                <button @click="save(component)" :disabled="saving[component.id]"
                                        class="px-5 py-2 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-500 transition disabled:opacity-50">
                                    {{ saving[component.id] ? 'Saving…' : 'Save block' }}
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- JSON tab -->
                    <div v-else-if="activeTab[component.id] === 'json'" class="px-5 py-5 bg-gray-900">
                        <label class="block text-xs text-gray-500 mb-2">Raw JSON — edits sync to Fields &amp; Preview</label>
                        <textarea :value="toJson(component.id)"
                                  @input="onJsonInput(component.id, $event.target.value)"
                                  rows="16" spellcheck="false"
                                  class="w-full bg-gray-950 border border-gray-700 rounded-lg px-4 py-3 text-sm text-gray-200 font-mono resize-y focus:outline-none focus:border-indigo-500" />
                        <div class="flex items-center justify-between mt-4 pt-4 border-t border-gray-800">
                            <button @click="deleteComponent(component)"
                                    class="text-xs text-red-600 hover:text-red-400 transition">Delete block</button>
                            <button @click="save(component)" :disabled="saving[component.id]"
                                    class="px-5 py-2 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-500 transition disabled:opacity-50">
                                {{ saving[component.id] ? 'Saving…' : 'Save block' }}
                            </button>
                        </div>
                    </div>

                    <!-- HTML Override tab -->
                    <div v-else class="px-5 py-5 bg-gray-900">
                        <label class="block text-xs text-gray-500 mb-1">
                            HTML override
                            <span class="ml-2 text-amber-400">Overrides Fields &amp; Preview when set</span>
                        </label>
                        <textarea v-model="htmlOverride[component.id]" rows="16" spellcheck="false"
                                  placeholder="Paste HTML. Clear to revert to structured rendering."
                                  class="w-full bg-gray-950 border border-gray-700 rounded-lg px-4 py-3 text-sm text-gray-200 font-mono resize-y focus:outline-none focus:border-indigo-500" />
                        <div class="flex items-center justify-between mt-4 pt-4 border-t border-gray-800">
                            <button @click="deleteComponent(component)"
                                    class="text-xs text-red-600 hover:text-red-400 transition">Delete block</button>
                            <div class="flex items-center gap-3">
                                <button @click="activeTab[component.id] = 'preview'"
                                        class="text-xs text-indigo-400 hover:text-indigo-300 transition">
                                    Preview →
                                </button>
                                <button @click="save(component)" :disabled="saving[component.id]"
                                        class="px-5 py-2 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-500 transition disabled:opacity-50">
                                    {{ saving[component.id] ? 'Saving…' : 'Save block' }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div v-if="!page.components.length" class="text-center py-12 text-gray-600 text-sm">
                No blocks yet. Add one below.
            </div>
        </div>

        <!-- Add block -->
        <div class="bg-gray-900 rounded-xl border border-gray-800 border-dashed p-5 flex items-center gap-4">
            <select v-model="newType"
                    class="bg-gray-800 border border-gray-700 text-white rounded-lg px-4 py-2 text-sm focus:outline-none focus:border-indigo-500">
                <option v-for="t in componentTypes" :key="t" :value="t">{{ t }}</option>
            </select>
            <button @click="addComponent"
                    class="px-5 py-2 bg-gray-700 text-white rounded-lg text-sm font-medium hover:bg-gray-600 transition">
                + Add block
            </button>
            <span class="text-xs text-gray-600">Appended at the bottom.</span>
        </div>
    </div>
</template>

<script>
import MainLayout from '../../Layouts/MainLayout.vue';
export default { layout: MainLayout };
</script>
