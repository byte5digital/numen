<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import LocaleStatusBadge from './LocaleStatusBadge.vue';

const props = defineProps({
    contentId:     { type: String, required: true },
    locales:       { type: Array,  required: true },
    currentLocale: { type: String, required: true },
});

const emit = defineEmits(['locale-click']);

const translations   = ref([]);
const personas       = ref([]);
const selectedPersona = ref(null);
const loadError      = ref(null);
let   pollTimer      = null;

const targetLocales = computed(() =>
    props.locales.filter(l => l.code !== props.currentLocale)
);

function statusFor(localeCode) {
    const job = translations.value.find(t => t.target_locale === localeCode);
    if (!job) return 'none';
    return job.status ?? 'none';
}

function jobFor(localeCode) {
    return translations.value.find(t => t.target_locale === localeCode) ?? null;
}

function isStale(job) {
    return job?.is_stale ?? false;
}

function hasPending() {
    return translations.value.some(t => t.status === 'pending' || t.status === 'processing');
}

async function loadTranslations() {
    try {
        const res = await fetch('/v1/content/' + props.contentId + '/translations', {
            headers: { Accept: 'application/json' },
        });
        if (!res.ok) throw new Error('HTTP ' + res.status);
        const data = await res.json();
        translations.value = Array.isArray(data) ? data : (data.data ?? []);
    } catch (e) {
        loadError.value = e.message;
    }
}

function schedulePoll() {
    if (pollTimer) return;
    pollTimer = setInterval(async () => {
        await loadTranslations();
        if (!hasPending()) {
            clearInterval(pollTimer);
            pollTimer = null;
        }
    }, 5000);
}

// Per-locale estimate state
const estimates       = ref({});
const estimateLoading = ref({});
const showEstimate    = ref({});
const translating     = ref({});
const translateError  = ref({});

async function fetchEstimate(localeCode) {
    estimateLoading.value[localeCode] = true;
    try {
        const res = await fetch(
            '/v1/content/' + props.contentId +
            '/translate/estimate?target_locale=' + localeCode,
            { headers: { Accept: 'application/json' } }
        );
        if (!res.ok) throw new Error('HTTP ' + res.status);
        estimates.value[localeCode] = await res.json();
    } catch (e) {
        translateError.value[localeCode] = e.message;
    } finally {
        estimateLoading.value[localeCode] = false;
    }
}

async function openConfirm(localeCode) {
    showEstimate.value  = { ...showEstimate.value,  [localeCode]: true };
    translateError.value[localeCode] = null;
    if (!estimates.value[localeCode]) await fetchEstimate(localeCode);
}

function cancelConfirm(localeCode) {
    showEstimate.value = { ...showEstimate.value, [localeCode]: false };
}

async function dispatchTranslation(localeCode) {
    translating.value[localeCode]    = true;
    translateError.value[localeCode] = null;
    try {
        const body = { target_locale: localeCode };
        if (selectedPersona.value) body.persona_id = selectedPersona.value;
        const res = await fetch('/v1/content/' + props.contentId + '/translate', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
            body: JSON.stringify(body),
        });
        if (!res.ok) throw new Error('HTTP ' + res.status);
        const job = await res.json();
        const idx = translations.value.findIndex(t => t.target_locale === localeCode);
        if (idx >= 0) translations.value[idx] = job;
        else translations.value.push(job);
        showEstimate.value[localeCode] = false;
        schedulePoll();
    } catch (e) {
        translateError.value[localeCode] = e.message;
    } finally {
        translating.value[localeCode] = false;
    }
}

onMounted(async () => {
    await loadTranslations();
    if (hasPending()) schedulePoll();
});

onUnmounted(() => {
    if (pollTimer) clearInterval(pollTimer);
});
</script>

<template>
    <div class="bg-gray-900 rounded-xl border border-gray-700 p-5 flex flex-col gap-4">
        <div class="flex items-center justify-between">
            <h3 class="text-sm font-semibold text-gray-200">AI Translations</h3>
            <select v-model="selectedPersona"
                class="text-xs bg-gray-800 border border-gray-700 rounded-lg px-2 py-1 text-gray-300 outline-none focus:border-indigo-500">
                <option :value="null">Default persona</option>
                <option v-for="p in personas" :key="p.id" :value="p.id">{{ p.name }}</option>
            </select>
        </div>

        <div v-if="loadError" class="text-xs text-red-400">{{ loadError }}</div>

        <ul class="flex flex-col gap-3">
            <li v-for="locale in targetLocales" :key="locale.code"
                class="rounded-lg border border-gray-700 bg-gray-800/50 p-3 flex flex-col gap-2">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <span class="text-sm font-medium text-gray-200">{{ locale.name ?? locale.code }}</span>
                        <span class="font-mono text-xs text-gray-500">{{ locale.code }}</span>
                        <LocaleStatusBadge :status="statusFor(locale.code)" :locale="locale.code" />
                        <span v-if="isStale(jobFor(locale.code))"
                            class="text-xs text-amber-400 flex items-center gap-1">
                            ⚠ Stale
                        </span>
                    </div>

                    <!-- Actions based on status -->
                    <div class="flex items-center gap-2">
                        <!-- Translated -->
                        <template v-if="statusFor(locale.code) === 'completed'">
                            <button @click="emit('locale-click', locale.code)"
                                class="text-xs px-2 py-1 rounded-lg bg-gray-700 hover:bg-gray-600 text-gray-300 transition-colors">
                                View / Edit
                            </button>
                            <button @click="openConfirm(locale.code)"
                                class="text-xs px-2 py-1 rounded-lg bg-amber-900/50 hover:bg-amber-900/80 text-amber-300 transition-colors">
                                Re-translate
                            </button>
                        </template>

                        <!-- Pending / processing -->
                        <template v-else-if="statusFor(locale.code) === 'pending' || statusFor(locale.code) === 'processing'">
                            <span class="flex items-center gap-1.5 text-xs text-indigo-300">
                                <svg class="animate-spin h-3 w-3 text-indigo-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
                                </svg>
                                {{ statusFor(locale.code) === 'processing' ? 'Processing…' : 'Queued…' }}
                            </span>
                        </template>

                        <!-- Not translated -->
                        <template v-else>
                            <button @click="openConfirm(locale.code)"
                                class="text-xs px-2 py-1 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white transition-colors">
                                Translate
                            </button>
                        </template>
                    </div>
                </div>

                <!-- Cost estimate + confirm -->
                <div v-if="showEstimate[locale.code]"
                    class="rounded-lg border border-indigo-700/40 bg-indigo-900/20 p-3 flex flex-col gap-2">
                    <p class="text-xs font-semibold text-indigo-300">Cost Estimate</p>
                    <div v-if="estimateLoading[locale.code]" class="text-xs text-gray-400 animate-pulse">
                        Fetching estimate…
                    </div>
                    <template v-else-if="estimates[locale.code]">
                        <dl class="grid grid-cols-2 gap-x-3 gap-y-0.5 text-xs text-gray-300">
                            <dt>Tokens in</dt>  <dd class="font-mono">{{ estimates[locale.code].tokens_in ?? '—' }}</dd>
                            <dt>Tokens out</dt> <dd class="font-mono">{{ estimates[locale.code].tokens_out ?? '—' }}</dd>
                            <dt>Est. cost</dt>  <dd class="font-mono text-amber-300">{{ estimates[locale.code].cost_display ?? '—' }}</dd>
                        </dl>
                    </template>
                    <div v-if="translateError[locale.code]" class="text-xs text-red-400">
                        {{ translateError[locale.code] }}
                    </div>
                    <div class="flex gap-2">
                        <button @click="dispatchTranslation(locale.code)"
                            :disabled="translating[locale.code] || estimateLoading[locale.code]"
                            class="px-2 py-1 rounded-lg bg-indigo-600 hover:bg-indigo-500 disabled:opacity-50 text-white text-xs font-medium transition-colors">
                            {{ translating[locale.code] ? 'Starting…' : 'Confirm' }}
                        </button>
                        <button @click="cancelConfirm(locale.code)"
                            class="px-2 py-1 rounded-lg bg-gray-700 hover:bg-gray-600 text-gray-300 text-xs transition-colors">
                            Cancel
                        </button>
                    </div>
                </div>
            </li>
        </ul>

        <p v-if="targetLocales.length === 0" class="text-xs text-gray-500 text-center py-4">
            No other locales configured for this space.
        </p>
    </div>
</template>
