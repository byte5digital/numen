<script setup>
import { ref, computed } from 'vue';
import axios from 'axios';
import LocaleStatusBadge from './LocaleStatusBadge.vue';

const props = defineProps({
    // { content_id: { locale: status } }
    matrix:   { type: Object, required: true },
    // Array of locale objects: { locale, label, flag }
    locales:  { type: Array, required: true },
    // Array of content objects: { id, title }
    contents: { type: Array, required: true },
});

const emit = defineEmits(['translated']);

// Local reactive copy so we can optimistically update cells
const localMatrix = ref(JSON.parse(JSON.stringify(props.matrix)));

// Track in-flight requests per "contentId:locale"
const pending = ref(new Set());

function cellKey(contentId, locale) {
    return `${contentId}:${locale}`;
}

function statusFor(contentId, locale) {
    return localMatrix.value[contentId]?.[locale] ?? null;
}

async function translate(contentId, locale) {
    const key = cellKey(contentId, locale);
    if (pending.value.has(key)) return;

    pending.value.add(key);

    // Optimistic update
    if (!localMatrix.value[contentId]) localMatrix.value[contentId] = {};
    localMatrix.value[contentId][locale] = 'pending';

    try {
        await axios.post(`/v1/content/${contentId}/translate`, { locale });
        localMatrix.value[contentId][locale] = 'processing';
        emit('translated', { contentId, locale });
    } catch (err) {
        localMatrix.value[contentId][locale] = 'failed';
        console.error('Translation trigger failed', err);
    } finally {
        pending.value.delete(key);
    }
}

async function translateAllMissing(locale) {
    const missing = props.contents.filter(c => {
        const s = statusFor(c.id, locale);
        return s === null || s === 'failed';
    });
    for (const content of missing) {
        await translate(content.id, locale);
    }
}

// Completion percentage per locale
function completionPct(locale) {
    const total = props.contents.length;
    if (!total) return 0;
    const done = props.contents.filter(c => statusFor(c.id, locale) === 'completed').length;
    return Math.round((done / total) * 100);
}

function hasMissing(locale) {
    return props.contents.some(c => {
        const s = statusFor(c.id, locale);
        return s === null || s === 'failed';
    });
}

function isCellPending(contentId, locale) {
    return pending.value.has(cellKey(contentId, locale));
}

function canTranslate(contentId, locale) {
    const s = statusFor(contentId, locale);
    return s === null || s === 'failed';
}
</script>

<template>
    <div class="overflow-x-auto rounded-lg border border-gray-700/40">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-gray-700/40 bg-gray-900/80">
                    <th class="px-4 py-3 text-left text-xs text-gray-400 font-medium uppercase tracking-wide w-1/4">
                        Content
                    </th>
                    <th
                        v-for="loc in locales"
                        :key="loc.locale"
                        class="px-3 py-3 text-center min-w-[140px]"
                    >
                        <div class="flex flex-col items-center gap-1.5">
                            <span class="flex items-center gap-1 text-xs text-gray-300 font-medium">
                                <span class="text-sm">{{ loc.flag ?? '🌐' }}</span>
                                {{ loc.label ?? loc.locale }}
                            </span>
                            <!-- Completion bar -->
                            <div class="w-full bg-gray-800 rounded-full h-1.5">
                                <div
                                    class="bg-green-500 h-1.5 rounded-full transition-all"
                                    :style="{ width: completionPct(loc.locale) + '%' }"
                                />
                            </div>
                            <span class="text-[10px] text-gray-500">{{ completionPct(loc.locale) }}% done</span>
                            <!-- Bulk translate button -->
                            <button
                                v-if="hasMissing(loc.locale)"
                                type="button"
                                @click="translateAllMissing(loc.locale)"
                                class="text-[10px] px-2 py-0.5 rounded bg-indigo-900/40 text-indigo-300
                                       border border-indigo-700/40 hover:bg-indigo-800/60 transition-colors whitespace-nowrap"
                            >
                                Translate all missing
                            </button>
                        </div>
                    </th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-800/60">
                <tr
                    v-for="content in contents"
                    :key="content.id"
                    class="hover:bg-gray-800/20 transition-colors"
                >
                    <!-- Content title -->
                    <td class="px-4 py-3 text-gray-200 text-sm truncate max-w-[200px]" :title="content.title">
                        {{ content.title }}
                    </td>

                    <!-- Status cells -->
                    <td
                        v-for="loc in locales"
                        :key="loc.locale"
                        class="px-3 py-3 text-center"
                    >
                        <div class="flex flex-col items-center gap-1">
                            <LocaleStatusBadge
                                :status="statusFor(content.id, loc.locale)"
                                :locale="loc.locale"
                                :class="{ 'opacity-60': isCellPending(content.id, loc.locale) }"
                            />
                            <button
                                v-if="canTranslate(content.id, loc.locale)"
                                type="button"
                                :disabled="isCellPending(content.id, loc.locale)"
                                @click="translate(content.id, loc.locale)"
                                class="text-[10px] text-indigo-400 hover:text-indigo-200 transition-colors
                                       disabled:opacity-40 disabled:cursor-not-allowed"
                            >
                                {{ isCellPending(content.id, loc.locale) ? 'Queuing…' : 'Translate' }}
                            </button>
                        </div>
                    </td>
                </tr>
                <tr v-if="!contents.length">
                    <td :colspan="locales.length + 1" class="px-4 py-8 text-center text-gray-500 text-sm italic">
                        No content items found.
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
