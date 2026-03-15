<script setup>
import { ref, computed, watch } from 'vue';

const props = defineProps({
    sourceContent: { type: Object, required: true },
    targetLocale:  { type: String, required: true },
    targetContent: { type: Object, default: null },
});

const emit = defineEmits(['saved', 'translated']);

const title   = ref(props.targetContent?.title   ?? '');
const excerpt = ref(props.targetContent?.excerpt  ?? '');
const body    = ref(props.targetContent?.body     ?? '');

watch(() => props.targetContent, (val) => {
    title.value   = val?.title   ?? '';
    excerpt.value = val?.excerpt  ?? '';
    body.value    = val?.body     ?? '';
});

const estimate       = ref(null);
const loadingEst     = ref(false);
const estimateError  = ref(null);
const showConfirm    = ref(false);
const translating    = ref(false);
const translateError = ref(null);
const saving         = ref(false);
const saveError      = ref(null);
const saveSuccess    = ref(false);
const hasTarget      = computed(() => !!props.targetContent);

async function fetchEstimate() {
    loadingEst.value    = true;
    estimateError.value = null;
    try {
        const url = '/v1/content/' + props.sourceContent.id
            + '/translate/estimate?target_locale=' + props.targetLocale;
        const res = await fetch(url, { headers: { Accept: 'application/json' } });
        if (!res.ok) throw new Error('HTTP ' + res.status);
        estimate.value = await res.json();
    } catch (e) {
        estimateError.value = e.message;
    } finally {
        loadingEst.value = false;
    }
}

async function openTranslateConfirm() {
    showConfirm.value = true;
    if (!estimate.value) await fetchEstimate();
}

async function confirmTranslate() {
    translating.value    = true;
    translateError.value = null;
    try {
        const res = await fetch('/v1/content/' + props.sourceContent.id + '/translate', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
            body: JSON.stringify({ target_locale: props.targetLocale }),
        });
        if (!res.ok) throw new Error('HTTP ' + res.status);
        emit('translated', await res.json());
        showConfirm.value = false;
    } catch (e) {
        translateError.value = e.message;
    } finally {
        translating.value = false;
    }
}

async function save() {
    saving.value      = true;
    saveError.value   = null;
    saveSuccess.value = false;
    try {
        const url    = props.targetContent?.id
            ? '/v1/content/' + props.targetContent.id
            : '/v1/content/' + props.sourceContent.id + '/translations';
        const method = props.targetContent?.id ? 'PUT' : 'POST';
        const res    = await fetch(url, {
            method,
            headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
            body: JSON.stringify({
                locale:  props.targetLocale,
                title:   title.value,
                excerpt: excerpt.value,
                body:    body.value,
            }),
        });
        if (!res.ok) throw new Error('HTTP ' + res.status);
        saveSuccess.value = true;
        emit('saved', await res.json());
    } catch (e) {
        saveError.value = e.message;
    } finally {
        saving.value = false;
    }
}
</script>

<template>
    <div class="grid grid-cols-2 gap-6">
        <!-- Source (read-only) -->
        <div class="flex flex-col gap-4 bg-gray-900 rounded-xl p-5 border border-gray-700">
            <div class="flex items-center gap-2 mb-1">
                <span class="text-xs font-semibold uppercase tracking-wider text-gray-400">Source</span>
                <span class="px-2 py-0.5 rounded text-xs bg-gray-800 text-indigo-300 font-mono">
                    {{ sourceContent.locale ?? 'default' }}
                </span>
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">Title</label>
                <div class="w-full rounded-lg bg-gray-800 border border-gray-700 px-3 py-2 text-gray-200 text-sm select-all">
                    {{ sourceContent.title ?? '—' }}
                </div>
            </div>
            <div v-if="sourceContent.excerpt">
                <label class="block text-xs text-gray-500 mb-1">Excerpt</label>
                <div class="w-full rounded-lg bg-gray-800 border border-gray-700 px-3 py-2 text-gray-300 text-sm whitespace-pre-wrap select-all">
                    {{ sourceContent.excerpt }}
                </div>
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">Body</label>
                <div class="w-full rounded-lg bg-gray-800 border border-gray-700 px-3 py-2 text-gray-300 text-sm whitespace-pre-wrap overflow-y-auto max-h-96 select-all">
                    {{ sourceContent.body ?? '—' }}
                </div>
            </div>
        </div>

        <!-- Target (editable) -->
        <div class="flex flex-col gap-4 bg-gray-900 rounded-xl p-5 border border-gray-700">
            <div class="flex items-center justify-between mb-1">
                <div class="flex items-center gap-2">
                    <span class="text-xs font-semibold uppercase tracking-wider text-gray-400">Target</span>
                    <span class="px-2 py-0.5 rounded text-xs bg-indigo-900/60 text-indigo-300 font-mono">
                        {{ targetLocale }}
                    </span>
                </div>
                <button
                    v-if="!hasTarget && !showConfirm"
                    @click="openTranslateConfirm"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-medium transition-colors"
                >
                    ✨ Translate with AI
                </button>
            </div>

            <template v-if="!hasTarget && !showConfirm">
                <div class="flex-1 flex flex-col items-center justify-center gap-3 py-16 text-center">
                    <span class="text-4xl">🌐</span>
                    <p class="text-gray-400 text-sm">No translation yet for <strong class="text-gray-200">{{ targetLocale }}</strong>.</p>
                    <button @click="openTranslateConfirm"
                        class="px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-medium transition-colors">
                        ✨ Translate with AI
                    </button>
                </div>
            </template>

            <template v-if="showConfirm">
                <div class="rounded-xl border border-indigo-700/50 bg-indigo-900/20 p-4 flex flex-col gap-3">
                    <p class="text-sm font-semibold text-indigo-300">AI Translation Cost Estimate</p>
                    <div v-if="loadingEst" class="text-xs text-gray-400 animate-pulse">Fetching estimate…</div>
                    <div v-else-if="estimateError" class="text-xs text-red-400">{{ estimateError }}</div>
                    <template v-else-if="estimate">
                        <dl class="grid grid-cols-2 gap-x-4 gap-y-1 text-xs text-gray-300">
                            <dt>Tokens in</dt>  <dd class="font-mono">{{ estimate.tokens_in ?? '—' }}</dd>
                            <dt>Tokens out</dt> <dd class="font-mono">{{ estimate.tokens_out ?? '—' }}</dd>
                            <dt>Est. cost</dt>  <dd class="font-mono text-amber-300">{{ estimate.cost_display ?? '—' }}</dd>
                        </dl>
                    </template>
                    <div v-if="translateError" class="text-xs text-red-400">{{ translateError }}</div>
                    <div class="flex gap-2 pt-1">
                        <button @click="confirmTranslate" :disabled="translating || loadingEst"
                            class="px-3 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-500 disabled:opacity-50 text-white text-xs font-medium transition-colors">
                            {{ translating ? 'Translating…' : 'Confirm & Translate' }}
                        </button>
                        <button @click="showConfirm = false"
                            class="px-3 py-1.5 rounded-lg bg-gray-700 hover:bg-gray-600 text-gray-300 text-xs font-medium transition-colors">
                            Cancel
                        </button>
                    </div>
                </div>
            </template>

            <template v-if="hasTarget">
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Title</label>
                    <input v-model="title" type="text"
                        class="w-full rounded-lg bg-gray-800 border border-gray-700 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 px-3 py-2 text-gray-200 text-sm outline-none" />
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Excerpt</label>
                    <textarea v-model="excerpt" rows="3"
                        class="w-full rounded-lg bg-gray-800 border border-gray-700 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 px-3 py-2 text-gray-300 text-sm outline-none resize-none" />
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Body</label>
                    <textarea v-model="body" rows="12"
                        class="w-full rounded-lg bg-gray-800 border border-gray-700 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 px-3 py-2 text-gray-300 text-sm outline-none resize-none" />
                </div>
                <div v-if="saveError" class="text-xs text-red-400">{{ saveError }}</div>
                <div v-if="saveSuccess" class="text-xs text-emerald-400">✓ Saved successfully</div>
                <div class="flex items-center gap-3 pt-1">
                    <button @click="save" :disabled="saving"
                        class="px-4 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-500 disabled:opacity-50 text-white text-sm font-medium transition-colors">
                        {{ saving ? 'Saving…' : 'Save Translation' }}
                    </button>
                    <button @click="openTranslateConfirm"
                        class="px-3 py-1.5 rounded-lg bg-gray-700 hover:bg-gray-600 text-gray-300 text-xs transition-colors">
                        Re-translate with AI
                    </button>
                </div>
            </template>
        </div>
    </div>
</template>
